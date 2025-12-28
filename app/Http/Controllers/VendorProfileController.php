<?php

namespace App\Http\Controllers;

use App\Models\VendorProfile;
use App\Models\VendorAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class VendorProfileController extends Controller
{
    /**
     * Get vendor profile
     */
    public function show(Request $request)
    {
        $vendor = $request->user();
        $profile = $vendor->profile;

        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found',
                'profile' => null
            ], 404);
        }

        // Include vendor name in the profile response
        $profileData = $profile->toArray();
        $profileData['name'] = $vendor->name;

        return response()->json([
            'message' => 'Profile retrieved successfully',
            'profile' => $profileData,
            'availabilities' => $vendor->availabilities
        ]);
    }

    /**
     * Create or update vendor profile
     */
    public function updateOrCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'nullable|string|max:15',
            'date_of_birth' => 'nullable|string', // BS date format: YYYY-MM-DD (e.g., 2080-05-15)
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'vehicle_type' => 'nullable|in:bike,auto,car,van',
            'vehicle_number' => 'nullable|string|max:20',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_color' => 'nullable|string|max:50',
            'vehicle_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'license_number' => 'nullable|string|max:50',
            'license_expiry' => 'nullable|string', // BS date format: YYYY-MM-DD (e.g., 2080-05-15)
            'service_latitude' => 'nullable|numeric|between:-90,90',
            'service_longitude' => 'nullable|numeric|between:-180,180',
            'service_radius' => 'nullable|integer|min:1|max:50000', // Reduced minimum from 100 to 1 (in km)
            'service_address' => 'nullable|string',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $vendor = $request->user();
        $data = $request->except(['profile_picture', 'license_document', 'vehicle_rc_document', 'insurance_document', 'citizenship_document', 'pan_document']);

        // Handle profile picture upload
        if ($request->hasFile('profile_picture')) {
            $data['profile_picture'] = $this->uploadFile($request->file('profile_picture'), 'profiles/vendors', 'vendor_' . $vendor->id . '_profile');
            
            if ($vendor->profile && $vendor->profile->profile_picture) {
                Storage::disk('public')->delete($vendor->profile->profile_picture);
            }
        }

        $profile = $vendor->profile()->updateOrCreate(
            ['vendor_id' => $vendor->id],
            $data
        );

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => $profile
        ]);
    }

    /**
     * Upload multiple documents
     */
    public function uploadMultipleDocuments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'license_document' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:5120',
            'vehicle_rc_document' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:5120',
            'insurance_document' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:5120',
            'citizenship_document' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:5120',
            'pan_document' => 'nullable|file|mimes:pdf,jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $vendor = $request->user();
        $documentFields = [
            'license_document' => 'license',
            'vehicle_rc_document' => 'vehicle_rc',
            'insurance_document' => 'insurance',
            'citizenship_document' => 'citizenship',
            'pan_document' => 'pan',
        ];

        $uploadedDocuments = [];
        $updateData = [];

        foreach ($documentFields as $field => $documentType) {
            if ($request->hasFile($field)) {
                $path = $this->uploadFile(
                    $request->file($field),
                    'documents/vendors',
                    'vendor_' . $vendor->id . '_' . $documentType
                );

                // Delete old document if exists
                if ($vendor->profile && $vendor->profile->{$field}) {
                    Storage::disk('public')->delete($vendor->profile->{$field});
                }

                $updateData[$field] = $path;
                $uploadedDocuments[$field] = $path;
            }
        }

        if (empty($updateData)) {
            return response()->json([
                'message' => 'No documents provided',
                'errors' => ['documents' => ['At least one document must be provided']]
            ], 422);
        }

        $profile = $vendor->profile()->updateOrCreate(
            ['vendor_id' => $vendor->id],
            $updateData
        );

        return response()->json([
            'message' => 'Documents uploaded successfully',
            'uploaded_documents' => $uploadedDocuments,
            'profile' => $profile
        ]);
    }

    /**
     * Update service area
     */
    public function updateServiceArea(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_latitude' => 'required|numeric|between:-90,90',
            'service_longitude' => 'required|numeric|between:-180,180',
            'service_radius' => 'required|integer|min:1|max:50000', // Reduced minimum from 100 to 1 (in km)
            'service_address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $vendor = $request->user();
        
        $profile = $vendor->profile()->updateOrCreate(
            ['vendor_id' => $vendor->id],
            $request->only(['service_latitude', 'service_longitude', 'service_radius', 'service_address'])
        );

        return response()->json([
            'message' => 'Service area updated successfully',
            'profile' => $profile
        ]);
    }

    /**
     * Get vendor availability
     */
    public function getAvailability(Request $request)
    {
        $vendor = $request->user();
        $availabilities = $vendor->availabilities;

        return response()->json([
            'message' => 'Availability retrieved successfully',
            'availabilities' => $availabilities
        ]);
    }

    /**
     * Update vendor availability
     */
    public function updateAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'availabilities' => 'required|array',
            'availabilities.*.day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'availabilities.*.is_available' => 'required|boolean',
            'availabilities.*.start_time' => 'nullable|date_format:H:i',
            'availabilities.*.end_time' => 'nullable|date_format:H:i|after:availabilities.*.start_time',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $vendor = $request->user();
        $availabilities = $request->input('availabilities');

        foreach ($availabilities as $availability) {
            VendorAvailability::updateOrCreate(
                [
                    'vendor_id' => $vendor->id,
                    'day_of_week' => $availability['day_of_week']
                ],
                [
                    'is_available' => $availability['is_available'],
                    'start_time' => $availability['start_time'] ?? null,
                    'end_time' => $availability['end_time'] ?? null,
                ]
            );
        }

        return response()->json([
            'message' => 'Availability updated successfully',
            'availabilities' => $vendor->availabilities()->get()
        ]);
    }

    /**
     * Toggle online status
     */
    public function toggleOnlineStatus(Request $request)
    {
        $vendor = $request->user();
        
        $profile = $vendor->profile()->firstOrFail();
        $profile->is_online = !$profile->is_online;
        $profile->save();

        return response()->json([
            'message' => 'Online status updated successfully',
            'is_online' => $profile->is_online,
            'profile' => $profile
        ]);
    }

    /**
     * Toggle available status
     */
    public function toggleAvailableStatus(Request $request)
    {
        $vendor = $request->user();
        
        $profile = $vendor->profile()->firstOrFail();
        $profile->is_available = !$profile->is_available;
        $profile->save();

        return response()->json([
            'message' => 'Available status updated successfully',
            'is_available' => $profile->is_available,
            'profile' => $profile
        ]);
    }

    /**
     * Helper function to upload file
     */
    private function uploadFile($file, $folder, $prefix)
    {
        $filename = $prefix . '_' . time() . '.' . $file->extension();
        return $file->storeAs($folder, $filename, 'public');
    }
}

