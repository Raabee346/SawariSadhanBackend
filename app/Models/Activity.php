<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'activity_type',
        'related_id',
        'related_type',
        'title',
        'message',
        'activity_date',
        'metadata',
    ];

    protected $casts = [
        'activity_date' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the activity.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the vehicle associated with the activity.
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Get the related model (Payment or RenewalRequest).
     */
    public function related()
    {
        return $this->morphTo('related', 'related_type', 'related_id');
    }

    /**
     * Check if activity is a payment type.
     */
    public function isPayment(): bool
    {
        return $this->activity_type === 'payment';
    }

    /**
     * Check if activity is a service type.
     */
    public function isService(): bool
    {
        return $this->activity_type === 'service';
    }

    /**
     * Create a payment activity
     */
    public static function createPaymentActivity($payment)
    {
        $vehicle = $payment->vehicle;
        $vehicleInfo = $vehicle ? $vehicle->registration_number : 'Unknown Vehicle';
        
        return self::create([
            'user_id' => $payment->user_id,
            'vehicle_id' => $payment->vehicle_id,
            'activity_type' => 'payment',
            'related_id' => $payment->id,
            'related_type' => 'App\Models\Payment',
            'title' => 'Payment Successful',
            'message' => 'NPR ' . number_format($payment->total_amount, 2) . ' paid for vehicle tax.',
            'activity_date' => $payment->payment_date ?? $payment->created_at ?? now(),
            'metadata' => [
                'amount' => $payment->total_amount,
                'payment_method' => $payment->payment_method,
                'transaction_id' => $payment->transaction_id,
                'vehicle_registration' => $vehicleInfo,
            ],
        ]);
    }

    /**
     * Create a service (bluebook renewal) activity
     */
    public static function createServiceActivity($renewalRequest)
    {
        $vehicle = $renewalRequest->vehicle;
        $vehicleInfo = $vehicle ? $vehicle->registration_number : 'Unknown Vehicle';
        
        return self::create([
            'user_id' => $renewalRequest->user_id,
            'vehicle_id' => $renewalRequest->vehicle_id,
            'activity_type' => 'service',
            'related_id' => $renewalRequest->id,
            'related_type' => 'App\Models\RenewalRequest',
            'title' => 'Bluebook Renewal',
            'message' => 'Bluebook renewal completed successfully for ' . $vehicleInfo . '.',
            'activity_date' => $renewalRequest->completed_at ?? $renewalRequest->delivered_at ?? $renewalRequest->updated_at ?? now(),
            'metadata' => [
                'service_type' => $renewalRequest->service_type,
                'vehicle_registration' => $vehicleInfo,
                'completed_at' => $renewalRequest->completed_at?->toDateTimeString(),
            ],
        ]);
    }
}
