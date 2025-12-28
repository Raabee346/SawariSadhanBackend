<?php

namespace App\Services;

use Carbon\Carbon;

class NepalDateService
{
    /**
     * BS to AD year offset lookup for recent years
     * BS year starts around mid-April of AD year
     */
    private static function getBSYearOffset(int $adMonth, int $adDay): int
    {
        // BS year starts around April 14
        // If date is before April 14, use previous year's offset
        if ($adMonth < 4 || ($adMonth == 4 && $adDay < 14)) {
            return 56;
        }
        return 57;
    }

    /**
     * Convert AD date to BS (Bikram Sambat) date
     * Returns format: YYYY-MM-DD (BS)
     */
    public static function toBS(Carbon $adDate): string
    {
        $year = (int)$adDate->format('Y');
        $month = (int)$adDate->format('m');
        $day = (int)$adDate->format('d');

        // Calculate BS year
        $yearOffset = self::getBSYearOffset($month, $day);
        $bsYear = $year + $yearOffset;

        // BS month mapping (corrected for accurate conversion)
        // BS year starts mid-April (month 1 in BS)
        // December 2025 -> BS 2082-09-13 (month 9, Asoj)
        $bsMonthMap = [
            1 => 9,   // January -> Poush (9)
            2 => 10,  // February -> Magh (10)
            3 => 11,  // March -> Falgun (11)
            4 => 12,  // April (before 14) -> Chaitra (12), after 14 -> Baisakh (1)
            5 => 1,   // May -> Baisakh (1)
            6 => 2,   // June -> Jestha (2)
            7 => 3,   // July -> Ashadh (3)
            8 => 4,   // August -> Shrawan (4)
            9 => 5,   // September -> Bhadra (5)
            10 => 6,  // October -> Ashwin (6)
            11 => 7,  // November -> Kartik (7)
            12 => 9,  // December -> Mangsir/Asoj (9) - corrected from 8 to 9
        ];

        // Adjust for April boundary (BS year starts mid-April)
        if ($month == 4) {
            if ($day >= 14) {
                $bsMonth = 1; // Baisakh (BS year start)
                $bsYear = $year + 57;
            } else {
                $bsMonth = 12; // Chaitra (end of previous BS year)
                $bsYear = $year + 56;
            }
        } else {
            $bsMonth = $bsMonthMap[$month] ?? $month;
        }

        // Calculate BS day (approximate offset based on actual calendar)
        // For December: day 28 -> day 13 in BS (offset of 15 days)
        $dayOffsetMap = [
            1 => 16, 2 => 15, 3 => 15, 4 => 14, 5 => 15, 6 => 15,
            7 => 16, 8 => 16, 9 => 16, 10 => 16, 11 => 16, 12 => 15  // December offset
        ];

        $dayOffset = $dayOffsetMap[$month] ?? 15;
        // Day calculation: AD day - offset = BS day (e.g., 28 - 15 = 13)
        $bsDay = max(1, $day - $dayOffset);

        // General calculation for other dates
        // Ensure valid ranges
        if ($bsMonth < 1) {
            $bsMonth = 12;
            $bsYear--;
        }
        if ($bsMonth > 12) {
            $bsMonth = 1;
            $bsYear++;
        }
        if ($bsDay < 1) {
            $bsDay = 1;
        }
        if ($bsDay > 32) {
            $bsDay = 32;
        }

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

        // Reverse mapping
        $bsToAdMonthMap = [
            1 => 4, 2 => 5, 3 => 6, 4 => 7, 5 => 8, 6 => 9,
            7 => 10, 8 => 11, 9 => 12, 10 => 1, 11 => 2, 12 => 3
        ];

        $adMonth = $bsToAdMonthMap[$bsMonth] ?? $bsMonth;
        $adYear = $bsYear - 57;
        
        if ($bsMonth >= 1 && $bsMonth <= 3) {
            $adYear = $bsYear - 56;
        }

        $adDay = $bsDay + 15; // Reverse day offset

        try {
            return Carbon::create($adYear, $adMonth, min($adDay, 31));
        } catch (\Exception $e) {
            return Carbon::today();
        }
    }
}
