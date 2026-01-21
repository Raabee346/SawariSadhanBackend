<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Vehicle;
use App\Models\FiscalYear;
use App\Models\Activity;
use App\Services\TaxCalculationService;
use App\Services\KhaltiPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Auth\AuthenticationException;

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
        try {
            // Check if user is authenticated
            $user = $request->user();
            if (!$user) {
                Log::error('Payment creation failed: User not authenticated', [
                    'has_token' => $request->bearerToken() !== null,
                    'token_preview' => $request->bearerToken() ? substr($request->bearerToken(), 0, 20) . '...' : null,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token. Please login again.',
                ], 401);
            }

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

            try {
                $vehicle = Vehicle::where('user_id', $user->id)
                    ->findOrFail($request->vehicle_id);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                Log::error('Payment creation failed: Vehicle not found', [
                    'vehicle_id' => $request->vehicle_id,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle not found or you do not have permission to access it.',
                ], 404);
            }
            if (!$vehicle->isVerified()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle must be verified before payment',
                ], 403);
            }

            DB::beginTransaction();
            try {
                Log::info('Creating payment', [
                    'user_id' => $user->id,
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
                        'user_id' => $user->id,
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
            
            // Use summary totals from calculation (includes ALL overdue years, not just one)
            $summary = $calculation['summary'];
            
            // Calculate amounts matching TaxCalculationService logic:
            // - Use summary totals (sum of all overdue years)
            // - Service Fee = 600 default
            // - VAT = 13% on service fee only
            // - Total Amount = tax + insurance + renewal_fee + penalty + service_fee + VAT
            
            $taxAmount = (float) $summary['total_tax'];
            $insuranceAmount = (float) $summary['total_insurance'];
            $renewalFee = (float) $summary['total_renewal_fee'];
            $penaltyAmountFromTax = (float) $summary['total_penalty']; // Tax penalty across all years
            $renewalFeePenalty = (float) $summary['total_renewal_fee_penalty']; // Renewal fee penalty across all years
            $penaltyAmount = (float) $summary['total_penalty_amount']; // Total penalty (both types, all years)
            
            // Log penalty breakdown for debugging
            Log::info('Payment calculation using summary totals (all overdue years)', [
                'years_count' => $summary['years_count'],
                'total_tax' => $taxAmount,
                'total_renewal_fee' => $renewalFee,
                'penalty_amount_from_tax' => $penaltyAmountFromTax,
                'renewal_fee_penalty' => $renewalFeePenalty,
                'total_penalty_amount' => $penaltyAmount,
                'total_insurance' => $insuranceAmount,
            ]);
            
            // Service fee = 600 default (matching TaxCalculationService)
            $serviceFee = 600.0;
            
            // VAT = 13% on service fee only (not on other fees)
            $vatAmount = round($serviceFee * 0.13, 2);
            
            // Total amount = tax + insurance + renewal_fee + penalty + service_fee + VAT (on service fee only)
            $grandTotal = round($taxAmount + $insuranceAmount + $renewalFee + $penaltyAmount + $serviceFee + $vatAmount, 2);

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
                'grand_total' => $grandTotal,
                'calculation_breakdown' => [
                    'tax' => $taxAmount,
                    'insurance' => $insuranceAmount,
                    'renewal_fee' => $renewalFee,
                    'penalty' => $penaltyAmount,
                    'service_fee' => $serviceFee,
                    'vat_on_service' => $vatAmount,
                    'sum' => $taxAmount + $insuranceAmount + $renewalFee + $penaltyAmount + $serviceFee + $vatAmount,
                ],
            ]);
            
            try {
                $payment = Payment::create([
                    'user_id' => $user->id,
                    'vehicle_id' => $vehicle->id,
                    'fiscal_year_id' => $request->fiscal_year_id,
                    'tax_amount' => $taxAmount,
                    'renewal_fee' => $renewalFee,
                    'penalty_amount' => $penaltyAmount,
                    'insurance_amount' => $insuranceAmount,
                    'service_fee' => $serviceFee,
                    'vat_amount' => $vatAmount,
                    'total_amount' => $grandTotal, // Save grand total (tax + insurance + renewal_fee + penalty + service_fee + VAT on service fee)
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

                    // Create activity for payment
                    try {
                        Activity::createPaymentActivity($payment);
                        Log::info('Payment activity created', ['payment_id' => $payment->id]);
                    } catch (\Exception $e) {
                        Log::error('Failed to create payment activity', [
                            'payment_id' => $payment->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

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
                        'service_fee' => $payment->service_fee,
                        'vat_amount' => $payment->vat_amount,
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

            // If payment method is Khalti, initiate payment
            if ($request->payment_method === 'khalti') {
                try {
                    Log::info('Initializing Khalti payment', [
                        'payment_id' => $payment->id,
                        'amount' => $payment->total_amount,
                    ]);
                    
                    // Initialize Khalti payment
                    $amount = $payment->total_amount; // Amount in NPR
                    $transactionId = 'PAYMENT_' . $payment->id;
                    $productName = 'Vehicle Renewal Payment - ' . $vehicle->registration_number;
                    
                    // For KPG-2: Khalti API requires HTTP/HTTPS return_url
                    // The SDK will handle redirects automatically if the backend callback redirects to deep link
                    $returnUrl = config('services.khalti.return_url', '');
                    
                    // If return_url contains localhost or 127.0.0.1, replace with actual IP
                    if (!empty($returnUrl) && (str_contains($returnUrl, '127.0.0.1') || str_contains($returnUrl, 'localhost'))) {
                        // Replace localhost with the IP from Android app (192.168.18.50)
                        $returnUrl = str_replace('127.0.0.1', '192.168.18.50', $returnUrl);
                        $returnUrl = str_replace('localhost', '192.168.18.50', $returnUrl);
                        Log::info('Replaced localhost in return_url with IP address', [
                            'original' => config('services.khalti.return_url', ''),
                            'updated' => $returnUrl,
                        ]);
                    }
                    
                    // If return_url is still empty or invalid, construct from request
                    if (empty($returnUrl) || !preg_match('/^https?:\/\//', $returnUrl)) {
                        $host = $request->getHost();
                        $port = $request->getPort();
                        
                        if ($host === 'localhost' || $host === '127.0.0.1') {
                            $host = '192.168.18.50'; // Use the same IP as Android app
                        }
                        
                        $scheme = $request->getScheme();
                        $portStr = ($port && $port != 80 && $port != 443) ? ":{$port}" : '';
                        $returnUrl = "{$scheme}://{$host}{$portStr}/api/payments/khalti/callback";
                        
                        Log::info('Constructed return_url from request', [
                            'host' => $host,
                            'port' => $port,
                            'return_url' => $returnUrl,
                        ]);
                    }
                    
                    // Note: KPG-2 SDK uses pidx directly and handles callbacks via OnPaymentResult
                    // The return_url is mainly for webhook callbacks and web-based redirects
                    // The SDK will automatically redirect to deep link if configured in callback handler
                    
                    $websiteUrl = config('services.khalti.website_url', '');
                    // Fix website_url if it contains localhost
                    if (!empty($websiteUrl) && (str_contains($websiteUrl, '127.0.0.1') || str_contains($websiteUrl, 'localhost'))) {
                        $websiteUrl = str_replace('127.0.0.1', '192.168.18.50', $websiteUrl);
                        $websiteUrl = str_replace('localhost', '192.168.18.50', $websiteUrl);
                    }
                    if (empty($websiteUrl) || !preg_match('/^https?:\/\//', $websiteUrl)) {
                        $host = $request->getHost();
                        if ($host === 'localhost' || $host === '127.0.0.1') {
                            $host = '192.168.18.50';
                        }
                        $scheme = $request->getScheme();
                        $port = $request->getPort();
                        $portStr = ($port && $port != 80 && $port != 443) ? ":{$port}" : '';
                        $websiteUrl = "{$scheme}://{$host}{$portStr}";
                    }
                    
                    $additionalData = [
                        'return_url' => $returnUrl,
                        'website_url' => $websiteUrl,
                    ];
                    
                    Log::info('Khalti payment return URL configured', [
                        'return_url' => $returnUrl,
                        'website_url' => $websiteUrl,
                        'config_return_url' => config('services.khalti.return_url'),
                        'config_website_url' => config('services.khalti.website_url'),
                    ]);
                    
                    Log::info('Calling Khalti payment service', [
                        'amount' => $amount,
                        'transaction_id' => $transactionId,
                        'product_name' => $productName,
                    ]);
                    
                    $khaltiResponse = $this->khaltiPaymentService->initiatePayment(
                        $amount,
                        $transactionId,
                        $productName,
                        $additionalData
                    );
                    
                    Log::info('Khalti payment service response', [
                        'success' => $khaltiResponse['success'] ?? false,
                        'has_pidx' => isset($khaltiResponse['pidx']),
                        'response_keys' => array_keys($khaltiResponse),
                    ]);
                    
                    // Check if response has pidx (from the 'data' key or directly)
                    $pidx = null;
                    if (isset($khaltiResponse['pidx'])) {
                        $pidx = $khaltiResponse['pidx'];
                    } elseif (isset($khaltiResponse['data']['pidx'])) {
                        $pidx = $khaltiResponse['data']['pidx'];
                    } elseif (isset($khaltiResponse['data']) && is_array($khaltiResponse['data']) && isset($khaltiResponse['data']['pidx'])) {
                        $pidx = $khaltiResponse['data']['pidx'];
                    }

                    Log::info('Khalti response analysis', [
                        'response_success' => $khaltiResponse['success'] ?? false,
                        'has_pidx' => !empty($pidx),
                        'pidx' => $pidx,
                        'response_keys' => array_keys($khaltiResponse),
                        'full_response' => $khaltiResponse,
                    ]);

                    if (!$khaltiResponse || !($khaltiResponse['success'] ?? false) || !$pidx) {
                        DB::rollBack();
                        Log::error('Khalti payment initialization failed', [
                            'response' => $khaltiResponse,
                            'has_pidx' => !empty($pidx),
                            'response_success' => $khaltiResponse['success'] ?? false,
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => $khaltiResponse['message'] ?? 'Failed to initialize Khalti payment. Please check Khalti service configuration.',
                        ], 500);
                    }

                    Log::info('Khalti payment initialized successfully', [
                        'pidx' => $pidx,
                        'payment_id' => $payment->id,
                    ]);

                    // Store pidx in payment record (if column exists)
                    // Note: We'll store it in a JSON field or metadata if khalti_pidx column doesn't exist
                    try {
                        if (Schema::hasColumn('payments', 'khalti_pidx')) {
                            $payment->khalti_pidx = $pidx;
                            $payment->save();
                        }
                    } catch (\Exception $e) {
                        // Column doesn't exist, that's okay - pidx will be returned in response
                        Log::info('khalti_pidx column not found, storing pidx in response only');
                    }

                    DB::commit();

                    Log::info('Payment committed, preparing response', [
                        'payment_id' => $payment->id,
                    ]);

                    // Return response with Khalti data
                    // Don't include full payment object if it causes issues, just essential data
                    $responseData = [
                        'success' => true,
                        'message' => 'Khalti payment initialized. Please complete the payment.',
                        'data' => [
                            'id' => $payment->id,
                            'total_amount' => $payment->total_amount,
                            'payment_status' => $payment->payment_status,
                            'payment_method' => $payment->payment_method,
                        ],
                        'khalti' => [
                            'pidx' => $pidx,
                            'payment_url' => $khaltiResponse['payment_url'] ?? null,
                        ],
                    ];
                    
                    Log::info('Returning Khalti payment response', [
                        'payment_id' => $payment->id,
                        'has_khalti_data' => isset($responseData['khalti']),
                        'pidx' => $pidx,
                    ]);
                    
                    // Return response - ensure it's returned before any outer catch can intercept
                    try {
                        return response()->json($responseData, 201);
                    } catch (\Exception $returnException) {
                        Log::error('Error returning Khalti payment response', [
                            'error' => $returnException->getMessage(),
                            'trace' => $returnException->getTraceAsString(),
                        ]);
                        // Even if JSON encoding fails, try to return a simple response
                        return response()->json([
                            'success' => true,
                            'message' => 'Khalti payment initialized',
                            'data' => ['id' => $payment->id],
                            'khalti' => ['pidx' => $pidx],
                        ], 201);
                    }
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Error initializing Khalti payment', [
                        'payment_id' => $payment->id ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to initialize Khalti payment: ' . $e->getMessage(),
                    ], 500);
                }
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
                Log::error('Payment creation failed in inner catch', [
                    'user_id' => isset($user) ? $user->id : null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Payment creation failed: ' . $e->getMessage(),
                ], 500);
            }
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            // Handle authentication exceptions specifically
            Log::error('Payment creation failed: Authentication exception', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid token. Please login again.',
            ], 401);
        } catch (\Exception $e) {
            // Log the full exception for debugging
            Log::error('Payment creation failed: Exception in outer catch', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'exception_class' => get_class($e),
            ]);
            
            // Only check for authentication errors if it's specifically an AuthenticationException
            // Don't check for "token" in message as it might be a false positive
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token. Please login again.',
                ], 401);
            }
            
            // Check if user was authenticated at the start
            // If we got here, it's likely a different error, not authentication
            // Don't expose internal error details to client
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request. Please try again.',
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
            
            // Create activity for payment
            try {
                // Check if activity already exists to avoid duplicates
                $existingActivity = Activity::where('related_id', $payment->id)
                    ->where('related_type', 'App\Models\Payment')
                    ->where('activity_type', 'payment')
                    ->first();
                
                if (!$existingActivity) {
                    Activity::createPaymentActivity($payment);
                    Log::info('Payment activity created', ['payment_id' => $payment->id]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to create payment activity', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
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
     * For mobile apps, this redirects to the deep link
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
            // Find payment by pidx stored in payment_details
            $payment = Payment::where('payment_method', 'khalti')
                ->whereJsonContains('payment_details->pidx', $request->pidx)
                ->first();

            if (!$payment) {
                // Try to find by pidx in khalti_pidx column if it exists
                $payment = Payment::where('payment_method', 'khalti')
                    ->where('khalti_pidx', $request->pidx)
                    ->first();
            }

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
                if ($vehicle && $fiscalYear) {
                    $vehicle->update([
                        'last_renewed_date' => $fiscalYear->end_date,
                    ]);
                }

                // Create activity for payment
                try {
                    // Check if activity already exists to avoid duplicates
                    $existingActivity = Activity::where('related_id', $payment->id)
                        ->where('related_type', 'App\Models\Payment')
                        ->where('activity_type', 'payment')
                        ->first();
                    
                    if (!$existingActivity) {
                        Activity::createPaymentActivity($payment);
                        Log::info('Payment activity created from callback', ['payment_id' => $payment->id]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to create payment activity from callback', [
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // For mobile apps, redirect to deep link
            // Check if request is from mobile (User-Agent or Accept header)
            $userAgent = $request->header('User-Agent', '');
            $isMobile = str_contains(strtolower($userAgent), 'android') || 
                       str_contains(strtolower($userAgent), 'iphone') ||
                       str_contains(strtolower($userAgent), 'mobile');
            
            if ($isMobile || $request->expectsJson()) {
                // Return JSON response for API calls
                return response()->json([
                    'success' => true,
                    'message' => 'Payment callback processed',
                    'redirect_url' => 'sawarisewa://payment/callback?pidx=' . urlencode($request->pidx) . '&status=success',
                ]);
            }
            
            // For web browsers, redirect to deep link via HTML redirect
            $deepLink = 'sawarisewa://payment/callback?pidx=' . urlencode($request->pidx) . '&status=success';
            return response()->view('khalti-callback-redirect', [
                'deepLink' => $deepLink,
                'pidx' => $request->pidx,
            ])->header('Content-Type', 'text/html');
        }

        // Payment verification failed
        $errorMessage = $verification['message'] ?? 'Payment verification failed';
        
        // For mobile, return JSON with error
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $errorMessage,
            ], 400);
        }
        
        // For web, show error page
        return response()->json([
            'success' => false,
            'message' => $errorMessage,
        ], 400);
    }
}

