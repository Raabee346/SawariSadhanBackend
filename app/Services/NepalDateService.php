<?php

namespace App\Services;

use Carbon\Carbon;

class NepalDateService
{
    /**
     * Convert AD date to BS (Bikram Sambat) date
     * Returns format: YYYY-MM-DD (BS)
     */
    public static function toBS(Carbon $adDate): string
    {
        $year = (int)$adDate->format('Y');
        $month = (int)$adDate->format('m');
        $day = (int)$adDate->format('d');

        // BS year is approximately 56-57 years ahead of AD
        $bsYear = $year + 56;
        
        // Approximate month conversion (Nepal months don't align perfectly with AD months)
        // This is a simplified conversion. For production, use a proper BS calendar library
        $bsMonth = $month;
        $bsDay = $day;

        // Adjust for month differences (BS months start mid-month of AD)
        if ($day > 15) {
            $bsMonth = $month;
        } else {
            $bsMonth = $month - 1;
            if ($bsMonth < 1) {
                $bsMonth = 12;
                $bsYear--;
            }
        }

        // Ensure valid ranges
        if ($bsMonth < 1) $bsMonth = 1;
        if ($bsMonth > 12) $bsMonth = 12;
        if ($bsDay < 1) $bsDay = 1;
        if ($bsDay > 32) $bsDay = 32;

        return sprintf('%04d-%02d-%02d', $bsYear, $bsMonth, $bsDay);
    }

    /**
     * Convert BS date to AD date
     * Accepts format: YYYY-MM-DD (BS)
     */
    public static function toAD(string $bsDate): Carbon
    {
        $parts = explode('-', $bsDate);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Invalid BS date format. Expected YYYY-MM-DD');
        }

        $bsYear = (int)$parts[0];
        $bsMonth = (int)$parts[1];
        $bsDay = (int)$parts[2];

        // AD year is approximately 56-57 years behind BS
        $adYear = $bsYear - 56;
        
        // Approximate month conversion
        $adMonth = $bsMonth;
        $adDay = $bsDay;

        // Adjust for month differences
        if ($bsDay > 15) {
            $adMonth = $bsMonth;
        } else {
            $adMonth = $bsMonth + 1;
            if ($adMonth > 12) {
                $adMonth = 1;
                $adYear++;
            }
        }

        // Ensure valid ranges
        if ($adMonth < 1) $adMonth = 1;
        if ($adMonth > 12) $adMonth = 12;
        if ($adDay < 1) $adDay = 1;
        if ($adDay > 31) $adDay = 31;

        try {
            return Carbon::create($adYear, $adMonth, $adDay);
        } catch (\Exception $e) {
            // Fallback to current date if conversion fails
            return Carbon::today();
        }
    }
}

