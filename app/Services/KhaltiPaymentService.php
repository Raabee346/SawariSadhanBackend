<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KhaltiPaymentService
{
    private $secretKey;
    private $publicKey;
    private $baseUrl;

    public function __construct()
    {
        // Use test credentials for demo/testing
        // Get these from: https://khalti.com/merchant/ or https://test-admin.khalti.com
        // Test credentials are available in Khalti test merchant dashboard
        $this->secretKey = config('services.khalti.secret_key', 'test_secret_key_xxxxxxxxxxxxxxxxx');
        $this->publicKey = config('services.khalti.public_key', 'test_public_key_xxxxxxxxxxxxxxxxx');

        // Log key status (without exposing full key)
        $keyPrefix = $this->secretKey ? substr($this->secretKey, 0, 10) . '...' : 'NOT SET';
        Log::info('Khalti service initialized', [
            'secret_key_prefix' => $keyPrefix,
            'secret_key_length' => $this->secretKey ? strlen($this->secretKey) : 0,
            'public_key_prefix' => $this->publicKey ? substr($this->publicKey, 0, 10) . '...' : 'NOT SET',
            'public_key_length' => $this->publicKey ? strlen($this->publicKey) : 0,
        ]);

        // Use sandbox URL for testing, production URL for live payments
        // Sandbox: https://a.khalti.com/api/v2
        // Production: https://khalti.com/api/v2
        $isSandbox = config('services.khalti.sandbox', true);
        $this->baseUrl = $isSandbox
            ? 'https://a.khalti.com/api/v2'
            : 'https://khalti.com/api/v2';
            
        Log::info('Khalti base URL configured', [
            'base_url' => $this->baseUrl,
            'is_sandbox' => $isSandbox,
        ]);
    }

    /**
     * Initialize payment with Khalti
     * Returns payment data needed for mobile SDK
     *
     * @param float $amount Amount in NPR (Nepalese Rupees)
     * @param string $transactionId Your internal transaction ID
     * @param string $productName Name of the product/service
     * @param array $additionalData Additional data for the payment
     * @return array
     */
    public function initiatePayment(float $amount, string $transactionId, string $productName, array $additionalData = [])
    {
        try {
            // Get return_url and website_url from config, with fallbacks
            $returnUrl = config('services.khalti.return_url');
            $websiteUrl = config('services.khalti.website_url');
            
            // If not set in config, use defaults
            if (empty($returnUrl)) {
                $returnUrl = url('/api/payments/khalti/callback');
            }
            if (empty($websiteUrl)) {
                $websiteUrl = url('/');
            }
            
            $payload = [
                'return_url' => $returnUrl,
                'website_url' => $websiteUrl,
                'amount' => (int)($amount * 100), // Convert to paisa (amount * 100)
                'purchase_order_id' => $transactionId,
                'purchase_order_name' => $productName,
            ];
            
            Log::info('Khalti payment payload prepared', [
                'return_url' => $returnUrl,
                'website_url' => $websiteUrl,
                'amount_paisa' => $payload['amount'],
            ]);

            // Add additional data if provided
            if (!empty($additionalData)) {
                $payload = array_merge($payload, $additionalData);
            }

            // Validate secret key before making request
            if (empty($this->secretKey) || $this->secretKey === 'test_secret_key_xxxxxxxxxxxxxxxxx') {
                Log::error('Khalti secret key is not configured properly', [
                    'key_set' => !empty($this->secretKey),
                    'is_default' => $this->secretKey === 'test_secret_key_xxxxxxxxxxxxxxxxx',
                ]);
                return [
                    'success' => false,
                    'message' => 'Khalti secret key is not configured. Please set KHALTI_SECRET_KEY in your .env file.',
                ];
            }

            $authHeader = 'Key ' . $this->secretKey;
            Log::info('Sending Khalti payment initiation request', [
                'url' => $this->baseUrl . '/epayment/initiate/',
                'amount_paisa' => $payload['amount'],
                'transaction_id' => $payload['purchase_order_id'],
                'auth_header_prefix' => substr($authHeader, 0, 15) . '...',
            ]);

            $response = Http::timeout(60) // Increase timeout to 60 seconds
                ->retry(2, 1000) // Retry up to 2 times with 1 second delay
                ->withHeaders([
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json',
                ])->post($this->baseUrl . '/epayment/initiate/', $payload);

            $responseData = $response->json();
            
            Log::info('Khalti API response', [
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'has_payment_url' => isset($responseData['payment_url']),
                'has_pidx' => isset($responseData['pidx']),
                'response_keys' => $responseData ? array_keys($responseData) : [],
            ]);

            if ($response->successful() && isset($responseData['payment_url'])) {
                $result = [
                    'success' => true,
                    'payment_url' => $responseData['payment_url'],
                    'pidx' => $responseData['pidx'] ?? null,
                    'data' => $responseData,
                ];
                
                Log::info('Khalti payment initiated successfully', [
                    'pidx' => $result['pidx'],
                ]);
                
                return $result;
            }

            $errorMessage = $responseData['detail'] ?? ($responseData['message'] ?? 'Failed to initiate payment');
            
            // Provide helpful error message for specific status codes
            if ($response->status() === 401) {
                $errorMessage = 'Invalid Khalti secret key. Please verify your KHALTI_SECRET_KEY in .env file. For test mode, get keys from https://test-admin.khalti.com';
                Log::error('Khalti payment initiation failed - Invalid token (401)', [
                    'status_code' => $response->status(),
                    'error' => $errorMessage,
                    'response' => $responseData,
                    'base_url' => $this->baseUrl,
                    'secret_key_configured' => !empty($this->secretKey) && $this->secretKey !== 'test_secret_key_xxxxxxxxxxxxxxxxx',
                    'secret_key_length' => strlen($this->secretKey ?? ''),
                    'hint' => 'Make sure KHALTI_SECRET_KEY is set in .env file with a valid test secret key from https://test-admin.khalti.com',
                ]);
            } elseif ($response->status() === 503) {
                $errorMessage = 'Khalti service is temporarily unavailable (503). Please try again later or create a manual payout record.';
                Log::error('Khalti payment initiation failed - Service Unavailable (503)', [
                    'status_code' => $response->status(),
                    'error' => $errorMessage,
                    'response' => $responseData,
                    'base_url' => $this->baseUrl,
                ]);
            } else {
                Log::error('Khalti payment initiation failed', [
                    'status_code' => $response->status(),
                    'error' => $errorMessage,
                    'response' => $responseData,
                ]);
            }

            return [
                'success' => false,
                'message' => $errorMessage,
                'error' => $responseData,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Handle timeout and connection errors specifically
            Log::error('Khalti payment initiation failed - Connection/Timeout error', [
                'message' => $e->getMessage(),
                'url' => $this->baseUrl . '/epayment/initiate/',
            ]);
            return [
                'success' => false,
                'message' => 'Connection timeout. Please check your internet connection and try again. If the problem persists, Khalti services may be temporarily unavailable.',
            ];
        } catch (\Exception $e) {
            Log::error('Khalti payment initiation failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'message' => 'Payment initiation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify payment with Khalti using pidx
     *
     * @param string $pidx Payment ID from Khalti
     * @return array
     */
    public function verifyPayment(string $pidx)
    {
        try {
            $response = Http::timeout(60) // Increase timeout to 60 seconds
                ->retry(2, 1000) // Retry up to 2 times with 1 second delay
                ->withHeaders([
                    'Authorization' => 'Key ' . $this->secretKey,
                    'Content-Type' => 'application/json',
                ])->post($this->baseUrl . '/epayment/lookup/', [
                    'pidx' => $pidx,
                ]);

            $responseData = $response->json();

            if ($response->successful()) {
                $status = $responseData['status'] ?? null;
                return [
                    'success' => $status === 'Completed',
                    'status' => $status,
                    'transaction_id' => $responseData['transaction_id'] ?? null,
                    'amount' => isset($responseData['total_amount']) ? ($responseData['total_amount'] / 100) : null, // Convert from paisa to NPR
                    'data' => $responseData,
                ];
            }

            return [
                'success' => false,
                'message' => $responseData['detail'] ?? 'Payment verification failed',
                'data' => $responseData,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Handle timeout and connection errors specifically
            Log::error('Khalti payment verification failed - Connection/Timeout error', [
                'message' => $e->getMessage(),
                'pidx' => $pidx,
            ]);
            return [
                'success' => false,
                'message' => 'Connection timeout while verifying payment. Please try again later.',
            ];
        } catch (\Exception $e) {
            Log::error('Khalti payment verification failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'pidx' => $pidx,
            ]);
            return [
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get payment URL for mobile SDK integration
     * For mobile apps, you can use the payment_url directly or extract pidx
     *
     * @param float $amount
     * @param string $transactionId
     * @param string $productName
     * @param array $additionalData
     * @return array
     */
    public function getPaymentDataForMobile(float $amount, string $transactionId, string $productName, array $additionalData = [])
    {
        $payment = $this->initiatePayment($amount, $transactionId, $productName, $additionalData);
        
        if ($payment['success']) {
            return [
                'success' => true,
                'pidx' => $payment['pidx'],
                'payment_url' => $payment['payment_url'],
                'amount' => $amount,
                'transaction_id' => $transactionId,
            ];
        }

        return $payment;
    }
}

