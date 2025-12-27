<?php

namespace App\Traits;

use App\Services\NepalDateService;
use Carbon\Carbon;

trait HasNepalDates
{
    /**
     * Convert AD date to BS date string
     */
    protected function toBSDate($date): ?string
    {
        if (!$date) {
            return null;
        }

        if ($date instanceof Carbon) {
            return NepalDateService::toBS($date);
        }

        if (is_string($date)) {
            try {
                $carbon = Carbon::parse($date);
                return NepalDateService::toBS($carbon);
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Parse date input - always treats input as BS date
     */
    protected function parseBSDate($dateInput): Carbon
    {
        if (!$dateInput) {
            throw new \InvalidArgumentException('Date is required');
        }

        // Always treat input as BS date and convert to AD for storage
        try {
            return NepalDateService::toAD($dateInput);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid BS date format. Expected YYYY-MM-DD (BS)');
        }
    }
}