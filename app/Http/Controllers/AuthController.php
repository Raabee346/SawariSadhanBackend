<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Vendor;
use App\Models\OtpCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Notifications\EmailOtpNotification;

class AuthController extends Controller
{
    private function getModel(string $type)
    {
        return $type === 'vendor' ? Vendor::class : User::class;
    }

    private function generateUniqueId(): string
    {
        return 'SS-' . strtoupper(uniqid());
    }

    // POST /api/register
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'     => 'required|in:user,vendor',
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email|unique:vendors,email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $type  = $request->input('type');
        $model = $this->getModel($type);

        $entity = $model::create([
            'unique_id' => $this->generateUniqueId(),
            'name'      => $request->input('name'),
            'email'     => $request->input('email'),
            'password'  => Hash::make($request->input('password')),
        ]);

        // create OTP
        $code = rand(100000, 999999);

        OtpCode::create([
            'email'      => $entity->email,
            'code'       => (string) $code,
            'type'       => $type,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $entity->notify(new EmailOtpNotification((string) $code));

        return response()->json([
            'message'   => 'Registered successfully. Please verify OTP sent to email.',
            'unique_id' => $entity->unique_id,
            'type'      => $type,
        ], 201);
    }

    // POST /api/verify-otp
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'  => 'required|in:user,vendor',
            'email' => 'required|email',
            'code'  => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $type  = $request->input('type');
        $email = $request->input('email');
        $code  = $request->input('code');

        $otp = OtpCode::where('email', $email)
            ->where('type', $type)
            ->where('code', $code)
            ->latest()
            ->first();

        if (!$otp || $otp->isExpired()) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        $model = $this->getModel($type);
        $entity = $model::where('email', $email)->first();

        if (!$entity) {
            return response()->json(['message' => 'Account not found'], 404);
        }

        $entity->email_verified_at = now();
        $entity->save();

        $otp->delete();

        // Create token if using Sanctum
        $token = $entity->createToken($type . '-token')->plainTextToken;

        return response()->json([
            'message' => 'Email verified successfully.',
            'token'   => $token,
            'type'    => $type,
        ]);
    }

    // POST /api/login
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'     => 'required|in:user,vendor',
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $type   = $request->input('type');
        $email  = $request->input('email');
        $pass   = $request->input('password');
        $model  = $this->getModel($type);

        $entity = $model::where('email', $email)->first();

        if (!$entity || !Hash::check($pass, $entity->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$entity->email_verified_at) {
            return response()->json(['message' => 'Email not verified'], 403);
        }

        $token = $entity->createToken($type . '-token')->plainTextToken;

        return response()->json([
            'message'   => 'Login successful',
            'token'     => $token,
            'type'      => $type,
            'unique_id' => $entity->unique_id,
        ]);
    }

    // POST /api/logout
    public function logout(Request $request)
    {
        $user = $request->user(); // works for both user & vendor if using Sanctum

        if ($user) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }

    // PUT /api/profile/email
    public function updateEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email|unique:vendors,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $user->email = $request->input('email');
        $user->email_verified_at = null; // require re-verification
        $user->save();

        // Send new OTP
        $type = $user instanceof Vendor ? 'vendor' : 'user';
        $code = rand(100000, 999999);

        OtpCode::create([
            'email'      => $user->email,
            'code'       => (string) $code,
            'type'       => $type,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $user->notify(new EmailOtpNotification((string) $code));

        return response()->json([
            'message' => 'Email updated. Please verify via OTP.'
        ]);
    }

    // PUT /api/profile/password
    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 400);
        }

        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        return response()->json(['message' => 'Password updated successfully']);
    }

    // POST /api/resend-otp
    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'  => 'required|in:user,vendor',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $type  = $request->input('type');
        $email = $request->input('email');
        $model = $this->getModel($type);

        $entity = $model::where('email', $email)->first();

        if (!$entity) {
            return response()->json(['message' => 'Account not found'], 404);
        }

        $code = rand(100000, 999999);

        OtpCode::create([
            'email'      => $email,
            'code'       => (string) $code,
            'type'       => $type,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $entity->notify(new EmailOtpNotification((string) $code));

        return response()->json(['message' => 'OTP resent to email']);
    }
}