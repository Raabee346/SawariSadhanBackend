<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Vehicle;
use App\Models\FiscalYear;
use App\Services\TaxCalculationService;
use App\Services\KhaltiPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected $taxCalculationService;
    protected $khaltiPaymentService;

    public function __construct(TaxCalculationService $taxCalculationService, KhaltiPaymentService $khaltiPaymentService)
    {
        $this->taxCalculationService = $taxCalculationService;
        $this->khaltiPaymentService = $khaltiPaymentService;
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
            'payment_method' => 'required|string|in:esewa,khalti,bank_transfer,cash,cash_on_delivery',
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
            ->findOrFail($request->vehicle_id);

        if (!$vehicle->isVerified()) {
            return response()->json([
                'success' => false,
                'message' => 'Vehicle must be verified before payment',
            ], 403);
        }

        DB::beginTransaction();
        try {
            Log::info('Creating payment', [
                'user_id' => $request->user()->id,
                'vehicle_id' => $request->vehicle_id,
                'fiscal_year_id' => $request->fiscal_year_id,
                'payment_method' => $request->payment_method,
                'include_insurance' => $request->input('include_insurance'),
            ]);
            
            // Calculate tax and insurance
            // include_insurance: true = user wants insurance, false = user has valid insurance (no insurance fee)
            $includeInsurance = $request->input('include_insurance', true); // Default to true for backward compatibility
            
            try {
                $calculation = $this->taxCalculationService->calculate($vehicle, $request->fiscal_year_id, $includeInsurance);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Tax calculation failed', [
                    'user_id' => $request->user()->id,
                    'vehicle_id' => $vehicle->id,
                    'fiscal_year_id' => $request->fiscal_year_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Tax calculation failed: ' . $e->getMessage(),
                ], 500);
            }
            
            // Find the specific year calculation
            $yearCalculation = collect($calculation['calculations'])
                ->firstWhere('fiscal_year_id', $request->fiscal_year_id);

            if (!$yearCalculation) {
                DB::rollBack();
                Log::error('Calculation not found for fiscal year', [
                    'fiscal_year_id' => $request->fiscal_year_id,
                    'user_id' => $request->user()->id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Calculation not found for the specified fiscal year',
                ], 404);
            }

            // Calculate amounts matching Android calculation exactly
            // Android calculation (PaymentSummaryActivity.java):
            // - Service Fee = renewalFee + penalties (or 500 if 0)
            // - Subtotal = roadTax + insurance + serviceFee
            // - VAT = subtotal * 0.13
            // - Grand Total = subtotal + VAT
            
            $taxAmount = (float) $yearCalculation['tax_amount'];
            $insuranceAmount = (float) $yearCalculation['insurance_amount'];
            $renewalFee = (float) $yearCalculation['renewal_fee'];
            $penaltyAmount = (float) ($yearCalculation['penalty_amount'] + $yearCalculation['renewal_fee_penalty']);
            
            // Service fee = renewal fee + penalties (or 500 if 0) - matching Android logic
            $serviceFee = $renewalFee + $penaltyAmount;
            if ($serviceFee <= 0) {
                $serviceFee = 500.0; // Default service fee
            }
            
            // Subtotal = tax + insurance + service fee (matching Android)
            $subtotal = $taxAmount + $insuranceAmount + $serviceFee;
            
            // VAT = 13% on subtotal (matching Android)
            $vatAmount = round($subtotal * 0.13, 2);
            
            // Grand total = subtotal + VAT (matching Android)
            $grandTotal = round($subtotal + $vatAmount, 2);

            // Create payment record
            $transactionId = 'TXN-' . strtoupper(Str::random(12));
            
            Log::info('Creating payment record', [
                'transaction_id' => $transactionId,
                'tax_amount' => $taxAmount,
                'renewal_fee' => $renewalFee,
                'penalty_amount' => $penaltyAmount,
                'insurance_amount' => $insuranceAmount,
                'service_fee' => $serviceFee,
                'vat_amount' => $vatAmount,
                'subtotal' => $subtotal,
                'grand_total' => $grandTotal,
            ]);
            
            try {
                $payment = Payment::create([
                    'user_id' => $request->user()->id,
                    'vehicle_id' => $vehicle->id,
                    'fiscal_year_id' => $request->fiscal_year_id,
                    'tax_amount' => $taxAmount,
                    'renewal_fee' => $renewalFee,
                    'penalty_amount' => $penaltyAmount,
                    'insurance_amount' => $insuranceAmount,
                    'total_amount' => $grandTotal, // Save grand total (subtotal + VAT) matching Android
                    'payment_status' => 'pending',
                    'payment_method' => $request->payment_method,
                    'transaction_id' => $transactionId,
                ]);
                
                Log::info('Payment created successfully', ['payment_id' => $payment->id]);
            } catch (\Illuminate\Database\QueryException $e) {
                DB::rollBack();
                Log::error('Database error creating payment', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'sql' => $e->getSql(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Database error: ' . $e->getMessage(),
                ], 500);
            }

            // If payment method is Cash on Delivery, automatically mark as completed
            if ($request->payment_method === 'cash_on_delivery' || $request->payment_method === 'cash') {
                try {
                    $payment->update([
                        'payment_status' => 'completed',
                        'payment_date' => now(),
                    ]);
                    
                    Log::info('Payment marked as completed for COD', ['payment_id' => $payment->id]);
                    
                    // Commit transaction before loading relationships
                    DB::commit();
                    
                    // Refresh payment to ensure latest data
                    $payment->refresh();
                    $payment->load(['vehicle.province', 'fiscalYear']);

                    // Verify payment was actually saved to database
                    $savedPayment = Payment::find($payment->id);
                    if (!$savedPayment) {
                        Log::error('Payment was not found in database after creation', [
                            'payment_id' => $payment->id,
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => 'Payment was created but could not be verified in database',
                        ], 500);
                    }
                    
                    Log::info('Payment created and completed successfully', [
                        'payment_id' => $payment->id,
                        'status' => $payment->payment_status,
                        'payment_method' => $payment->payment_method,
                        'total_amount' => $payment->total_amount,
                        'payment_date' => $payment->payment_date,
                        'verified_in_db' => $savedPayment !== null,
                    ]);

                    // Ensure payment data is properly formatted
                    // Helper function to format date safely (handles both Carbon and string)
                    $formatDate = function($date) {
                        if (!$date) {
                            return null;
                        }
                        if ($date instanceof \Carbon\Carbon) {
                            return $date->format('Y-m-d H:i:s');
                        }
                        // If it's already a string, return as-is or format if needed
                        if (is_string($date)) {
                            try {
                                return \Carbon\Carbon::parse($date)->format('Y-m-d H:i:s');
                            } catch (\Exception $e) {
                                return $date; // Return as-is if parsing fails
                            }
                        }
                        return null;
                    };
                    
                    $paymentData = [
                        'id' => $payment->id,
                        'user_id' => $payment->user_id,
                        'vehicle_id' => $payment->vehicle_id,
                        'fiscal_year_id' => $payment->fiscal_year_id,
                        'tax_amount' => $payment->tax_amount,
                        'renewal_fee' => $payment->renewal_fee,
                        'penalty_amount' => $payment->penalty_amount,
                        'insurance_amount' => $payment->insurance_amount,
                        'total_amount' => $payment->total_amount,
                        'payment_status' => $payment->payment_status,
                        'payment_method' => $payment->payment_method,
                        'transaction_id' => $payment->transaction_id,
                        'payment_date' => $formatDate($payment->payment_date),
                        'created_at' => $formatDate($payment->created_at),
                    ];

                    return response()->json([
                        'success' => true,
                        'message' => 'Payment record created. You can proceed with service request.',
                        'data' => $paymentData,
                    ], 201);
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Error updating payment status for COD', [
                        'payment_id' => $payment->id ?? null,
                        'error' => $e->getMessage(),
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to update payment status: ' . $e->getMessage(),
                    ], 500);
                }
            }

            // If payment method is Khalti, initiate payment (DISABLED for now)
            if ($request->payment_method === 'khalti') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Khalti payment is currently disabled. Please use Cash on Delivery.',
                ], 400);
            }

            // Commit transaction for other payment methods
            DB::commit();
            
            $payment->refresh();
            $payment->load(['vehicle.province', 'fiscalYear']);

            return response()->json([
                'success' => true,
                'message' => 'Payment record created. Please complete the payment.',
                'data' => $payment,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment creation failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Payment creation failed: ' . $e->getMessage(),
            ], 500);
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

    /**
     * Verify Khalti payment (callback from mobile app)
     * Mobile app should call this after user completes payment
     */
    public function verifyKhaltiPayment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'pidx' => 'required|string',
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

        if ($payment->payment_method !== 'khalti') {
            return response()->json([
                'success' => false,
                'message' => 'This payment is not a Khalti payment',
            ], 400);
        }

        // Verify payment with Khalti
        $verification = $this->khaltiPaymentService->verifyPayment($request->pidx);

        if ($verification['success']) {
            $payment->update([
                'payment_status' => 'completed',
                'transaction_id' => $verification['transaction_id'] ?? $payment->transaction_id,
                'payment_details' => array_merge($payment->payment_details ?? [], [
                    'pidx' => $request->pidx,
                    'khalti_transaction_id' => $verification['transaction_id'] ?? null,
                    'verified_at' => now()->toDateTimeString(),
                    'verification_response' => $verification['data'] ?? null,
                ]),
                'payment_date' => now(),
            ]);

            // Update vehicle's last_renewed_date
            $vehicle = $payment->vehicle;
            $fiscalYear = $payment->fiscalYear;
            $vehicle->update([
                'last_renewed_date' => $fiscalYear->end_date,
            ]);

            // Note: Renewal request will be created from Android app after payment verification
            // This keeps the payment and request creation separate as requested
            
            $payment->load(['vehicle.province', 'fiscalYear']);

            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully',
                'data' => $payment,
            ]);
        }

        // Payment verification failed
        $payment->update([
            'payment_status' => 'failed',
            'payment_details' => array_merge($payment->payment_details ?? [], [
                'verification_error' => $verification['message'] ?? 'Verification failed',
                'verification_response' => $verification['data'] ?? null,
            ]),
        ]);

        return response()->json([
            'success' => false,
            'message' => $verification['message'] ?? 'Payment verification failed',
            'data' => $payment,
        ], 400);
    }

    /**
     * Khalti payment callback (webhook from Khalti server)
     * This is called by Khalti server after payment is completed
     */
    public function khaltiCallback(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pidx' => 'required|string',
            'transaction_id' => 'nullable|string',
            'amount' => 'nullable|numeric',
            'status' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid callback data',
            ], 422);
        }

        // Verify payment with Khalti
        $verification = $this->khaltiPaymentService->verifyPayment($request->pidx);

        if ($verification['success']) {
            // Find payment by transaction_id stored in payment_details
            $payment = Payment::where('payment_method', 'khalti')
                ->whereJsonContains('payment_details->pidx', $request->pidx)
                ->first();

            if ($payment && $payment->payment_status === 'pending') {
                $payment->update([
                    'payment_status' => 'completed',
                    'transaction_id' => $verification['transaction_id'] ?? $request->transaction_id ?? $payment->transaction_id,
                    'payment_details' => array_merge($payment->payment_details ?? [], [
                        'khalti_transaction_id' => $verification['transaction_id'] ?? null,
                        'callback_data' => $request->all(),
                        'verified_at' => now()->toDateTimeString(),
                    ]),
                    'payment_date' => now(),
                ]);

                // Update vehicle's last_renewed_date
                $vehicle = $payment->vehicle;
                $fiscalYear = $payment->fiscalYear;
                $vehicle->update([
                    'last_renewed_date' => $fiscalYear->end_date,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment callback processed',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Payment verification failed',
        ], 400);
    }
}

