<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Province;
use App\Services\TaxCalculationService;
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
        $vehicles = Vehicle::where('user_id', $request->user()->id)
            ->with(['province', 'verifiedBy'])
            ->latest()
            ->get();

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
        $vehicle = Vehicle::where('user_id', $request->user()->id)
            ->with(['province', 'verifiedBy', 'payments.fiscalYear'])
            ->findOrFail($id);

        // Format dates to show only date part (YYYY-MM-DD) in response
        $vehicleData = $vehicle->toArray();
        if ($vehicle->registration_date) {
            $vehicleData['registration_date'] = $vehicle->registration_date->format('Y-m-d');
        }
        if ($vehicle->last_renewed_date) {
            $vehicleData['last_renewed_date'] = $vehicle->last_renewed_date->format('Y-m-d');
        }
        
        // Add BS dates to response
        $vehicleData['registration_date_bs'] = $this->toBSDate($vehicle->registration_date);
        $vehicleData['last_renewed_date_bs'] = $this->toBSDate($vehicle->last_renewed_date);

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

        if ($vehicle->verification_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update vehicle that is already verified or rejected',
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
            'registration_date'

        ]);

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
}

