<?php

namespace App\Http\Controllers;

use App\Models\FiscalYear;
use App\Services\NepalDateService;
use Illuminate\Http\Request;

class FiscalYearController extends Controller
{
    /**
     * Get all fiscal years with BS dates
     */
    public function index()
    {
        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get()->map(function ($fy) {
            return [
                'id' => $fy->id,
                'year' => $fy->year,
                'start_date' => $fy->start_date->format('Y-m-d'),
                'start_date_bs' => NepalDateService::toBS($fy->start_date),
                'end_date' => $fy->end_date->format('Y-m-d'),
                'end_date_bs' => NepalDateService::toBS($fy->end_date),
                'is_current' => $fy->is_current,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $fiscalYears,
        ]);
    }

    /**
     * Get current fiscal year
     */
    public function current()
    {
        $fiscalYear = FiscalYear::where('is_current', true)->first();

        if (!$fiscalYear) {
            return response()->json([
                'success' => false,
                'message' => 'No current fiscal year found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $fiscalYear->id,
                'year' => $fiscalYear->year,
                'start_date' => $fiscalYear->start_date->format('Y-m-d'),
                'start_date_bs' => NepalDateService::toBS($fiscalYear->start_date),
                'end_date' => $fiscalYear->end_date->format('Y-m-d'),
                'end_date_bs' => NepalDateService::toBS($fiscalYear->end_date),
                'is_current' => $fiscalYear->is_current,
            ],
        ]);
    }
}

