<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Vehicle;
use App\Models\FiscalYear;
use App\Services\TaxCalculationService;
use App\Services\KhaltiPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
            'payment_method' => 'required|string|in:esewa,khalti,bank_transfer,cash',
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

        try {
            // Calculate tax and insurance
            // include_insurance: true = user wants insurance, false = user has valid insurance (no insurance fee)
            $includeInsurance = $request->input('include_insurance', true); // Default to true for backward compatibility
            $calculation = $this->taxCalculationService->calculate($vehicle, $request->fiscal_year_id, $includeInsurance);
            
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
            $transactionId = 'TXN-' . strtoupper(Str::random(12));
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
                'transaction_id' => $transactionId,
            ]);

            // If payment method is Khalti, initiate payment
            if ($request->payment_method === 'khalti') {
                $productName = "Vehicle Tax Payment - {$vehicle->registration_number}";
                $additionalData = [
                    'customer_info' => [
                        'name' => $vehicle->owner_name,
                        'email' => $request->user()->email,
                    ]
                ];
                
                $khaltiResponse = $this->khaltiPaymentService->getPaymentDataForMobile(
                    $yearCalculation['subtotal'],
                    $transactionId,
                    $productName,
                    $additionalData
                );

                if ($khaltiResponse['success']) {
                    // Store pidx in payment details for verification later
                    $payment->update([
                        'payment_details' => [
                            'pidx' => $khaltiResponse['pidx'],
                            'khalti_transaction_id' => $khaltiResponse['pidx'],
                        ]
                    ]);

                    $payment->load(['vehicle.province', 'fiscalYear']);

                    return response()->json([
                        'success' => true,
                        'message' => 'Payment initialized. Please complete the payment.',
                        'data' => $payment,
                        'khalti' => [
                            'pidx' => $khaltiResponse['pidx'],
                            'payment_url' => $khaltiResponse['payment_url'],
                        ],
                    ], 201);
                } else {
                    // If Khalti initialization fails, still return payment record
                    return response()->json([
                        'success' => false,
                        'message' => $khaltiResponse['message'] ?? 'Failed to initialize Khalti payment',
                        'data' => $payment,
                    ], 400);
                }
            }

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

