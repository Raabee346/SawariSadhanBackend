<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class UserProfileController extends Controller
{
    /**
     * Get user profile
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found',
                'profile' => null
            ], 404);
        }

        // Include user name and email in the profile response
        $profileData = $profile->toArray();
        $profileData['name'] = $user->name;
        $profileData['email'] = $user->email;

        return response()->json([
            'message' => 'Profile retrieved successfully',
            'profile' => $profileData
        ]);
    }

    /**
     * Create or update user profile
     */
    public function updateOrCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255', // ADDED: Validate name field
            'phone_number' => 'nullable|string|max:15',
            'date_of_birth' => 'nullable|string', // Save as string exactly as user provided, no validation or conversion
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        
        // IMPORTANT: Update user name if provided (name belongs to users table, not user_profiles)
        if ($request->has('name') && !empty($request->name)) {
            $user->name = $request->name;
            $user->save();
        }
        
        // Exclude 'name' from profile data (it doesn't belong in user_profiles table)
        $data = $request->except(['profile_picture', 'name']);

        // Handle profile picture upload
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $filename = 'user_' . $user->id . '_' . time() . '.' . $file->extension();
            $path = $file->storeAs('profiles/users', $filename, 'public');
            $data['profile_picture'] = $path;

            // Delete old profile picture if exists
            if ($user->profile && $user->profile->profile_picture) {
                Storage::disk('public')->delete($user->profile->profile_picture);
            }
        }

        $profile = $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $data
        );

        // Include user name and email in the profile response (now with updated name!)
        $profileData = $profile->toArray();
        $profileData['name'] = $user->name;
        $profileData['email'] = $user->email;

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => $profileData
        ]);
    }

    /**
     * Update profile picture
     */
    public function updateProfilePicture(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Delete old profile picture if exists
        if ($user->profile && $user->profile->profile_picture) {
            Storage::disk('public')->delete($user->profile->profile_picture);
        }

        $file = $request->file('profile_picture');
        $filename = 'user_' . $user->id . '_' . time() . '.' . $file->extension();
        $path = $file->storeAs('profiles/users', $filename, 'public');

        $profile = $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            ['profile_picture' => $path]
        );

        // Include user name and email in the profile response
        $profileData = $profile->toArray();
        $profileData['name'] = $user->name;
        $profileData['email'] = $user->email;

        return response()->json([
            'message' => 'Profile picture updated successfully',
            'profile_picture' => $path,
            'profile' => $profileData
        ]);
    }

    /**
     * Delete profile picture
     */
    public function deleteProfilePicture(Request $request)
    {
        $user = $request->user();

        if (!$user->profile || !$user->profile->profile_picture) {
            return response()->json(['message' => 'No profile picture found'], 404);
        }

        Storage::disk('public')->delete($user->profile->profile_picture);
        $user->profile->update(['profile_picture' => null]);

        return response()->json(['message' => 'Profile picture deleted successfully']);
    }
}

