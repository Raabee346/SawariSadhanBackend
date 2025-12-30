<?php

namespace App\Http\Controllers;

use App\Models\RenewalRequest;
use App\Models\Vehicle;
use App\Services\FCMNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            
            // Prepare data array for creation
            $renewalRequestData = [
                'user_id' => $request->user()->id,
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
        
        // Load profile with service area information - use fresh() to get latest from database
        $profile = \App\Models\VendorProfile::where('vendor_id', $vendor->id)->first();
        
        // If profile doesn't exist, create a default one (but don't save it yet)
        if (!$profile) {
            Log::warning('Vendor profile not found, will show all requests', [
                'vendor_id' => $vendor->id,
            ]);
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
        $query = RenewalRequest::where('status', 'pending')
            ->whereNull('vendor_id'); // Only show requests not yet accepted
        
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
            $query->where('status', $status);
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

            // Assign request to vendor
            $renewalRequest->update([
                'status' => 'assigned',
                'vendor_id' => $request->user()->id,
                'assigned_at' => now(),
            ]);

            // DO NOT notify other vendors - request will be hidden from their list automatically
            // Other vendors will see it disappear from available requests without notification
            
            // Notify user that request was accepted
            $this->fcmService->notifyUserRequestUpdate($renewalRequest, 'assigned');

            $renewalRequest->load(['vehicle.province', 'payment', 'user', 'vendor.profile', 'fiscalYear']);

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
     */
    public function show(Request $request, $id)
    {
        $renewalRequest = RenewalRequest::with(['vehicle.province', 'payment', 'user', 'vendor.profile', 'fiscalYear'])
            ->findOrFail($id);

        // Check permissions
        if ($renewalRequest->user_id !== $request->user()->id && 
            ($request->user()->isVendor() && $renewalRequest->vendor_id !== $request->user()->id) &&
            ($request->user()->isVendor() && $renewalRequest->status !== 'pending')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $renewalRequest,
        ]);
    }

    /**
     * Update FCM token
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

        $request->user()->update([
            'fcm_token' => $request->fcm_token,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'FCM token updated successfully',
        ]);
    }
}