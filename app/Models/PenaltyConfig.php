<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenaltyConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'duration_label',
        'days_from_expiry',
        'days_to',
        'penalty_percentage',
        'renewal_fee_penalty_percentage',
        'is_active',
    ];

    protected $casts = [
        'penalty_percentage' => 'decimal:2',
        'renewal_fee_penalty_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get penalty percentage for given days of delay
     */
    public static function getPenaltyPercentage($daysDelayed)
    {
        $config = static::where('is_active', true)
            ->where('days_from_expiry', '<=', $daysDelayed)
            ->where(function ($query) use ($daysDelayed) {
                $query->whereNull('days_to')
                      ->orWhere('days_to', '>=', $daysDelayed);
            })
            ->orderBy('days_from_expiry', 'desc')
            ->first();

        return $config ? $config->penalty_percentage : 0;
    }
}

