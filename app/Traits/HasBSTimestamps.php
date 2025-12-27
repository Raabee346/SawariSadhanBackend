<?php

namespace App\Traits;

use App\Services\NepalDateService;
use Carbon\Carbon;

trait HasBSTimestamps
{
    /**
     * Get the value of the model's "created at" timestamp.
     *
     * @return string|null
     */
    public function getCreatedAtAttribute($value)
    {
        if (!$value) {
            return null;
        }

        // If value is already a BS date string (YYYY-MM-DD format), return as is
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // Convert datetime/timestamp to BS format
        try {
            $carbon = $value instanceof Carbon ? $value : Carbon::parse($value);
            return NepalDateService::toBS($carbon);
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Get the value of the model's "updated at" timestamp.
     *
     * @return string|null
     */
    public function getUpdatedAtAttribute($value)
    {
        if (!$value) {
            return null;
        }

        // If value is already a BS date string (YYYY-MM-DD format), return as is
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // Convert datetime/timestamp to BS format
        try {
            $carbon = $value instanceof Carbon ? $value : Carbon::parse($value);
            return NepalDateService::toBS($carbon);
        } catch (\Exception $e) {
            return $value;
        }
    }


    /**
     * Serialize date fields to Y-m-d format (BS format)
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        // Convert to BS format
        try {
            $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
            return NepalDateService::toBS($carbon);
        } catch (\Exception $e) {
            return $date->format('Y-m-d');
        }
    }
}

