<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasBSTimestamps;

class Payment extends Model
{
    use HasFactory, HasBSTimestamps;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'fiscal_year_id',
        'tax_amount',
        'renewal_fee',
        'penalty_amount',
        'insurance_amount',
        'total_amount',
        'payment_status',
        'payment_method',
        'transaction_id',
        'payment_details',
        'payment_date',
    ];

    protected $casts = [
        'tax_amount' => 'decimal:2',
        'renewal_fee' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'insurance_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_date' => 'date',
        'payment_details' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function isCompleted(): bool
    {
        return $this->payment_status === 'completed';
    }

}

