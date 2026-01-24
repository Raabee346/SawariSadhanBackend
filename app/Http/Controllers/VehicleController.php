<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Province;
use App\Services\TaxCalculationService;
use App\Services\NepalDateService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    protected $taxCalculationService;

    public function __construct(TaxCalculationService $taxCalculationService)
    {
        $this->taxCalculationService = $taxCalculationService;
    }

    /**
     * Get user's vehicles
     */
    public function index(Request $request)
    {
        // Always fetch fresh data from database (no caching to ensure latest vehicle data)
        $vehicles = Vehicle::where('user_id', $request->user()->id)
            ->with(['province', 'verifiedBy'])
            ->latest()
            ->get();
        
        // Refresh each vehicle to ensure we have the latest data from database
        foreach ($vehicles as $vehicle) {
            $vehicle->refresh();
        }

        return response()->json([
            'success' => true,
            'data' => $vehicles,
        ]);
    }

    /**
     * Store a new vehicle
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'province_id' => 'required|exists:provinces,id',
            'owner_name' => 'required|string|max:255',
            'registration_number' => 'required|string|unique:vehicles,registration_number',
            'chassis_number' => 'required|string|unique:vehicles,chassis_number',
            'vehicle_type' => 'required|in:2W,4W,Commercial,Heavy',
            'fuel_type' => 'required|in:Petrol,Diesel,Electric',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'engine_capacity' => 'required|integer|min:1',
            'manufacturing_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'registration_date' => 'required|string', // BS date format: YYYY-MM-DD (e.g., 2080-05-15)
            'is_commercial' => 'boolean',
            'rc_firstpage' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'rc_ownerdetails' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'rc_vehicledetails' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'lastrenewdate' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'insurance' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'owner_ctznship_front' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'owner_ctznship_back' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'last_renewed_date' => 'nullable|string', // BS date format: YYYY-MM-DD
            'registration_date' => 'nullable|string', // BS date format: YYYY-MM-DD
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }


        // Store BS date directly (no conversion)
        // Handle file uploads
        $documentFields = [
            'rc_firstpage',
            'rc_ownerdetails',
            'rc_vehicledetails',
            'lastrenewdate',
            'insurance',
            'owner_ctznship_front',
            'owner_ctznship_back',
        ];

        $vehicleData = [
            'user_id' => $request->user()->id,
            'province_id' => $request->province_id,
            'owner_name' => $request->owner_name,
            'registration_number' => $request->registration_number,
            'chassis_number' => $request->chassis_number,
            'vehicle_type' => $request->vehicle_type,
            'fuel_type' => $request->fuel_type,
            'brand' => $request->brand,
            'model' => $request->model,
            'last_renewed_date' => $request->last_renewed_date, // Store BS date directly
            'engine_capacity' => $request->engine_capacity,
            'manufacturing_year' => $request->manufacturing_year,
            'registration_date' => $request->registration_date, // Store BS date directly
            'is_commercial' => $request->is_commercial ?? false,
            'verification_status' => 'pending',
        ];

        // Calculate expiry_date automatically from last_renewed_date (BS format)
        if ($request->last_renewed_date) {
            try {
                // Convert BS date to AD date using NepaliDate service
                $nepaliDate = new \App\Services\NepaliDate();
                $lastRenewedAD = $nepaliDate->convertBsToAd($request->last_renewed_date);
                
                // Add 1 year for expiry
                $expiryDate = \Carbon\Carbon::createFromFormat('Y-m-d', $lastRenewedAD)->addYear()->format('Y-m-d');
                
                // Store expiry date in AD format (YYYY-MM-DD)
                $vehicleData['expiry_date'] = $expiryDate;
            } catch (\Exception $e) {
                \Log::warning('Failed to calculate expiry date from last_renewed_date', [
                    'last_renewed_date' => $request->last_renewed_date,
                    'error' => $e->getMessage(),
                ]);
                // Continue without expiry_date if conversion fails
            }
        }

        // Upload documents
        foreach ($documentFields as $field) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $path = $file->store('vehicles/documents', 'public');
                $vehicleData[$field] = $path;
            }
        }

        $vehicle = Vehicle::create($vehicleData);
        $vehicle->load(['province']);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle added successfully. Please wait for admin verification.',
            'data' => $vehicle,
        ], 201);
    }

    /**
     * Get a specific vehicle
     */
    public function show(Request $request, $id)
    {
        // Always fetch fresh data from database (no caching to ensure latest vehicle data)
        $vehicle = Vehicle::where('user_id', $request->user()->id)
            ->with(['province', 'verifiedBy', 'payments.fiscalYear'])
            ->findOrFail($id);
        
        // Refresh vehicle to ensure we have the latest data from database
        $vehicle->refresh();

        // Get vehicle data as array
        $vehicleData = $vehicle->toArray();
        
        // Format dates to show only date part (remove time if present)
        // Dates are stored as strings (BS format: YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)
        if (isset($vehicleData['registration_date']) && $vehicleData['registration_date']) {
            // Remove time part if present
            $vehicleData['registration_date'] = explode(' ', $vehicleData['registration_date'])[0];
        }
        if (isset($vehicleData['last_renewed_date']) && $vehicleData['last_renewed_date']) {
            // Remove time part if present
            $vehicleData['last_renewed_date'] = explode(' ', $vehicleData['last_renewed_date'])[0];
        }
        if (isset($vehicleData['expiry_date']) && $vehicleData['expiry_date']) {
            // Remove time part if present (expiry_date is stored in AD format: YYYY-MM-DD)
            $vehicleData['expiry_date'] = explode(' ', $vehicleData['expiry_date'])[0];
        }

        return response()->json([
            'success' => true,
            'data' => $vehicleData,
        ]);
    }

    /**
     * Update a vehicle (only if pending)
     */
    public function update(Request $request, $id)
    {
        $vehicle = Vehicle::where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Allow updates for pending or rejected vehicles
        // Approved vehicles cannot be updated
        if ($vehicle->verification_status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update an approved vehicle. Please contact support if you need to make changes.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'province_id' => 'sometimes|exists:provinces,id',
            'owner_name' => 'sometimes|string|max:255',
            'registration_number' => ['sometimes', 'string', Rule::unique('vehicles')->ignore($vehicle->id)],
            'chassis_number' => ['sometimes', 'string', Rule::unique('vehicles')->ignore($vehicle->id)],
            'vehicle_type' => 'sometimes|in:2W,4W,Commercial,Heavy',
            'fuel_type' => 'sometimes|in:Petrol,Diesel,Electric',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'engine_capacity' => 'sometimes|integer|min:1',
            'manufacturing_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'registration_date' => 'sometimes|string', // BS date format: YYYY-MM-DD
            'is_commercial' => 'boolean',
            'rc_firstpage' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'rc_ownerdetails' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'rc_vehicledetails' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'lastrenewdate' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'insurance' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'owner_ctznship_front' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'owner_ctznship_back' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'last_renewed_date' => 'nullable|string', // BS date format: YYYY-MM-DD
            'registration_date' => 'nullable|string', // BS date format: YYYY-MM-DD

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = $request->only([
            'province_id',
            'owner_name',
            'registration_number',
            'chassis_number',
            'vehicle_type',
            'fuel_type',
            'brand',
            'model',
            'engine_capacity',
            'manufacturing_year',
            'is_commercial',
            'last_renewed_date',
            'expiry_date',
            'registration_date'

        ]);

        // Recalculate expiry_date if last_renewed_date is being updated
        if ($request->has('last_renewed_date') && $request->last_renewed_date) {
            try {
                $nepaliDate = new \App\Services\NepaliDate();
                $lastRenewedAD = $nepaliDate->convertBsToAd($request->last_renewed_date);
                $expiryDate = \Carbon\Carbon::createFromFormat('Y-m-d', $lastRenewedAD)->addYear()->format('Y-m-d');
                $updateData['expiry_date'] = $expiryDate;
            } catch (\Exception $e) {
                \Log::warning('Failed to calculate expiry date during update', [
                    'last_renewed_date' => $request->last_renewed_date,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Store BS dates directly (no conversion)
        // Dates are already in $updateData from $request->only()

        // Handle file uploads
        $documentFields = [
            'rc_firstpage',
            'rc_ownerdetails',
            'rc_vehicledetails',
            'lastrenewdate',
            'insurance',
            'owner_ctznship_front',
            'owner_ctznship_back',
        ];

        foreach ($documentFields as $field) {
            if ($request->hasFile($field)) {
                // Delete old file if exists
                if ($vehicle->{$field}) {
                    Storage::disk('public')->delete($vehicle->{$field});
                }
                
                // Upload new file
                $file = $request->file($field);
                $path = $file->store('vehicles/documents', 'public');
                $updateData[$field] = $path;
            }
        }

        // Reset verification status to pending when updating a rejected vehicle
        if ($vehicle->verification_status === 'rejected') {
            $updateData['verification_status'] = 'pending';
            $updateData['rejection_reason'] = null;
            Log::info('Vehicle resubmitted for verification', [
                'vehicle_id' => $vehicle->id,
                'user_id' => $request->user()->id,
            ]);
        }

        $vehicle->update($updateData);
        
        // Reload the vehicle to get updated data
        $vehicle->refresh();
        $vehicle->load(['province', 'verifiedBy']);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle updated successfully',
            'data' => $vehicle,
        ]);
    }

    /**
     * Calculate tax and insurance for a vehicle
     */
    public function calculate(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'fiscal_year_id' => 'nullable|exists:fiscal_years,id',
            'include_insurance' => 'nullable|boolean', // true = include insurance, false = user has valid insurance (no insurance fee)
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $vehicle = Vehicle::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if (!$vehicle->isVerified()) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle must be verified by admin before calculation',
            ], 403);
        }

        try {
            $fiscalYearId = $request->input('fiscal_year_id');
            // include_insurance: true = user wants insurance, false = user has valid insurance (no insurance fee)
            $includeInsurance = $request->input('include_insurance', true); // Default to true for backward compatibility
            $calculation = $this->taxCalculationService->calculate($vehicle, $fiscalYearId, $includeInsurance);

            return response()->json([
                'success' => true,
                'data' => $calculation,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Calculate tax estimate for any vehicle (standalone calculator)
     * This endpoint allows users to estimate tax without having a registered vehicle
     */
    public function calculateEstimate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_type' => 'required|string',
            'fuel_type' => 'required|string',
            'engine_capacity' => 'required|integer|min:1',
            'province_id' => 'required|exists:provinces,id',
            'last_renewed_date_bs' => 'required|string', // BS date format: YYYY-MM-DD
            'include_insurance' => 'nullable|boolean',
            'fiscal_year_id' => 'nullable|exists:fiscal_years,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Convert last_renewed_date from BS to AD using NepaliDate service
            $nepaliDate = new \App\Services\NepaliDate();
            $lastRenewedDateBS = $request->input('last_renewed_date_bs');
            
            try {
                $lastRenewedDateAD = $nepaliDate->convertBsToAd($lastRenewedDateBS);
                $lastRenewedDate = Carbon::createFromFormat('Y-m-d', $lastRenewedDateAD);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date format. Please use YYYY-MM-DD format for BS date (e.g., 2080-01-15).',
                    'error' => $e->getMessage(),
                ], 400);
            }

            // Calculate expiry date (last renewed + 1 year in AD)
            $expiryDate = $lastRenewedDate->copy()->addYear();
            
            // Convert expiry date back to BS using NepalDateService (has toBS method)
            $expiryDateBS = NepalDateService::toBS($expiryDate);

            // Create a temporary vehicle object for calculation
            $tempVehicle = new Vehicle([
                'vehicle_type' => $request->input('vehicle_type'),
                'fuel_type' => $request->input('fuel_type'),
                'engine_capacity' => $request->input('engine_capacity'),
                'province_id' => $request->input('province_id'),
                'last_renewed_date' => $lastRenewedDateBS,
                'registration_date' => $lastRenewedDateBS, // Use same as last renewed
                'expiry_date' => $expiryDate->format('Y-m-d'), // Store in AD format
                'verification_status' => 'approved', // Mark as verified for calculation
                'owner_name' => 'Estimate', // Required field
                'registration_number' => 'ESTIMATE', // Required field
            ]);

            // Set the vehicle's ID to a temporary value (for non-persisted object)
            $tempVehicle->id = 0;

            $fiscalYearId = $request->input('fiscal_year_id');
            $includeInsurance = $request->input('include_insurance', true);

            // Perform calculation using the same TaxCalculationService
            $calculation = $this->taxCalculationService->calculate($tempVehicle, $fiscalYearId, $includeInsurance);

            return response()->json([
                'success' => true,
                'data' => $calculation,
                'message' => 'Tax estimate calculated successfully based on entered details.',
            ]);

        } catch (\Exception $e) {
            \Log::error('Tax estimate calculation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Tax calculation failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get provinces list
     */
    public function provinces()
    {
        $provinces = Province::where('is_active', true)
            ->orderBy('number')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $provinces,
        ]);
    }

    /**
     * Check if vehicle's renewal date is expired
     */
    public function checkExpiry(Request $request, $id)
    {
        $vehicle = Vehicle::where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Use stored expiry_date if available (more accurate)
        $expiryDate = null;
        $lastRenewedDateBS = $vehicle->last_renewed_date;
        $lastRenewedDateAD = null;
        
        if ($vehicle->expiry_date) {
            // Use stored expiry_date (already in AD format)
            try {
                $expiryDate = \Carbon\Carbon::createFromFormat('Y-m-d', $vehicle->expiry_date);
            } catch (\Exception $e) {
                \Log::warning('Failed to parse stored expiry_date, will recalculate', [
                    'expiry_date' => $vehicle->expiry_date,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // If expiry_date not available, calculate from last_renewed_date
        if (!$expiryDate && $lastRenewedDateBS) {
            try {
                // Remove time part if present
                $dateStr = explode(' ', $lastRenewedDateBS)[0];
                
                // Convert BS date to AD date using NepaliDate service
                $nepaliDate = new \App\Services\NepaliDate();
                $lastRenewedADStr = $nepaliDate->convertBsToAd($dateStr);
                $lastRenewedDateAD = \Carbon\Carbon::createFromFormat('Y-m-d', $lastRenewedADStr);
                
                // Calculate expiry date (last renewed + 1 year)
                $expiryDate = $lastRenewedDateAD->copy()->addYear();
            } catch (\Exception $e) {
                \Log::error('Error converting BS date to AD in checkExpiry', [
                    'last_renewed_date_bs' => $lastRenewedDateBS,
                    'error' => $e->getMessage(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to determine expiry date. Please ensure vehicle has valid renewal date.',
                ], 400);
            }
        }
        
        if (!$expiryDate) {
            return response()->json([
                'success' => true,
                'data' => [
                    'is_expired' => false,
                    'message' => 'No renewal date or expiry date found',
                    'last_renewed_date_bs' => $lastRenewedDateBS,
                    'expiry_date_ad' => null,
                    'current_date_ad' => \Carbon\Carbon::today()->format('Y-m-d'),
                ],
            ]);
        }

        try {
            // Get current date
            $today = \Carbon\Carbon::today();
            
            // Check if expired (expiry date is before or equal to today)
            $isExpired = $expiryDate->lte($today);
            
            // Calculate days expired (if expired) or days until expiry (if not expired)
            $daysDifference = $today->diffInDays($expiryDate, false); // Negative if expired
            
            return response()->json([
                'success' => true,
                'data' => [
                    'is_expired' => $isExpired,
                    'last_renewed_date_bs' => $lastRenewedDateBS ? explode(' ', $lastRenewedDateBS)[0] : null,
                    'last_renewed_date_ad' => $lastRenewedDateAD ? $lastRenewedDateAD->format('Y-m-d') : null,
                    'expiry_date_ad' => $expiryDate->format('Y-m-d'),
                    'current_date_ad' => $today->format('Y-m-d'),
                    'days_difference' => $daysDifference, // Negative if expired, positive if valid
                    'message' => $isExpired 
                        ? "Vehicle renewal expired " . abs($daysDifference) . " days ago" 
                        : "Vehicle renewal is valid for " . $daysDifference . " more days",
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Error checking vehicle expiry', [
                'vehicle_id' => $id,
                'expiry_date' => $vehicle->expiry_date,
                'last_renewed_date' => $lastRenewedDateBS,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error checking expiry date: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete a vehicle
     */
    public function destroy(Request $request, $id)
    {
        $vehicle = Vehicle::where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Delete associated documents from storage
        $documentFields = [
            'rc_firstpage',
            'rc_ownerdetails',
            'rc_vehicledetails',
            'lastrenewdate',
            'insurance',
            'owner_ctznship_front',
            'owner_ctznship_back',
        ];

        foreach ($documentFields as $field) {
            if ($vehicle->{$field}) {
                Storage::disk('public')->delete($vehicle->{$field});
            }
        }

        // Delete the vehicle
        $vehicle->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vehicle deleted successfully',
        ]);
    }
}

