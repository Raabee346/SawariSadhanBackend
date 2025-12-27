<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasBSTimestamps;

class TaxRate extends Model
{
    use HasFactory, HasBSTimestamps;

    protected $fillable = [
        'province_id',
        'fiscal_year_id',
        'vehicle_type',
        'fuel_type',
        'capacity_value',
        'annual_tax_amount',
        'renewal_fee',
        'notes',
    ];

    protected $casts = [
        'annual_tax_amount' => 'decimal:2',
        'renewal_fee' => 'decimal:2',
    ];

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Find matching tax rate for given parameters
     * Finds exact match or closest capacity value
     */
    public static function findRate($provinceId, $fiscalYearId, $vehicleType, $fuelType, $engineCapacity)
    {
        // First try exact match
        $exact = static::where('province_id', $provinceId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('vehicle_type', $vehicleType)
            ->where('fuel_type', $fuelType)
            ->where('capacity_value', $engineCapacity)
            ->first();

        if ($exact) {
            return $exact;
        }

        // If no exact match, find closest capacity value (less than or equal)
        $closest = static::where('province_id', $provinceId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('vehicle_type', $vehicleType)
            ->where('fuel_type', $fuelType)
            ->where('capacity_value', '<=', $engineCapacity)
            ->orderBy('capacity_value', 'desc')
            ->first();

        if ($closest) {
            return $closest;
        }

        // If still no match, try without fiscal year constraint (use any fiscal year)
        $anyFiscalYear = static::where('province_id', $provinceId)
            ->where('vehicle_type', $vehicleType)
            ->where('fuel_type', $fuelType)
            ->where('capacity_value', '<=', $engineCapacity)
            ->orderBy('capacity_value', 'desc')
            ->orderBy('fiscal_year_id', 'desc')
            ->first();

        if ($anyFiscalYear) {
            return $anyFiscalYear;
        }

        // Last resort: try any province with same vehicle/fuel type
        return static::where('vehicle_type', $vehicleType)
            ->where('fuel_type', $fuelType)
            ->where('capacity_value', '<=', $engineCapacity)
            ->orderBy('capacity_value', 'desc')
            ->orderBy('fiscal_year_id', 'desc')
            ->first();
    }

}

