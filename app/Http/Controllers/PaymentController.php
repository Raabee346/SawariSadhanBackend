<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Vehicle;
use App\Models\FiscalYear;
use App\Services\TaxCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected $taxCalculationService;

    public function __construct(TaxCalculationService $taxCalculationService)
    {
        $this->taxCalculationService = $taxCalculationService;
    }

    /**
     * Get user's payments
     */
    public function index(Request $request)
    {
        $payments = Payment::where('user_id', $request->user()->id)
            ->with(['vehicle.province', 'fiscalYear'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Create a payment record
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vehicle_id' => 'required|exists:vehicles,id',
            'fiscal_year_id' => 'required|exists:fiscal_years,id',
            'payment_method' => 'required|string|in:esewa,khalti,bank_transfer,cash',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $vehicle = Vehicle::where('user_id', $request->user()->id)
            ->findOrFail($request->vehicle_id);

        if (!$vehicle->isVerified()) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle must be verified before payment',
            ], 403);
        }

        try {
            // Calculate tax and insurance
            $calculation = $this->taxCalculationService->calculate($vehicle, $request->fiscal_year_id);
            
            // Find the specific year calculation
            $yearCalculation = collect($calculation['calculations'])
                ->firstWhere('fiscal_year_id', $request->fiscal_year_id);

            if (!$yearCalculation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Calculation not found for the specified fiscal year',
                ], 404);
            }

            // Create payment record
            $payment = Payment::create([
                'user_id' => $request->user()->id,
                'vehicle_id' => $vehicle->id,
                'fiscal_year_id' => $request->fiscal_year_id,
                'tax_amount' => $yearCalculation['tax_amount'],
                'renewal_fee' => $yearCalculation['renewal_fee'],
                'penalty_amount' => $yearCalculation['penalty_amount'] + $yearCalculation['renewal_fee_penalty'],
                'insurance_amount' => $yearCalculation['insurance_amount'],
                'total_amount' => $yearCalculation['subtotal'],
                'payment_status' => 'pending',
                'payment_method' => $request->payment_method,
                'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
            ]);

            $payment->load(['vehicle.province', 'fiscalYear']);

            return response()->json([
                'success' => true,
                'message' => 'Payment record created. Please complete the payment.',
                'data' => $payment,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update payment status (for payment gateway callbacks)
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_status' => 'required|in:pending,completed,failed,refunded',
            'transaction_id' => 'nullable|string',
            'payment_details' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payment = Payment::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $payment->update([
            'payment_status' => $request->payment_status,
            'transaction_id' => $request->transaction_id ?? $payment->transaction_id,
            'payment_details' => $request->payment_details ?? $payment->payment_details,
            'payment_date' => $request->payment_status === 'completed' ? now() : null,
        ]);

        // Update vehicle's last_renewed_date if payment is completed
        if ($request->payment_status === 'completed') {
            $vehicle = $payment->vehicle;
            $fiscalYear = $payment->fiscalYear;
            $vehicle->update([
                'last_renewed_date' => $fiscalYear->end_date,
            ]);
        }

        $payment->load(['vehicle.province', 'fiscalYear']);

        return response()->json([
            'success' => true,
            'message' => 'Payment status updated',
            'data' => $payment,
        ]);
    }

    /**
     * Get a specific payment
     */
    public function show(Request $request, $id)
    {
        $payment = Payment::where('user_id', $request->user()->id)
            ->with(['vehicle.province', 'fiscalYear'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $payment,
        ]);
    }
}

