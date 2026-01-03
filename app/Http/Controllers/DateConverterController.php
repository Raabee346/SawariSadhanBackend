<?php

namespace App\Http\Controllers;

use App\Services\NepaliDate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class DateConverterController extends Controller
{
    /**
     * Convert BS date to AD date and calculate expiry date
     * POST /api/convert-date
     * Body: { "bs_date": "2082-09-19" }
     * Returns: { "success": true, "ad_date": "2026-01-03", "expiry_date": "2027-01-03" }
     */
    public function convertDate(Request $request): JsonResponse
    {
        $request->validate([
            'bs_date' => 'required|string|regex:/^\d{4}-\d{2}-\d{2}$/',
        ]);

        try {
            $bsDate = $request->input('bs_date');
            
            // Parse BS date
            $parts = explode('-', $bsDate);
            $bsYear = (int)$parts[0];
            $bsMonth = (int)$parts[1];
            $bsDay = (int)$parts[2];
            
            // Convert BS to AD using NepaliDate service
            $nepaliDate = new NepaliDate();
            $adDateStr = $nepaliDate->convertBsToAd($bsDate);
            
            // Parse AD date string to Carbon for expiry calculation
            $adDate = Carbon::createFromFormat('Y-m-d', $adDateStr);
            
            // Calculate expiry date (AD date + 1 year)
            $expiryDate = $adDate->copy()->addYear();
            
            return response()->json([
                'success' => true,
                'bs_date' => $bsDate,
                'ad_date' => $adDate->format('Y-m-d'),
                'expiry_date' => $expiryDate->format('Y-m-d'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid BS date format. Expected YYYY-MM-DD',
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error converting date',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Convert AD date to BS date
     * POST /api/convert-date-ad-to-bs
     * Body: { "ad_date": "2026-01-03" }
     * Returns: { "success": true, "bs_date": "2082-09-19" }
     */
    public function convertAdToBs(Request $request): JsonResponse
    {
        $request->validate([
            'ad_date' => 'required|string|regex:/^\d{4}-\d{2}-\d{2}$/',
        ]);

        try {
            $adDateStr = $request->input('ad_date');
            
            // Parse AD date
            $adDate = Carbon::createFromFormat('Y-m-d', $adDateStr);
            $year = (int)$adDate->format('Y');
            $month = (int)$adDate->format('m');
            $day = (int)$adDate->format('d');
            
            // Convert AD to BS using NepaliDate service
            $nepaliDate = new NepaliDate();
            $bsDate = $nepaliDate->get_nepali_date($year, $month, $day);
            
            // Format BS date as YYYY-MM-DD
            $bsDateStr = sprintf('%04d-%02d-%02d', $bsDate['y'], $bsDate['m'], $bsDate['d']);
            
            return response()->json([
                'success' => true,
                'ad_date' => $adDateStr,
                'bs_date' => $bsDateStr,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid AD date format. Expected YYYY-MM-DD',
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error converting date',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

