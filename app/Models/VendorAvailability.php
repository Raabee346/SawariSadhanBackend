<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class VendorAvailability extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'day_of_week',
        'is_available',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    /**
     * Get the vendor that owns the availability.
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Check if vendor is available at a given time.
     */
    public function isAvailableAt($time = null)
    {
        if (!$this->is_available) {
            return false;
        }

        if (!$time) {
            $time = Carbon::now();
        }

        $timeToCheck = Carbon::parse($time)->format('H:i:s');
        $startTime = Carbon::parse($this->start_time)->format('H:i:s');
        $endTime = Carbon::parse($this->end_time)->format('H:i:s');

        return $timeToCheck >= $startTime && $timeToCheck <= $endTime;
    }

    /**
     * Get formatted time range.
     */
    public function getTimeRangeAttribute()
    {
        if (!$this->start_time || !$this->end_time) {
            return 'Not set';
        }

        return Carbon::parse($this->start_time)->format('h:i A') . ' - ' . 
               Carbon::parse($this->end_time)->format('h:i A');
    }
}

