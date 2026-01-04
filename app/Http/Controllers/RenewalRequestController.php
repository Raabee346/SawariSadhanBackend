<?php

namespace App\Http\Controllers;

use App\Models\RenewalRequest;
use App\Models\Vehicle;
use App\Services\FCMNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class RenewalRequestController extends Controller
{
    protected $fcmService;

    public function __construct(FCMNotificationService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Create a new renewal request (from payment completion)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|exists:payments,id',
            'pickup_address' => 'required|string|max:500',
            'pickup_latitude' => 'required|numeric|between:-90,90',
            'pickup_longitude' => 'required|numeric|between:-180,180',
            'dropoff_address' => 'nullable|string|max:500',
            'dropoff_latitude' => 'nullable|numeric|between:-90,90',
            'dropoff_longitude' => 'nullable|numeric|between:-180,180',
            'pickup_date' => 'required|date_format:Y-m-d', // Accept YYYY-MM-DD format
            'pickup_time_slot' => 'required|string|max:255',
            'has_insurance' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            Log::error('Renewal request validation failed', [
                'user_id' => $request->user()->id,
                'errors' => $validator->errors()->toArray(),
                'request_data' => [
                    'payment_id' => $request->payment_id,
                    'pickup_address' => substr($request->pickup_address ?? '', 0, 50),
                    'pickup_latitude' => $request->pickup_latitude,
                    'pickup_longitude' => $request->pickup_longitude,
                    'pickup_date' => $request->pickup_date,
                    'pickup_time_slot' => $request->pickup_time_slot,
                ],
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find payment with fresh data from database
        $payment = \App\Models\Payment::where('user_id', $request->user()->id)
            ->where('id', $request->payment_id)
            ->with(['vehicle', 'fiscalYear'])
            ->first();

        if (!$payment) {
            Log::error('Payment not found for renewal request', [
                'user_id' => $request->user()->id,
                'payment_id' => $request->payment_id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Payment not found or does not belong to you',
            ], 404);
        }

        // Refresh payment to ensure we have latest status from database
        try {
            $payment->refresh();
        } catch (\Exception $e) {
            Log::error('Failed to refresh payment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            // Continue anyway - payment object should be valid
        }
        
        Log::info('Payment found for renewal request', [
            'payment_id' => $payment->id,
            'payment_status' => $payment->payment_status,
            'payment_method' => $payment->payment_method,
            'vehicle_id' => $payment->vehicle_id,
            'fiscal_year_id' => $payment->fiscal_year_id,
        ]);

        if ($payment->payment_status !== 'completed') {
            Log::warning('Payment not completed when creating renewal request', [
                'payment_id' => $payment->id,
                'payment_status' => $payment->payment_status,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Payment must be completed before creating renewal request. Current status: ' . $payment->payment_status,
            ], 400);
        }
        
        // Validate payment has required relationships
        if (!$payment->vehicle) {
            Log::error('Payment missing vehicle', ['payment_id' => $payment->id]);
            return response()->json([
                'success' => false,
                'message' => 'Payment is missing vehicle information',
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Log payment details for debugging
            Log::info('Creating renewal request', [
                'user_id' => $request->user()->id,
                'payment_id' => $payment->id,
                'payment_status' => $payment->payment_status,
                'vehicle_id' => $payment->vehicle_id,
                'fiscal_year_id' => $payment->fiscal_year_id,
                'pickup_date' => $request->pickup_date,
                'pickup_address' => $request->pickup_address,
            ]);
            
            // Validate that payment has all required fields
            if (!$payment->vehicle_id) {
                throw new \Exception('Payment does not have a vehicle_id');
            }
            
            if (!$payment->fiscal_year_id) {
                throw new \Exception('Payment does not have a fiscal_year_id');
            }
            
            // Verify vehicle exists
            $vehicle = Vehicle::find($payment->vehicle_id);
            if (!$vehicle) {
                throw new \Exception('Vehicle not found with ID: ' . $payment->vehicle_id);
            }
            
            // Verify fiscal year exists
            $fiscalYear = \App\Models\FiscalYear::find($payment->fiscal_year_id);
            if (!$fiscalYear) {
                throw new \Exception('Fiscal year not found with ID: ' . $payment->fiscal_year_id);
            }
            
            // Ensure all numeric fields are not null
            $taxAmount = $payment->tax_amount ?? 0;
            $renewalFee = $payment->renewal_fee ?? 0;
            $penaltyAmount = $payment->penalty_amount ?? 0;
            $insuranceAmount = $payment->insurance_amount ?? 0;
            $totalAmount = $payment->total_amount ?? 0;
            $serviceFee = 500; // Default service fee
            $vatAmount = ($totalAmount + $serviceFee) * 0.13; // 13% VAT
            
            // Validate and sanitize payment method
            $paymentMethod = $payment->payment_method;
            $allowedPaymentMethods = ['khalti', 'cash_on_delivery', 'esewa', 'ime_pay'];
            if ($paymentMethod && !in_array($paymentMethod, $allowedPaymentMethods)) {
                // If payment method doesn't match enum, default to cash_on_delivery
                $paymentMethod = 'cash_on_delivery';
                Log::warning('Invalid payment method in payment, defaulting to cash_on_delivery', [
                    'payment_id' => $payment->id,
                    'original_method' => $payment->payment_method
                ]);
            }
            
            // Validate pickup coordinates
            if (!is_numeric($request->pickup_latitude) || !is_numeric($request->pickup_longitude)) {
                throw new \Exception('Invalid pickup coordinates');
            }
            
            // Validate pickup date format - try multiple formats
            $pickupDate = null;
            try {
                // First try Y-m-d format (expected format)
                $pickupDate = \Carbon\Carbon::createFromFormat('Y-m-d', $request->pickup_date);
            } catch (\Exception $e) {
                try {
                    // If that fails, try Carbon::parse which is more lenient
                    $pickupDate = \Carbon\Carbon::parse($request->pickup_date);
                } catch (\Exception $e2) {
                    Log::error('Pickup date parsing failed', [
                        'pickup_date' => $request->pickup_date,
                        'error1' => $e->getMessage(),
                        'error2' => $e2->getMessage(),
                    ]);
                    throw new \Exception('Invalid pickup date format: ' . $request->pickup_date . '. Expected YYYY-MM-DD format.');
                }
            }
            
            if (!$pickupDate || !$pickupDate->isValid()) {
                throw new \Exception('Invalid pickup date: ' . $request->pickup_date);
            }
            
            // Get user phone number from profile
            $userProfile = $request->user()->profile;
            $userPhoneNumber = $userProfile ? $userProfile->phone_number : null;
            
            // Prepare data array for creation
            $renewalRequestData = [
                'user_id' => $request->user()->id,
                'user_phone_number' => $userPhoneNumber,
                'vehicle_id' => $payment->vehicle_id,
                'payment_id' => $payment->id,
                'fiscal_year_id' => $payment->fiscal_year_id,
                'vendor_id' => null, // Explicitly set to null (no vendor assigned yet)
                'service_type' => 'bluebook_renewal',
                'status' => 'pending',
                'pickup_address' => $request->pickup_address,
                'pickup_latitude' => (float) $request->pickup_latitude,
                'pickup_longitude' => (float) $request->pickup_longitude,
                'dropoff_address' => $request->input('dropoff_address'),
                'dropoff_latitude' => $request->has('dropoff_latitude') && $request->dropoff_latitude !== null ? (float) $request->dropoff_latitude : null,
                'dropoff_longitude' => $request->has('dropoff_longitude') && $request->dropoff_longitude !== null ? (float) $request->dropoff_longitude : null,
                'pickup_date' => $pickupDate->format('Y-m-d'),
                'pickup_time_slot' => $request->pickup_time_slot,
                'has_insurance' => $request->input('has_insurance', true) ? true : false,
                'tax_amount' => (float) $taxAmount,
                'renewal_fee' => (float) $renewalFee,
                'penalty_amount' => (float) $penaltyAmount,
                'insurance_amount' => (float) $insuranceAmount,
                'service_fee' => (float) $serviceFee,
                'vat_amount' => (float) $vatAmount,
                'total_amount' => (float) $totalAmount,
                'payment_status' => 'completed',
            ];
            
            // Only add payment_method if it's not null (enum allows null)
            if ($paymentMethod !== null) {
                $renewalRequestData['payment_method'] = $paymentMethod;
            }
            
            // Log the data being created (without sensitive info)
            $logData = $renewalRequestData;
            Log::info('Creating renewal request with data', [
                'user_id' => $logData['user_id'],
                'vehicle_id' => $logData['vehicle_id'],
                'payment_id' => $logData['payment_id'],
                'fiscal_year_id' => $logData['fiscal_year_id'],
                'pickup_address' => substr($logData['pickup_address'], 0, 50) . '...',
                'pickup_date' => $logData['pickup_date'],
            ]);
            
            try {
                $renewalRequest = RenewalRequest::create($renewalRequestData);
                Log::info('Renewal request created successfully', ['id' => $renewalRequest->id]);
            } catch (\Illuminate\Database\QueryException $e) {
                Log::error('Database error creating renewal request', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'sql' => $e->getSql(),
                    'bindings' => $e->getBindings(),
                ]);
                throw new \Exception('Database error: ' . $e->getMessage() . ' (Code: ' . $e->getCode() . ')');
            } catch (\Exception $e) {
                Log::error('Error creating renewal request', [
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            // Commit transaction first - don't let relationship loading fail the transaction
            DB::commit();
            
            Log::info('Transaction committed, renewal request ID: ' . $renewalRequest->id);
            
            // Refresh the model to ensure casts are applied (especially for pickup_date)
            $renewalRequest->refresh();
            
            // Load all relationships for the response (after commit)
            // Load relationships one by one to avoid failing if one fails
            try {
                $renewalRequest->load('vehicle');
            } catch (\Exception $e) {
                Log::warning('Failed to load vehicle relationship: ' . $e->getMessage());
            }
            
            try {
                if ($renewalRequest->vehicle) {
                    $renewalRequest->vehicle->load('province');
                }
            } catch (\Exception $e) {
                Log::warning('Failed to load vehicle province: ' . $e->getMessage());
            }
            
            try {
                $renewalRequest->load('payment');
            } catch (\Exception $e) {
                Log::warning('Failed to load payment relationship: ' . $e->getMessage());
            }
            
            try {
                $renewalRequest->load('user');
            } catch (\Exception $e) {
                Log::warning('Failed to load user relationship: ' . $e->getMessage());
            }
            
            try {
                $renewalRequest->load('fiscalYear');
            } catch (\Exception $e) {
                Log::warning('Failed to load fiscalYear relationship: ' . $e->getMessage());
            }
            
            // Don't load vendor relationship if vendor_id is null
            // This avoids foreign key constraint issues if vendor table structure is different

            // Send FCM notifications to online vendors (after commit to avoid blocking transaction)
            try {
                $this->fcmService->sendNewRenewalRequest($renewalRequest);
            } catch (\Exception $e) {
                // Log FCM error but don't fail the request creation
                Log::error('FCM notification failed: ' . $e->getMessage(), [
                    'renewal_request_id' => $renewalRequest->id,
                    'error' => $e->getTraceAsString()
                ]);
            }

            Log::info('Returning success response for renewal request', ['id' => $renewalRequest->id]);
            
            // Return response - serialize model properly
            // Laravel will automatically serialize relationships if loaded
            try {
                return response()->json([
                    'success' => true,
                    'message' => 'Renewal request created successfully',
                    'data' => $renewalRequest,
                ], 201);
            } catch (\Exception $e) {
                // If serialization fails, return basic data without relationships
                Log::error('Failed to serialize renewal request response', [
                    'renewal_request_id' => $renewalRequest->id,
                    'error' => $e->getMessage()
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Renewal request created successfully',
                    'data' => [
                        'id' => $renewalRequest->id,
                        'user_id' => $renewalRequest->user_id,
                        'vehicle_id' => $renewalRequest->vehicle_id,
                        'payment_id' => $renewalRequest->payment_id,
                        'status' => $renewalRequest->status,
                        'pickup_address' => $renewalRequest->pickup_address,
                        'pickup_date' => $renewalRequest->pickup_date,
                        'pickup_time_slot' => $renewalRequest->pickup_time_slot,
                    ],
                ], 201);
            }
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            $errorMessage = 'Database error: ' . $e->getMessage();
            if ($e->getCode() == 23000) { // Integrity constraint violation
                $errorMessage = 'Database constraint violation. Please check your data.';
            }
            Log::error('Renewal request creation failed - Database error', [
                'user_id' => $request->user()->id,
                'payment_id' => $request->payment_id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            $errorMessage = $e->getMessage();
            $statusCode = 500;
            
            // Provide more user-friendly error messages
            if (stripos($errorMessage, 'date') !== false) {
                $errorMessage = 'Invalid date format. Please use YYYY-MM-DD format.';
                $statusCode = 422;
            } elseif (stripos($errorMessage, 'database') !== false) {
                $errorMessage = 'Database error occurred. Please try again.';
                $statusCode = 500;
            } elseif (stripos($errorMessage, 'vehicle') !== false) {
                $errorMessage = 'Vehicle information is missing or invalid.';
                $statusCode = 400;
            } elseif (stripos($errorMessage, 'fiscal') !== false) {
                $errorMessage = 'Fiscal year information is missing or invalid.';
                $statusCode = 400;
            }
            
            Log::error('Renewal request creation failed', [
                'user_id' => $request->user()->id,
                'payment_id' => $request->payment_id,
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'request_data' => [
                    'pickup_date' => $request->pickup_date,
                    'pickup_address' => substr($request->pickup_address ?? '', 0, 50),
                    'pickup_latitude' => $request->pickup_latitude,
                    'pickup_longitude' => $request->pickup_longitude,
                ],
            ]);
            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], $statusCode);
        }
    }

    /**
     * Get user's renewal requests
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        
        $query = RenewalRequest::where('user_id', $request->user()->id)
            ->with(['vehicle.province', 'payment', 'vendor', 'fiscalYear'])
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $requests = $query->get();

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Get in-progress renewal requests for user
     * Includes pending, assigned, and in_progress statuses
     */
    public function getInProgress(Request $request)
    {
        $requests = RenewalRequest::where('user_id', $request->user()->id)
            ->whereIn('status', ['pending', 'assigned', 'in_progress'])
            ->whereNull('delivered_at') // Exclude delivered/completed requests
            ->whereNull('completed_at') // Exclude completed requests
            ->with(['vehicle.province', 'vendor.profile', 'fiscalYear'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Get available renewal requests for vendors
     * Only shows pending requests that haven't been accepted by any vendor
     * Filters by vendor's service area radius
     */
    public function getAvailable(Request $request)
    {
        $vendor = $request->user();
        
        // Load profile with service area information - refresh to get latest from database
        // Use fresh() to ensure we get the latest data, especially after login
        $profile = \App\Models\VendorProfile::where('vendor_id', $vendor->id)->first();
        
        // If profile doesn't exist, log warning but continue (will show all requests)
        if (!$profile) {
            Log::warning('Vendor profile not found, will show all requests', [
                'vendor_id' => $vendor->id,
            ]);
        } else {
            // Refresh profile to ensure we have latest data
            $profile->refresh();
        }
        
        Log::info('Vendor fetching available renewal requests', [
            'vendor_id' => $vendor->id,
            'vendor_type' => get_class($vendor),
            'has_profile' => $profile !== null,
            'has_service_area' => $profile && $profile->service_latitude && $profile->service_longitude,
            'service_latitude' => $profile ? $profile->service_latitude : null,
            'service_longitude' => $profile ? $profile->service_longitude : null,
            'service_radius' => $profile ? $profile->service_radius : null,
        ]);
        
        // Base query: pending requests without vendor assigned
        // Only show requests that are pending and haven't been accepted by any vendor
        $query = RenewalRequest::where('status', 'pending')
            ->whereNull('vendor_id'); // Only show requests not yet accepted by any vendor
        
        // Log all pending requests for debugging
        $allPendingRequests = RenewalRequest::where('status', 'pending')
            ->whereNull('vendor_id')
            ->get();
        
        Log::info('All pending renewal requests (before filtering)', [
            'vendor_id' => $vendor->id,
            'total_pending' => $allPendingRequests->count(),
            'request_ids' => $allPendingRequests->pluck('id')->toArray(),
            'requests_with_location' => $allPendingRequests->filter(function($req) {
                return $req->pickup_latitude !== null && $req->pickup_longitude !== null;
            })->count(),
            'requests_without_location' => $allPendingRequests->filter(function($req) {
                return $req->pickup_latitude === null || $req->pickup_longitude === null;
            })->count(),
        ]);
        
        // Get all pending requests with location data first
        // Exclude requests that this vendor has declined
        try {
            $declinedRequestIds = DB::table('renewal_request_declined_vendors')
                ->where('vendor_id', $vendor->id)
                ->pluck('renewal_request_id')
                ->toArray();
        } catch (\Exception $e) {
            // If table doesn't exist yet (migration not run), use empty array
            Log::warning('Could not fetch declined requests (table may not exist): ' . $e->getMessage());
            $declinedRequestIds = [];
        }
        
        $allRequestsWithLocation = $query->whereNotNull('pickup_latitude')
            ->whereNotNull('pickup_longitude')
            ->when(!empty($declinedRequestIds), function($q) use ($declinedRequestIds) {
                return $q->whereNotIn('id', $declinedRequestIds); // Exclude declined requests
            })
            ->with(['vehicle.province', 'user', 'fiscalYear'])
            ->get();
        
        Log::info('Total pending requests with location found', [
            'vendor_id' => $vendor->id,
            'total_count' => $allRequestsWithLocation->count(),
            'request_details' => $allRequestsWithLocation->map(function($req) {
                return [
                    'id' => $req->id,
                    'pickup_lat' => $req->pickup_latitude,
                    'pickup_lng' => $req->pickup_longitude,
                    'status' => $req->status,
                    'vendor_id' => $req->vendor_id,
                ];
            })->toArray(),
        ]);
        
        // Filter by vendor's service area radius if set
        if ($profile && $profile->service_latitude && $profile->service_longitude) {
            $vendorLat = (float) $profile->service_latitude;
            $vendorLng = (float) $profile->service_longitude;
            // service_radius is stored in meters, convert to kilometers for comparison
            $radiusMeters = $profile->service_radius ?? 50000; // Default 50000 meters = 50km
            $radius = $radiusMeters / 1000; // Convert meters to kilometers
            
            // Ensure radius is a valid number
            if (!is_numeric($radius) || $radius <= 0) {
                $radius = 50; // Default to 50km if invalid
            }
            
            Log::info('Filtering requests by service area', [
                'vendor_id' => $vendor->id,
                'vendor_lat' => $vendorLat,
                'vendor_lng' => $vendorLng,
                'service_radius_meters' => $radiusMeters,
                'service_radius_km' => $radius,
            ]);
            
            // Filter by distance - only show requests within service radius
            $requests = $allRequestsWithLocation->filter(function ($renewalRequest) use ($vendorLat, $vendorLng, $radius, $vendor) {
                $requestLat = (float) $renewalRequest->pickup_latitude;
                $requestLng = (float) $renewalRequest->pickup_longitude;
                
                // Skip requests with invalid coordinates (0,0 or null)
                if ($requestLat == 0 || $requestLng == 0 || abs($requestLat) > 90 || abs($requestLng) > 180) {
                    Log::warning('Skipping request with invalid coordinates', [
                        'renewal_request_id' => $renewalRequest->id,
                        'request_lat' => $requestLat,
                        'request_lng' => $requestLng,
                    ]);
                    return false;
                }
                
                $distance = $this->calculateDistance(
                    $vendorLat,
                    $vendorLng,
                    $requestLat,
                    $requestLng
                );
                
                $withinRadius = $distance <= $radius;
                
                // Log each request's distance for debugging
                Log::info('Checking renewal request distance', [
                    'vendor_id' => $vendor->id,
                    'renewal_request_id' => $renewalRequest->id,
                    'request_lat' => $requestLat,
                    'request_lng' => $requestLng,
                    'vendor_lat' => $vendorLat,
                    'vendor_lng' => $vendorLng,
                    'distance_km' => round($distance, 2),
                    'service_radius_km' => $radius,
                    'within_radius' => $withinRadius,
                ]);
                
                return $withinRadius;
            })->values();
            
            Log::info('Available renewal requests found (filtered by service area)', [
                'vendor_id' => $vendor->id,
                'total_pending_with_location' => $allRequestsWithLocation->count(),
                'within_radius' => $requests->count(),
                'service_radius_meters' => $radiusMeters,
                'service_radius_km' => $radius,
                'vendor_location' => ['lat' => $vendorLat, 'lng' => $vendorLng],
                'request_ids' => $requests->pluck('id')->toArray(),
            ]);
        } else {
            // If vendor doesn't have service area set, show all requests with location
            Log::warning('Vendor does not have service area set, showing all requests with location', [
                'vendor_id' => $vendor->id,
                'profile_exists' => $profile !== null,
                'has_lat' => $profile && $profile->service_latitude !== null,
                'has_lng' => $profile && $profile->service_longitude !== null,
            ]);
            
            // Use the requests we already fetched
            $requests = $allRequestsWithLocation->values();
            
            Log::info('Available renewal requests (no service area filter)', [
                'vendor_id' => $vendor->id,
                'total_count' => $requests->count(),
                'request_ids' => $requests->pluck('id')->toArray(),
            ]);
        }

        // Log final response
        Log::info('Returning available renewal requests to vendor', [
            'vendor_id' => $vendor->id,
            'request_count' => $requests->count(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }
    
    /**
     * Calculate distance between two points using Haversine formula
     * Returns distance in kilometers
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Earth's radius in kilometers
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }

    /**
     * Get vendor's assigned requests
     */
    public function getVendorRequests(Request $request)
    {
        $status = $request->query('status');
        
        $query = RenewalRequest::where('vendor_id', $request->user()->id)
            ->with(['vehicle.province', 'user', 'payment', 'fiscalYear'])
            ->latest();

        if ($status) {
            // Support comma-separated statuses for filtering
            $statuses = explode(',', $status);
            $statuses = array_map('trim', $statuses);
            $query->whereIn('status', $statuses);
        } else {
            // Default: exclude completed and cancelled requests
            $query->whereNotIn('status', ['completed', 'cancelled']);
        }

        $requests = $query->get();

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Accept a renewal request (vendor)
     */
    public function accept(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $renewalRequest = RenewalRequest::where('status', 'pending')
                ->findOrFail($id);

            // Check if vendor is online and available
            // $request->user() returns Vendor instance when authenticated as vendor
            $vendor = $request->user();
            $vendorProfile = $vendor->profile;
            if (!$vendorProfile || !$vendorProfile->is_online || !$vendorProfile->is_available) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must be online and available to accept requests',
                ], 403);
            }

            // Prepare update data
            $updateData = [
                'status' => 'assigned',
                'vendor_id' => $request->user()->id,
                'assigned_at' => now(),
            ];

            // Update drop-off location if provided
            if ($request->has('dropoff_address') && $request->dropoff_address !== null) {
                $updateData['dropoff_address'] = $request->dropoff_address;
            }
            if ($request->has('dropoff_latitude') && $request->dropoff_latitude !== null) {
                $updateData['dropoff_latitude'] = (float) $request->dropoff_latitude;
            }
            if ($request->has('dropoff_longitude') && $request->dropoff_longitude !== null) {
                $updateData['dropoff_longitude'] = (float) $request->dropoff_longitude;
            }

            // Assign request to vendor
            $renewalRequest->update($updateData);
            
            // Refresh the model to ensure we have the latest data from database
            $renewalRequest->refresh();

            // Send silent refresh notification to all vendors (no popup, just triggers refresh)
            // This ensures accepted requests disappear from all rider dashboards immediately
            try {
                $this->fcmService->sendToAllVendors(
                    '', // Empty title = silent notification
                    '', // Empty body = silent notification
                    [
                        'type' => 'refresh_available_requests',
                        'action' => 'refresh',
                        'renewal_request_id' => (string) $renewalRequest->id,
                    ]
                );
            } catch (\Exception $e) {
                Log::warning('Failed to send refresh notification to vendors', [
                    'error' => $e->getMessage(),
                    'renewal_request_id' => $renewalRequest->id,
                ]);
                // Don't fail the request acceptance if notification fails
            }
            
            // Notify user that request was accepted
            try {
                $this->fcmService->notifyUserRequestUpdate($renewalRequest, 'assigned');
            } catch (\Exception $e) {
                Log::warning('Failed to notify user about request acceptance', [
                    'error' => $e->getMessage(),
                    'renewal_request_id' => $renewalRequest->id,
                ]);
                // Don't fail the request acceptance if notification fails
            }

            // Load all relationships for the response
            $renewalRequest->load(['vehicle.province', 'payment', 'user.profile', 'vendor.profile', 'fiscalYear']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Request accepted successfully',
                'data' => $renewalRequest,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Decline a renewal request (vendor declines to accept)
     * This hides the request from this vendor but keeps it available for others
     */
    public function decline(Request $request, $id)
    {
        try {
            $renewalRequest = RenewalRequest::where('status', 'pending')
                ->whereNull('vendor_id')
                ->findOrFail($id);

            $vendor = $request->user();

            // Check if vendor has already declined this request
            $alreadyDeclined = \DB::table('renewal_request_declined_vendors')
                ->where('renewal_request_id', $id)
                ->where('vendor_id', $vendor->id)
                ->exists();

            if ($alreadyDeclined) {
                return response()->json([
                    'success' => true,
                    'message' => 'Request already declined',
                ]);
            }

            // Record the decline
            \DB::table('renewal_request_declined_vendors')->insert([
                'renewal_request_id' => $id,
                'vendor_id' => $vendor->id,
                'declined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('Vendor declined renewal request', [
                'vendor_id' => $vendor->id,
                'renewal_request_id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Request declined successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error declining renewal request', [
                'error' => $e->getMessage(),
                'renewal_request_id' => $id,
                'vendor_id' => $request->user()->id ?? null,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to decline request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update workflow status (en_route, document_picked_up, at_dotm, delivered)
     */
    public function updateWorkflowStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'workflow_status' => 'required|in:en_route,arrived,document_picked_up,at_dotm,processing_complete,en_route_dropoff,arrived_dropoff,delivered',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $renewalRequest = RenewalRequest::findOrFail($id);

        // Check permissions - only vendor who accepted the request can update workflow
        $vendor = $request->user();
        
        // Use == for type coercion (vendor_id might be int, vendor->id might be int or string)
        if ($renewalRequest->vendor_id != $vendor->id) {
            Log::warning('Unauthorized workflow status update attempt', [
                'renewal_request_id' => $id,
                'request_vendor_id' => $renewalRequest->vendor_id,
                'request_vendor_id_type' => gettype($renewalRequest->vendor_id),
                'current_vendor_id' => $vendor->id,
                'current_vendor_id_type' => gettype($vendor->id),
                'vendor_type' => get_class($vendor),
                'workflow_status' => $request->workflow_status,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only update requests you have accepted.',
            ], 403);
        }
        
        Log::info('Workflow status update authorized', [
            'renewal_request_id' => $id,
            'vendor_id' => $vendor->id,
            'workflow_status' => $request->workflow_status,
        ]);

        DB::beginTransaction();
        try {
            $updateData = [];
            $statusMessage = '';
            $shouldNotify = true; // Default to true for most statuses

            switch ($request->workflow_status) {
                case 'en_route':
                    // Only update if en_route_at is not already set (to prevent duplicate notifications)
                    $shouldNotify = $renewalRequest->en_route_at === null;
                    $updateData['en_route_at'] = now();
                    $updateData['status'] = 'in_progress'; // Change status to in_progress when en route
                    $updateData['started_at'] = now(); // Also set started_at
                    $statusMessage = 'Rider is en route to pickup location';
                    break;
                case 'arrived':
                    // Determine if this is pickup arrival or dropoff arrival
                    // If document_picked_up_at is set, this is dropoff arrival
                    // Otherwise, it's pickup arrival
                    $isDropoffArrival = $renewalRequest->document_picked_up_at !== null;
                    
                    if ($isDropoffArrival) {
                        // This is dropoff arrival - treat as arrived_dropoff
                        // Don't process here, let arrived_dropoff case handle it
                        // But we need to prevent duplicate notification
                        $statusMessage = 'Rider has arrived for document drop-off';
                        $shouldNotifyArrivedDropoff = !$renewalRequest->delivered_at;
                        // Store flag to use in notification logic
                        $isArrivedDropoff = true;
                    } else {
                        // This is pickup arrival
                        $statusMessage = 'Rider has arrived at pickup location';
                        // Only notify if not already notified (prevent duplicates)
                        // Check if arrived_at is already set to prevent duplicate notifications
                        $shouldNotifyArrived = $renewalRequest->arrived_at === null;
                        if ($shouldNotifyArrived) {
                            $updateData['arrived_at'] = now();
                        }
                        $isArrivedDropoff = false;
                    }
                    break;
                case 'document_picked_up':
                    // Only update if not already set (prevent duplicate notifications)
                    $shouldNotifyDocumentPickup = $renewalRequest->document_picked_up_at === null;
                    if ($shouldNotifyDocumentPickup) {
                        $updateData['document_picked_up_at'] = now();
                    }
                    $statusMessage = 'Documents have been picked up';
                    break;
                case 'at_dotm':
                    $updateData['at_dotm_at'] = now();
                    $statusMessage = 'Rider is at DoTM office';
                    break;
                case 'processing_complete':
                    // Processing at Yatayat is complete - set timestamp and notify user
                    // Only update if not already set (prevent duplicate notifications)
                    $shouldNotifyProcessingComplete = $renewalRequest->processing_complete_at === null;
                    if ($shouldNotifyProcessingComplete) {
                        $updateData['processing_complete_at'] = now();
                    }
                    $statusMessage = 'Processing at Yatayat is complete';
                    break;
                case 'en_route_dropoff':
                    // Rider is en route for dropoff - set timestamp and notify user
                    // Only update if not already set (prevent duplicate notifications)
                    $shouldNotifyEnRouteDropoff = $renewalRequest->en_route_dropoff_at === null;
                    if ($shouldNotifyEnRouteDropoff) {
                        $updateData['en_route_dropoff_at'] = now();
                    }
                    $statusMessage = 'Rider is en route for drop-off';
                    break;
                case 'arrived_dropoff':
                    // Rider has arrived for dropoff - notify user (only once)
                    // Check if already delivered to prevent duplicates
                    $shouldNotifyArrivedDropoff = !$renewalRequest->delivered_at; // Only notify if not already delivered
                    // No database update needed, just notification
                    $statusMessage = 'Rider has arrived for document drop-off';
                    break;
                case 'delivered':
                    $updateData['delivered_at'] = now();
                    $updateData['status'] = 'completed';
                    $updateData['completed_at'] = now();
                    $statusMessage = 'Documents have been delivered to client';
                    
                    // Update vehicle's last_renewed_date in BS format and expiry_date in AD format
                    try {
                        $vehicle = $renewalRequest->vehicle;
                        if ($vehicle) {
                            // Convert completion date (AD) to BS format
                            $nepaliDate = new \App\Services\NepaliDate();
                            $completionDateAD = now()->format('Y-m-d');
                            $completionDateBS = $nepaliDate->get_nepali_date(
                                (int) now()->format('Y'),
                                (int) now()->format('m'),
                                (int) now()->format('d')
                            );
                            $lastRenewedDateBS = sprintf('%04d-%02d-%02d', $completionDateBS['y'], $completionDateBS['m'], $completionDateBS['d']);
                            
                            // Calculate expiry date: last_renewed_date (AD) + 1 year
                            $lastRenewedDateAD = \Carbon\Carbon::createFromFormat('Y-m-d', $completionDateAD);
                            $expiryDateAD = $lastRenewedDateAD->copy()->addYear()->format('Y-m-d');
                            
                            $vehicle->update([
                                'last_renewed_date' => $lastRenewedDateBS,
                                'expiry_date' => $expiryDateAD,
                            ]);
                            
                            // Refresh vehicle to ensure latest data is available
                            $vehicle->refresh();
                            
                            // Clear vehicle from cache to ensure fresh data on next fetch
                            \Cache::forget('vehicle_' . $vehicle->id);
                            
                            Log::info('Vehicle dates updated after renewal completion', [
                                'vehicle_id' => $vehicle->id,
                                'last_renewed_date_bs' => $lastRenewedDateBS,
                                'expiry_date_ad' => $expiryDateAD,
                                'renewal_request_id' => $renewalRequest->id,
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to update vehicle dates after renewal completion', [
                            'renewal_request_id' => $renewalRequest->id,
                            'error' => $e->getMessage(),
                        ]);
                        // Don't fail the request if vehicle update fails
                    }
                    break;
            }

            if ($request->has('notes') && $request->notes) {
                $updateData['notes'] = $request->notes;
            }

            $renewalRequest->update($updateData);

            // Notify user about workflow status update
            // For en_route, only notify if it's the first time (en_route_at was null before)
            if ($request->workflow_status === 'en_route' && !$shouldNotify) {
                // Don't notify if en_route_at was already set (duplicate click)
                \Log::info('Skipping notification for en_route - already notified', [
                    'renewal_request_id' => $renewalRequest->id,
                ]);
            } else if ($request->workflow_status === 'arrived') {
                // Check if this is pickup or dropoff arrival based on document_picked_up_at
                $isDropoffArrival = isset($isArrivedDropoff) ? $isArrivedDropoff : ($renewalRequest->document_picked_up_at !== null);
                
                if ($isDropoffArrival) {
                    // This is dropoff arrival - send arrived_dropoff notification
                    if (isset($shouldNotifyArrivedDropoff) && $shouldNotifyArrivedDropoff) {
                        $renewalRequest->refresh();
                        $this->fcmService->notifyUserRequestUpdate($renewalRequest, 'arrived_dropoff');
                        Log::info('Sent arrived_dropoff notification (arrived status with documents picked up)', [
                            'renewal_request_id' => $renewalRequest->id,
                        ]);
                    } else {
                        \Log::info('Skipping notification for arrived (dropoff) - already delivered or notified', [
                            'renewal_request_id' => $renewalRequest->id,
                        ]);
                    }
                } else {
                    // This is pickup arrival - send arrived notification
                    // Only notify once - check if already notified (arrived_at was null before update)
                    if (isset($shouldNotifyArrived) && $shouldNotifyArrived && !$renewalRequest->document_picked_up_at) {
                        $renewalRequest->refresh();
                        $this->fcmService->notifyUserRequestUpdate($renewalRequest, 'arrived');
                        Log::info('Sent arrived notification (pickup arrival)', [
                            'renewal_request_id' => $renewalRequest->id,
                        ]);
                    } else {
                        \Log::info('Skipping notification for arrived (pickup) - already notified or documents picked up', [
                            'renewal_request_id' => $renewalRequest->id,
                            'arrived_at' => $renewalRequest->arrived_at,
                            'document_picked_up_at' => $renewalRequest->document_picked_up_at,
                        ]);
                    }
                }
            } else if ($request->workflow_status === 'processing_complete') {
                // Only notify if this is the first time (checked before update)
                if (isset($shouldNotifyProcessingComplete) && $shouldNotifyProcessingComplete) {
                    $renewalRequest->refresh();
                    $this->fcmService->notifyUserRequestUpdate($renewalRequest, $request->workflow_status);
                } else {
                    \Log::info('Skipping notification for processing_complete - already notified', [
                        'renewal_request_id' => $renewalRequest->id,
                    ]);
                }
            } else if ($request->workflow_status === 'en_route_dropoff') {
                // Only notify if this is the first time (checked before update)
                if (isset($shouldNotifyEnRouteDropoff) && $shouldNotifyEnRouteDropoff) {
                    $renewalRequest->refresh();
                    $this->fcmService->notifyUserRequestUpdate($renewalRequest, $request->workflow_status);
                } else {
                    \Log::info('Skipping notification for en_route_dropoff - already notified', [
                        'renewal_request_id' => $renewalRequest->id,
                    ]);
                }
            } else if ($request->workflow_status === 'arrived_dropoff') {
                // Only notify for dropoff arrival if not already delivered (prevent duplicates)
                if (isset($shouldNotifyArrivedDropoff) && $shouldNotifyArrivedDropoff) {
                    $renewalRequest->refresh();
                    $this->fcmService->notifyUserRequestUpdate($renewalRequest, $request->workflow_status);
                } else {
                    \Log::info('Skipping notification for arrived_dropoff - already delivered or notified', [
                        'renewal_request_id' => $renewalRequest->id,
                    ]);
                }
            } else if ($request->workflow_status === 'document_picked_up') {
                // Only notify if this is the first time (checked before update)
                // Note: $shouldNotifyDocumentPickup is set in the switch case before update
                if (isset($shouldNotifyDocumentPickup) && $shouldNotifyDocumentPickup) {
                    // Refresh the model to get updated data before notifying
                    $renewalRequest->refresh();
                    $this->fcmService->notifyUserRequestUpdate($renewalRequest, $request->workflow_status);
                } else {
                    \Log::info('Skipping notification for document_picked_up - already notified', [
                        'renewal_request_id' => $renewalRequest->id,
                    ]);
                }
            } else {
                // Notify for all other statuses or first-time en_route
                $this->fcmService->notifyUserRequestUpdate($renewalRequest, $request->workflow_status);
            }

            // Refresh the model to ensure we have latest data
            $renewalRequest->refresh();
            
            // If vehicle was updated (delivered status), refresh vehicle relationship to get latest data
            if ($request->workflow_status === 'delivered' && $renewalRequest->vehicle_id) {
                // Unset the existing vehicle relationship to force fresh load
                $renewalRequest->unsetRelation('vehicle');
                
                // Reload vehicle with fresh data from database
                $renewalRequest->load('vehicle.province');
                
                // Also refresh the vehicle model itself to ensure latest data
                if ($renewalRequest->vehicle) {
                    $renewalRequest->vehicle->refresh();
                }
            }
            
            // Load all relationships for the response
            $renewalRequest->load(['vehicle.province', 'payment', 'user.profile', 'vendor.profile', 'fiscalYear']);

            DB::commit();
            
            Log::info('Workflow status updated successfully', [
                'renewal_request_id' => $id,
                'vendor_id' => $vendor->id,
                'workflow_status' => $request->workflow_status,
                'status' => $renewalRequest->status,
                'en_route_at' => $renewalRequest->en_route_at,
            ]);

            return response()->json([
                'success' => true,
                'message' => $statusMessage,
                'data' => $renewalRequest,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating workflow status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'renewal_request_id' => $id,
                'vendor_id' => $vendor->id ?? null,
                'workflow_status' => $request->workflow_status ?? null,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update workflow status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload document pickup photo
     */
    public function uploadDocumentPhoto(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'document_photo' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $renewalRequest = RenewalRequest::findOrFail($id);

        // Check permissions
        $vendor = $request->user();
        // Use == for type coercion (vendor_id might be int, vendor->id might be int or string)
        if ($renewalRequest->vendor_id != $vendor->id) {
            Log::warning('Unauthorized document photo upload attempt', [
                'renewal_request_id' => $id,
                'request_vendor_id' => $renewalRequest->vendor_id,
                'current_vendor_id' => $vendor->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only upload photos for requests you have accepted.',
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Delete old photo if exists
            if ($renewalRequest->document_photo) {
                \Storage::disk('public')->delete($renewalRequest->document_photo);
            }

            // Upload new photo
            $file = $request->file('document_photo');
            $filename = 'document_pickup_' . $renewalRequest->id . '_' . time() . '.' . $file->extension();
            $path = $file->storeAs('renewal_requests/documents', $filename, 'public');

            // Update renewal request - only update document_photo
            // Do NOT set document_picked_up_at here - that should be done via updateWorkflowStatus endpoint
            // This prevents duplicate notifications when both endpoints are called
            $renewalRequest->update([
                'document_photo' => $path,
            ]);

            // Do NOT send notification here - updateWorkflowStatus endpoint will handle it
            // This prevents duplicate notifications

            $renewalRequest->load(['vehicle.province', 'payment', 'user.profile', 'vendor.profile', 'fiscalYear']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Document photo uploaded successfully',
                'data' => $renewalRequest,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error uploading document photo', [
                'error' => $e->getMessage(),
                'renewal_request_id' => $id,
                'vendor_id' => $vendor->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document photo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload signature photo for dropoff confirmation
     */
    public function uploadSignaturePhoto(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'signature_photo' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $renewalRequest = RenewalRequest::findOrFail($id);

        // Check permissions
        $vendor = $request->user();
        if ($renewalRequest->vendor_id !== $vendor->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You can only upload signatures for requests you have accepted.',
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Delete old signature if exists
            if ($renewalRequest->signature_photo) {
                \Storage::disk('public')->delete($renewalRequest->signature_photo);
            }

            // Upload new signature
            $file = $request->file('signature_photo');
            $filename = 'signature_' . $renewalRequest->id . '_' . time() . '.' . $file->extension();
            $path = $file->storeAs('renewal_requests/signatures', $filename, 'public');

            // Update renewal request
            $renewalRequest->update([
                'signature_photo' => $path,
            ]);

            $renewalRequest->load(['vehicle.province', 'payment', 'user.profile', 'vendor.profile', 'fiscalYear']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Signature uploaded successfully',
                'data' => $renewalRequest,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error uploading signature photo', [
                'error' => $e->getMessage(),
                'renewal_request_id' => $id,
                'vendor_id' => $vendor->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload signature photo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update request status
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:in_progress,completed,cancelled',
            'notes' => 'nullable|string',
            'cancellation_reason' => 'required_if:status,cancelled|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $renewalRequest = RenewalRequest::findOrFail($id);

        // Check permissions
        if ($request->user()->isVendor()) {
            // Vendor can only update their own assigned requests
            if ($renewalRequest->vendor_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }
        } else {
            // User can only cancel their own requests
            if ($renewalRequest->user_id !== $request->user()->id || $request->status !== 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }
        }

        DB::beginTransaction();
        try {
            $updateData = [
                'status' => $request->status,
                'notes' => $request->notes,
            ];

            if ($request->status === 'in_progress') {
                $updateData['started_at'] = now();
            } elseif ($request->status === 'completed') {
                $updateData['completed_at'] = now();
            } elseif ($request->status === 'cancelled') {
                $updateData['cancelled_at'] = now();
                $updateData['cancellation_reason'] = $request->cancellation_reason;
            }

            $renewalRequest->update($updateData);

            // Notify user about status update
            $this->fcmService->notifyUserRequestUpdate($renewalRequest, $request->status);

            $renewalRequest->load(['vehicle.province', 'payment', 'user', 'vendor.profile', 'fiscalYear']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Request status updated successfully',
                'data' => $renewalRequest,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update request status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific renewal request
     * Accessible to both users and vendors
     */
    public function show(Request $request, $id)
    {
        try {
            // Load relationships with error handling
            $renewalRequest = RenewalRequest::with([
                'vehicle.province', 
                'payment', 
                'user.profile',  // Load user profile to get phone number and profile picture
                'vendor.profile', 
                'fiscalYear'
            ])
                ->findOrFail($id);
            
            // Ensure user relationship is loaded (it should be, but double-check)
            if (!$renewalRequest->relationLoaded('user')) {
                $renewalRequest->load('user');
            }
            
            // Ensure user profile is loaded if user exists
            if ($renewalRequest->user && !$renewalRequest->user->relationLoaded('profile')) {
                $renewalRequest->user->load('profile');
            }

            $user = $request->user();
            
            // Check if user is a Vendor instance (more reliable than checking VendorProfile)
            $isVendorInstance = $user instanceof \App\Models\Vendor;
            $isUserInstance = $user instanceof \App\Models\User;
            
            // Also check VendorProfile as fallback (for cases where User model is used for vendors)
            $isVendorByProfile = false;
            if (!$isVendorInstance) {
                try {
                    $vendorProfile = \App\Models\VendorProfile::where('vendor_id', $user->id)->first();
                    $isVendorByProfile = $vendorProfile !== null;
                } catch (\Exception $e) {
                    \Log::warning('Error checking if user is vendor by profile', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $isVendor = $isVendorInstance || $isVendorByProfile;

            // Log permission check details
            \Log::info('Checking access for renewal request', [
                'request_id' => $id,
                'user_id' => $user->id,
                'user_type' => get_class($user),
                'is_vendor_instance' => $isVendorInstance,
                'is_vendor_by_profile' => $isVendorByProfile,
                'is_vendor' => $isVendor,
                'request_status' => $renewalRequest->status,
                'request_vendor_id' => $renewalRequest->vendor_id,
                'request_vendor_id_type' => gettype($renewalRequest->vendor_id),
                'user_id_type' => gettype($user->id),
                'request_payment_status' => $renewalRequest->payment_status,
                'request_user_id' => $renewalRequest->user_id,
            ]);

            // Check permissions
            $hasAccess = false;

            // First, check if user owns the request (users should always be able to view their own requests)
            if ($renewalRequest->user_id == $user->id) { // Use == for type coercion
                $hasAccess = true;
                \Log::info('User owns the request - access granted', [
                    'request_user_id' => $renewalRequest->user_id,
                    'current_user_id' => $user->id,
                ]);
            } else {
                // Check if vendor has accepted this request
                // Use == for type coercion (vendor_id might be int, user->id might be int or string)
                $vendorIdMatches = ($renewalRequest->vendor_id == $user->id);
                
                \Log::info('Checking vendor access', [
                    'vendor_id_matches' => $vendorIdMatches,
                    'request_vendor_id' => $renewalRequest->vendor_id,
                    'user_id' => $user->id,
                    'request_status' => $renewalRequest->status,
                ]);
                
                if ($vendorIdMatches) {
                    // Vendor has accepted this request - grant access regardless of status
                    $hasAccess = true;
                    \Log::info('Vendor has accepted this request - access granted', [
                        'vendor_id' => $user->id,
                        'request_vendor_id' => $renewalRequest->vendor_id,
                        'request_status' => $renewalRequest->status,
                    ]);
                } else if ($isVendor) {
                    // Vendor is viewing a pending unassigned request
                    $isPendingAndUnassigned = ($renewalRequest->status === 'pending' && $renewalRequest->vendor_id === null);
                    $hasAccess = $isPendingAndUnassigned;
                    
                    \Log::info('Vendor access check for pending request', [
                        'has_access' => $hasAccess,
                        'is_pending_and_unassigned' => $isPendingAndUnassigned,
                        'request_status' => $renewalRequest->status,
                        'request_vendor_id' => $renewalRequest->vendor_id,
                        'current_user_id' => $user->id,
                    ]);
                } else {
                    // User is not a vendor and doesn't own the request - deny access
                    \Log::warning('Access denied - user is not vendor and does not own request', [
                        'user_id' => $user->id,
                        'user_type' => get_class($user),
                        'request_id' => $id,
                        'request_status' => $renewalRequest->status,
                        'request_vendor_id' => $renewalRequest->vendor_id,
                        'request_user_id' => $renewalRequest->user_id,
                    ]);
                }
            }

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You do not have permission to view this request.',
                ], 403);
            }

            // Ensure all relationships are safely loaded before serialization
            // Handle cases where relationships might be null
            if ($renewalRequest->user && $renewalRequest->user->profile) {
                // Profile is already loaded, ensure it serializes correctly
                $renewalRequest->user->makeVisible(['profile']);
            }
            
            // Convert to array to ensure proper serialization
            // This helps catch any serialization issues before sending response
            try {
                $renewalRequestArray = $renewalRequest->toArray();
                
                return response()->json([
                    'success' => true,
                    'data' => $renewalRequestArray,
                ]);
            } catch (\Exception $e) {
                \Log::error('Error serializing renewal request', [
                    'id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // If serialization fails, try to return the model directly
                // Laravel will handle serialization automatically
                return response()->json([
                    'success' => true,
                    'data' => $renewalRequest,
                ]);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            \Log::error('Renewal request not found', [
                'id' => $id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Renewal request not found.',
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error fetching renewal request', [
                'id' => $id,
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching the renewal request. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Update FCM token
     * Works for both users and vendors
     */
    public function updateFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $userType = get_class($user);
        
        Log::info('Updating FCM token', [
            'user_id' => $user->id,
            'user_type' => $userType,
            'user_name' => $user->name ?? 'N/A',
            'fcm_token_preview' => substr($request->fcm_token, 0, 20) . '...',
            'fcm_token_length' => strlen($request->fcm_token),
        ]);

        try {
            // First, try using Eloquent update
            $user->fcm_token = $request->fcm_token;
            $saved = $user->save();
            
            if (!$saved) {
                Log::warning('Eloquent save() returned false, trying direct DB update', [
                    'user_id' => $user->id,
                    'user_type' => $userType,
                ]);
                
                // Fallback: Direct database update
                $tableName = $user->getTable();
                $updated = DB::table($tableName)
                    ->where('id', $user->id)
                    ->update(['fcm_token' => $request->fcm_token]);
                
                if ($updated === 0) {
                    Log::error('Direct DB update also failed - no rows affected', [
                        'user_id' => $user->id,
                        'user_type' => $userType,
                        'table' => $tableName,
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to update FCM token in database',
                    ], 500);
                }
                
                Log::info('FCM token updated via direct DB update', [
                    'user_id' => $user->id,
                    'user_type' => $userType,
                    'rows_affected' => $updated,
                ]);
            }
            
            // Refresh to verify token was saved
            $user->refresh();
            
            // Verify token was actually saved
            $tokenSaved = !empty($user->fcm_token) && $user->fcm_token === $request->fcm_token;
            
            Log::info('FCM token update result', [
                'user_id' => $user->id,
                'user_type' => $userType,
                'user_name' => $user->name ?? 'N/A',
                'token_saved' => $tokenSaved,
                'token_in_db' => !empty($user->fcm_token),
                'token_matches' => $user->fcm_token === $request->fcm_token,
                'db_token_preview' => $user->fcm_token ? substr($user->fcm_token, 0, 20) . '...' : 'null',
                'request_token_preview' => substr($request->fcm_token, 0, 20) . '...',
            ]);
            
            if (!$tokenSaved) {
                Log::error('FCM token was not saved correctly after update', [
                    'user_id' => $user->id,
                    'user_type' => $userType,
                    'requested_token_preview' => substr($request->fcm_token, 0, 20) . '...',
                    'saved_token_preview' => $user->fcm_token ? substr($user->fcm_token, 0, 20) . '...' : 'null',
                    'requested_token_length' => strlen($request->fcm_token),
                    'saved_token_length' => $user->fcm_token ? strlen($user->fcm_token) : 0,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'FCM token was not saved correctly',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Exception while updating FCM token', [
                'user_id' => $user->id ?? null,
                'user_type' => $userType ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error updating FCM token: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'FCM token updated successfully',
        ]);
    }
}