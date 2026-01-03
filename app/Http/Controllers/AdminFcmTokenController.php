<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdminFcmTokenController extends Controller
{
    /**
     * Update FCM token for the logged-in admin
     * This is called automatically when admin logs in via JavaScript
     */
    public function updateFcmToken(Request $request)
    {
        Log::info('=== FCM Token Update Request ===', [
            'has_token' => $request->has('fcm_token'),
            'token_length' => $request->has('fcm_token') ? strlen($request->fcm_token) : 0,
            'ip' => $request->ip(),
        ]);

        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            Log::warning('FCM token validation failed', [
                'errors' => $validator->errors()->toArray(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get the authenticated admin (using admin guard)
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            Log::warning('FCM token update attempted without authentication');
            return response()->json([
                'success' => false,
                'message' => 'Admin not authenticated',
            ], 401);
        }

        Log::info('Admin authenticated for FCM token update', [
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
        ]);

        try {
            $token = $request->fcm_token;
            
            // Validate FCM token format
            // Valid FCM tokens are typically 150+ characters
            // Allow tokens that are at least 50 characters (some FCM tokens can be shorter)
            if (strlen($token) < 50) {
                Log::warning('FCM token too short, rejecting', [
                    'admin_id' => $admin->id,
                    'token_length' => strlen($token),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid FCM token format. Token is too short.',
                ], 422);
            }
            
            $admin->update([
                'fcm_token' => $token,
            ]);

            Log::info('Admin FCM token updated successfully', [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'token_length' => strlen($token),
                'token_preview' => substr($token, 0, 20) . '...',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'FCM token updated successfully',
                'admin_id' => $admin->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update admin FCM token', [
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update FCM token',
            ], 500);
        }
    }
}
