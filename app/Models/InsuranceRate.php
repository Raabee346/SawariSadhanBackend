<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsuranceRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_type',
        'fuel_type',
        'capacity_value',
        'annual_premium',
        'fiscal_year_id',
        'notes',
    ];

    protected $casts = [
        'annual_premium' => 'decimal:2',
    ];

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Find matching insurance rate for given parameters
     * Finds exact match or closest capacity value with fallbacks
     */
    public static function findRate($fiscalYearId, $vehicleType, $fuelType, $engineCapacity)
    {
        // First try exact match
        $exact = static::where('fiscal_year_id', $fiscalYearId)
            ->where('vehicle_type', $vehicleType)
            ->where('fuel_type', $fuelType)
            ->where('capacity_value', $engineCapacity)
            ->first();

        if ($exact) {
            return $exact;
        }

        // If no exact match, find closest capacity value (less than or equal)
        $closest = static::where('fiscal_year_id', $fiscalYearId)
            ->where('vehicle_type', $vehicleType)
            ->where('fuel_type', $fuelType)
            ->where('capacity_value', '<=', $engineCapacity)
            ->orderBy('capacity_value', 'desc')
            ->first();

        if ($closest) {
            return $closest;
        }

        // If still no match, try without fiscal year constraint (use any fiscal year)
        $anyFiscalYear = static::where('vehicle_type', $vehicleType)
            ->where('fuel_type', $fuelType)
            ->where('capacity_value', '<=', $engineCapacity)
            ->orderBy('capacity_value', 'desc')
            ->orderBy('fiscal_year_id', 'desc')
            ->first();

        if ($anyFiscalYear) {
            return $anyFiscalYear;
        }

        // Last resort: try any capacity for same vehicle/fuel type
        return static::where('vehicle_type', $vehicleType)
            ->where('fuel_type', $fuelType)
            ->orderBy('capacity_value', 'desc')
            ->orderBy('fiscal_year_id', 'desc')
            ->first();
    }
}

