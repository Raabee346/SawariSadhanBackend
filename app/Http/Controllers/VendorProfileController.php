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
        try {
            $vendor = $request->user();
            
            if (!$vendor) {
                return response()->json([
                    'message' => 'Vendor not found',
                    'profile' => null
                ], 404);
            }
            
            // Load profile relationship
            $profile = $vendor->profile;

            if (!$profile) {
                // Return 200 with null profile instead of 404, so Android can handle it gracefully
                return response()->json([
                    'message' => 'Profile not found. Please create your profile.',
                    'profile' => null,
                    'availabilities' => []
                ], 200);
            }

            // Convert profile to array safely
            try {
                $profileData = $profile->toArray();
            } catch (\Exception $e) {
                \Log::error('Error converting profile to array', [
                    'vendor_id' => $vendor->id,
                    'profile_id' => $profile->id ?? null,
                    'error' => $e->getMessage()
                ]);
                // Fallback: manually build array
                $profileData = [
                    'id' => $profile->id,
                    'vendor_id' => $profile->vendor_id,
                    'phone_number' => $profile->phone_number,
                    'profile_picture' => $profile->profile_picture,
                    'date_of_birth' => $profile->date_of_birth,
                    'gender' => $profile->gender,
                    'address' => $profile->address,
                    'city' => $profile->city,
                    'state' => $profile->state,
                    'pincode' => $profile->pincode,
                    'vehicle_type' => $profile->vehicle_type,
                    'vehicle_number' => $profile->vehicle_number,
                    'vehicle_model' => $profile->vehicle_model,
                    'vehicle_color' => $profile->vehicle_color,
                    'vehicle_year' => $profile->vehicle_year,
                    'license_number' => $profile->license_number,
                    'license_expiry' => $profile->license_expiry,
                    'license_document' => $profile->license_document,
                    'vehicle_rc_document' => $profile->vehicle_rc_document,
                    'insurance_document' => $profile->insurance_document,
                    'citizenship_document' => $profile->citizenship_document,
                    'pan_document' => $profile->pan_document,
                    'service_latitude' => $profile->service_latitude,
                    'service_longitude' => $profile->service_longitude,
                    'service_radius' => $profile->service_radius,
                    'service_address' => $profile->service_address,
                    'is_verified' => $profile->is_verified,
                    'is_online' => $profile->is_online,
                    'is_available' => $profile->is_available,
                    'verification_status' => $profile->verification_status,
                    'rejection_reason' => $profile->rejection_reason,
                    'rating' => $profile->rating,
                    'total_rides' => $profile->total_rides,
                ];
            }
            
            $profileData['name'] = $vendor->name;
            
            // Include created_at from profile (join date)
            // Note: HasBSTimestamps trait returns created_at as BS date string (YYYY-MM-DD)
            // We need to get the raw database value for ISO format
            try {
                // Get raw created_at from database (bypassing the trait accessor)
                $rawCreatedAt = $profile->getOriginal('created_at') ?? $profile->getAttributes()['created_at'] ?? null;
                
                if ($rawCreatedAt) {
                    try {
                        // Try to parse and format as ISO 8601
                        if ($rawCreatedAt instanceof \Carbon\Carbon) {
                            $profileData['created_at'] = $rawCreatedAt->toIso8601String();
                        } elseif (is_string($rawCreatedAt)) {
                            $carbonDate = \Carbon\Carbon::parse($rawCreatedAt);
                            $profileData['created_at'] = $carbonDate->toIso8601String();
                        } else {
                            $profileData['created_at'] = (string) $rawCreatedAt;
                        }
                    } catch (\Exception $e) {
                        // If parsing fails, try simple format
                        try {
                            if ($rawCreatedAt instanceof \Carbon\Carbon) {
                                $profileData['created_at'] = $rawCreatedAt->format('Y-m-d\TH:i:s\Z');
                            } elseif (is_string($rawCreatedAt)) {
                                $carbonDate = \Carbon\Carbon::parse($rawCreatedAt);
                                $profileData['created_at'] = $carbonDate->format('Y-m-d\TH:i:s\Z');
                            } else {
                                $profileData['created_at'] = (string) $rawCreatedAt;
                            }
                        } catch (\Exception $e2) {
                            // If all else fails, use the raw value
                            $profileData['created_at'] = is_string($rawCreatedAt) ? $rawCreatedAt : (string) $rawCreatedAt;
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('Error formatting created_at', [
                    'vendor_id' => $vendor->id,
                    'error' => $e->getMessage()
                ]);
                // Don't include created_at if we can't format it
            }

            // Get availabilities safely
            $availabilities = [];
            try {
                // Load availabilities relationship - use query builder to avoid potential issues
                $availabilitiesQuery = \App\Models\VendorAvailability::where('vendor_id', $vendor->id)->get();
                
                if ($availabilitiesQuery && $availabilitiesQuery->count() > 0) {
                    $availabilities = $availabilitiesQuery->map(function($item) {
                        try {
                            $startTime = null;
                            $endTime = null;
                            
                            // Handle start_time safely - get raw value to avoid casting issues
                            if ($item->start_time !== null) {
                                $rawStartTime = $item->getOriginal('start_time') ?? $item->getAttributes()['start_time'] ?? $item->start_time;
                                if (is_string($rawStartTime)) {
                                    $startTime = $rawStartTime;
                                } elseif ($rawStartTime instanceof \Carbon\Carbon) {
                                    $startTime = $rawStartTime->format('H:i');
                                } else {
                                    try {
                                        $startTime = \Carbon\Carbon::parse($rawStartTime)->format('H:i');
                                    } catch (\Exception $e) {
                                        $startTime = (string) $rawStartTime;
                                    }
                                }
                            }
                            
                            // Handle end_time safely - get raw value to avoid casting issues
                            if ($item->end_time !== null) {
                                $rawEndTime = $item->getOriginal('end_time') ?? $item->getAttributes()['end_time'] ?? $item->end_time;
                                if (is_string($rawEndTime)) {
                                    $endTime = $rawEndTime;
                                } elseif ($rawEndTime instanceof \Carbon\Carbon) {
                                    $endTime = $rawEndTime->format('H:i');
                                } else {
                                    try {
                                        $endTime = \Carbon\Carbon::parse($rawEndTime)->format('H:i');
                                    } catch (\Exception $e) {
                                        $endTime = (string) $rawEndTime;
                                    }
                                }
                            }
                            
                            return [
                                'id' => $item->id,
                                'vendor_id' => $item->vendor_id,
                                'day_of_week' => $item->day_of_week,
                                'is_available' => (bool) $item->is_available,
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                            ];
                        } catch (\Exception $e) {
                            \Log::warning('Error processing availability item', [
                                'item_id' => $item->id ?? null,
                                'error' => $e->getMessage()
                            ]);
                            return null;
                        }
                    })->filter()->values()->toArray(); // Filter out null values
                }
            } catch (\Exception $e) {
                \Log::warning('Error loading vendor availabilities', [
                    'vendor_id' => $vendor->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $availabilities = [];
            }

            return response()->json([
                'message' => 'Profile retrieved successfully',
                'profile' => $profileData,
                'availabilities' => $availabilities
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Error fetching vendor profile', [
                'vendor_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Error retrieving profile: ' . $e->getMessage(),
                'profile' => null,
                'availabilities' => []
            ], 500);
        }
    }

    /**
     * Create or update vendor profile
     */
    public function updateOrCreate(Request $request)
    {
        try {
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
            if (!$vendor) {
                return response()->json(['message' => 'Vendor not authenticated'], 401);
            }

            $data = $request->except(['profile_picture', 'license_document', 'vehicle_rc_document', 'insurance_document', 'citizenship_document', 'pan_document']);

            // Convert service_radius from kilometers to meters before saving
            // Android sends radius in kilometers, but database stores in meters
            if (isset($data['service_radius'])) {
                $serviceRadiusKm = (int) $data['service_radius'];
                $data['service_radius'] = $serviceRadiusKm * 1000; // Convert kilometers to meters
            } else {
                // Default to 50km (50000 meters) if not provided
                $data['service_radius'] = 50000;
            }

            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                try {
                    $data['profile_picture'] = $this->uploadFile($request->file('profile_picture'), 'profiles/vendors', 'vendor_' . $vendor->id . '_profile');
                    
                    if ($vendor->profile && $vendor->profile->profile_picture) {
                        Storage::disk('public')->delete($vendor->profile->profile_picture);
                    }
                } catch (\Exception $e) {
                    \Log::error('Error uploading profile picture', [
                        'vendor_id' => $vendor->id,
                        'error' => $e->getMessage()
                    ]);
                    // Continue without profile picture if upload fails
                }
            }

            $profile = $vendor->profile()->updateOrCreate(
                ['vendor_id' => $vendor->id],
                $data
            );

        // Include vendor name and created_at in the response
        $profileData = $profile->toArray();
        $profileData['name'] = $vendor->name;
        
        // Handle created_at safely (HasBSTimestamps trait may return string)
        try {
            // Get raw created_at from database (bypassing the trait accessor)
            $rawCreatedAt = $profile->getOriginal('created_at') ?? $profile->getAttributes()['created_at'] ?? null;
            
            if ($rawCreatedAt) {
                try {
                    // Try to parse and format as ISO 8601
                    if ($rawCreatedAt instanceof \Carbon\Carbon) {
                        $profileData['created_at'] = $rawCreatedAt->toIso8601String();
                    } elseif (is_string($rawCreatedAt)) {
                        $carbonDate = \Carbon\Carbon::parse($rawCreatedAt);
                        $profileData['created_at'] = $carbonDate->toIso8601String();
                    } else {
                        $profileData['created_at'] = (string) $rawCreatedAt;
                    }
                } catch (\Exception $e) {
                    // If parsing fails, try simple format
                    try {
                        if ($rawCreatedAt instanceof \Carbon\Carbon) {
                            $profileData['created_at'] = $rawCreatedAt->format('Y-m-d\TH:i:s\Z');
                        } elseif (is_string($rawCreatedAt)) {
                            $carbonDate = \Carbon\Carbon::parse($rawCreatedAt);
                            $profileData['created_at'] = $carbonDate->format('Y-m-d\TH:i:s\Z');
                        } else {
                            $profileData['created_at'] = (string) $rawCreatedAt;
                        }
                    } catch (\Exception $e2) {
                        // If all else fails, use the raw value
                        $profileData['created_at'] = is_string($rawCreatedAt) ? $rawCreatedAt : (string) $rawCreatedAt;
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Error formatting created_at in updateOrCreate', [
                'vendor_id' => $vendor->id,
                'error' => $e->getMessage()
            ]);
            // Don't include created_at if we can't format it
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => $profileData,
            'availabilities' => $vendor->availabilities ?? []
        ]);
        } catch (\Exception $e) {
            \Log::error('Error updating vendor profile', [
                'vendor_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Error updating profile: ' . $e->getMessage(),
                'profile' => null,
                'availabilities' => []
            ], 500);
        }
    }

    /**
     * Upload single document
     */
    public function uploadDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_type' => 'required|string|in:license,vehicle_rc,insurance,citizenship,pan',
            'document' => 'required|file|mimes:pdf,jpeg,png,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $vendor = $request->user();
        $documentType = $request->input('document_type');
        
        // Map document type to database field
        $documentFieldMap = [
            'license' => 'license_document',
            'vehicle_rc' => 'vehicle_rc_document',
            'insurance' => 'insurance_document',
            'citizenship' => 'citizenship_document',
            'pan' => 'pan_document',
        ];

        if (!isset($documentFieldMap[$documentType])) {
            return response()->json([
                'errors' => ['document_type' => ['Invalid document type']]
            ], 422);
        }

        $fieldName = $documentFieldMap[$documentType];
        
        // Upload the document
        $path = $this->uploadFile(
            $request->file('document'),
            'documents/vendors',
            'vendor_' . $vendor->id . '_' . $documentType
        );

        // Delete old document if exists
        if ($vendor->profile && $vendor->profile->{$fieldName}) {
            Storage::disk('public')->delete($vendor->profile->{$fieldName});
        }

        // Update profile with new document
        $profile = $vendor->profile()->updateOrCreate(
            ['vendor_id' => $vendor->id],
            [$fieldName => $path]
        );

        return response()->json([
            'message' => 'Document uploaded successfully',
            'document_type' => $documentType,
            'document_path' => $path,
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
            'service_radius' => 'nullable|integer|min:1|max:50000', // Optional, defaults to 50km
            'service_address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $vendor = $request->user();
        
        // Default service radius to 50km if not provided
        // Android sends radius in kilometers, but database stores in meters
        $serviceRadiusKm = $request->input('service_radius', 50);
        $serviceRadiusMeters = $serviceRadiusKm * 1000; // Convert kilometers to meters
        
        $profile = $vendor->profile()->updateOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'service_latitude' => $request->service_latitude,
                'service_longitude' => $request->service_longitude,
                'service_radius' => $serviceRadiusMeters, // Store in meters
                'service_address' => $request->input('service_address'),
            ]
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

