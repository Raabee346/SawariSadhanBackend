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

        // Include user name in the profile response
        $profileData = $profile->toArray();
        $profileData['name'] = $user->name;

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
            'phone_number' => 'nullable|string|max:15',
            'date_of_birth' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($value === null || $value === '') {
                    return; // Allow null/empty
                }
                // Check if it's a valid BS date format (e.g., "2080-01-15") or AD date format
                // BS dates typically start with 20xx (years 2000-2099 in BS calendar)
                // AD dates typically start with 19xx or 20xx (years 1900-2100 in AD calendar)
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    // It's in YYYY-MM-DD format
                    $parts = explode('-', $value);
                    $year = (int)$parts[0];
                    $month = (int)$parts[1];
                    $day = (int)$parts[2];
                    
                    // Validate date components
                    if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
                        $fail('The date of birth must be a valid date.');
                        return;
                    }
                    
                    // For AD dates (1900-2100), check if it's before today
                    if ($year >= 1900 && $year <= 2100) {
                        try {
                            $date = \Carbon\Carbon::createFromFormat('Y-m-d', $value);
                            if ($date->isFuture()) {
                                $fail('The date of birth must be before today.');
                            }
                        } catch (\Exception $e) {
                            $fail('The date of birth must be a valid date.');
                        }
                    }
                    // For BS dates (2000-2099), just validate format - store as string
                    // BS dates are stored as-is without conversion
                } else {
                    $fail('The date of birth must be in YYYY-MM-DD format.');
                }
            }],
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
        $data = $request->except('profile_picture');

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

        // Include user name in the profile response
        $profileData = $profile->toArray();
        $profileData['name'] = $user->name;

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

        // Include user name in the profile response
        $profileData = $profile->toArray();
        $profileData['name'] = $user->name;

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

