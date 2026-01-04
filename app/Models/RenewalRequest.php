<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasBSTimestamps;

class RenewalRequest extends Model
{
    use HasFactory, HasBSTimestamps;

    protected $fillable = [
        'user_id',
        'user_phone_number',
        'vehicle_id',
        'payment_id',
        'vendor_id',
        'service_type',
        'status',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'dropoff_address',
        'dropoff_latitude',
        'dropoff_longitude',
        'pickup_date',
        'pickup_time_slot',
        'has_insurance',
        'fiscal_year_id',
        'tax_amount',
        'renewal_fee',
        'penalty_amount',
        'insurance_amount',
        'service_fee',
        'vat_amount',
        'total_amount',
        'payment_method',
        'payment_status',
        'assigned_at',
        'en_route_at',
        'started_at',
        'document_picked_up_at',
        'document_photo',
        'signature_photo',
        'at_dotm_at',
        'processing_complete_at',
        'completed_at',
        'delivered_at',
        'cancelled_at',
        'notes',
        'cancellation_reason',
    ];

    protected $casts = [
        'pickup_latitude' => 'decimal:8',
        'pickup_longitude' => 'decimal:8',
        'dropoff_latitude' => 'decimal:8',
        'dropoff_longitude' => 'decimal:8',
        'pickup_date' => 'date',
        'has_insurance' => 'boolean',
        'tax_amount' => 'decimal:2',
        'renewal_fee' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'insurance_amount' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'assigned_at' => 'datetime',
        'en_route_at' => 'datetime',
        'started_at' => 'datetime',
        'document_picked_up_at' => 'datetime',
        'at_dotm_at' => 'datetime',
        'processing_complete_at' => 'datetime',
        'completed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    // Status check methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAssigned(): bool
    {
        return $this->status === 'assigned';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canBeAssigned(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeStarted(): bool
    {
        return $this->status === 'assigned';
    }

    public function canBeCompleted(): bool
    {
        return $this->status === 'in_progress';
    }
}