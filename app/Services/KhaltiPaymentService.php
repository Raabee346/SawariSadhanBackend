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
        // Get these from: https://khalti.com/merchant/
        // Test credentials are available in Khalti merchant dashboard
        $this->secretKey = config('services.khalti.secret_key', 'test_secret_key_xxxxxxxxxxxxxxxxx');
        $this->publicKey = config('services.khalti.public_key', 'test_public_key_xxxxxxxxxxxxxxxxx');

        // Use sandbox URL for testing, production URL for live payments
        // Sandbox: https://a.khalti.com/api/v2
        // Production: https://khalti.com/api/v2
        $this->baseUrl = config('services.khalti.sandbox', true)
            ? 'https://a.khalti.com/api/v2'
            : 'https://khalti.com/api/v2';
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
            $payload = [
                'return_url' => config('services.khalti.return_url', url('/api/payments/khalti/callback')),
                'website_url' => config('services.khalti.website_url', url('/')),
                'amount' => (int)($amount * 100), // Convert to paisa (amount * 100)
                'purchase_order_id' => $transactionId,
                'purchase_order_name' => $productName,
            ];

            // Add additional data if provided
            if (!empty($additionalData)) {
                $payload = array_merge($payload, $additionalData);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Key ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/epayment/initiate/', $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['payment_url'])) {
                return [
                    'success' => true,
                    'payment_url' => $responseData['payment_url'],
                    'pidx' => $responseData['pidx'] ?? null,
                    'data' => $responseData,
                ];
            }

            return [
                'success' => false,
                'message' => $responseData['detail'] ?? 'Failed to initiate payment',
                'error' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Khalti payment initiation failed: ' . $e->getMessage());
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
            $response = Http::withHeaders([
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
        } catch (\Exception $e) {
            Log::error('Khalti payment verification failed: ' . $e->getMessage());
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

