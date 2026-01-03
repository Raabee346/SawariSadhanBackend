<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasBSTimestamps;

class PenaltyConfig extends Model
{
    use HasFactory, HasBSTimestamps;

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
     * 
     * @param int $daysDelayed Days after grace period (90 days) that the vehicle is delayed
     * @return float Penalty percentage (0 if no config found or days_delayed <= 0)
     * 
     * Logic:
     * - Finds the most specific config that matches the days_delayed
     * - Configs are matched by: days_from_expiry <= daysDelayed AND (days_to IS NULL OR days_to >= daysDelayed)
     * - Returns the config with the highest days_from_expiry (most specific match)
     */
    public static function getPenaltyPercentage($daysDelayed)
    {
        if ($daysDelayed <= 0) {
            return 0;
        }

        $config = static::where('is_active', true)
            ->where('days_from_expiry', '<=', $daysDelayed)
            ->where(function ($query) use ($daysDelayed) {
                $query->whereNull('days_to')
                      ->orWhere('days_to', '>=', $daysDelayed);
            })
            ->orderBy('days_from_expiry', 'desc') // Get the most specific (highest) matching config
            ->first();

        if (!$config) {
            \Log::warning('No penalty config found for days_delayed', [
                'days_delayed' => $daysDelayed,
                'available_configs' => static::where('is_active', true)
                    ->select('id', 'duration_label', 'days_from_expiry', 'days_to', 'penalty_percentage')
                    ->orderBy('days_from_expiry', 'asc')
                    ->get()
                    ->toArray(),
            ]);
            return 0;
        }

        return (float)$config->penalty_percentage;
    }

}

