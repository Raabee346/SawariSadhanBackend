<?php

namespace Database\Seeders;

use App\Models\FiscalYear;
use App\Models\InsuranceRate;
use Illuminate\Database\Seeder;

class InsuranceRateSeeder extends Seeder
{
    public function run(): void
    {
        $fiscalYear = FiscalYear::where('year', '2081/82')->first();
        if (!$fiscalYear) {
            $this->command->warn('Fiscal year 2081/82 not found. Please run FiscalYearSeeder first.');
            return;
        }

        // Two-Wheeler Insurance (Petrol) - comprehensive coverage
        $this->createInsuranceRates($fiscalYear, '2W', 'Petrol', [
            ['capacity' => 0, 'premium' => 1715], // Up to 149
            ['capacity' => 50, 'premium' => 1715],
            ['capacity' => 100, 'premium' => 1715],
            ['capacity' => 149, 'premium' => 1715],
            ['capacity' => 150, 'premium' => 1941], // 150-250
            ['capacity' => 200, 'premium' => 1941],
            ['capacity' => 250, 'premium' => 1941],
            ['capacity' => 251, 'premium' => 2167], // Above 250
            ['capacity' => 300, 'premium' => 2167],
            ['capacity' => 400, 'premium' => 2167],
            ['capacity' => 500, 'premium' => 2167],
        ]);

        // Two-Wheeler Insurance (Diesel) - same as Petrol
        $this->createInsuranceRates($fiscalYear, '2W', 'Diesel', [
            ['capacity' => 0, 'premium' => 1715],
            ['capacity' => 50, 'premium' => 1715],
            ['capacity' => 100, 'premium' => 1715],
            ['capacity' => 149, 'premium' => 1715],
            ['capacity' => 150, 'premium' => 1941],
            ['capacity' => 200, 'premium' => 1941],
            ['capacity' => 250, 'premium' => 1941],
            ['capacity' => 251, 'premium' => 2167],
            ['capacity' => 300, 'premium' => 2167],
            ['capacity' => 400, 'premium' => 2167],
            ['capacity' => 500, 'premium' => 2167],
        ]);

        // Four-Wheeler Insurance (Petrol) - comprehensive coverage
        $this->createInsuranceRates($fiscalYear, '4W', 'Petrol', [
            ['capacity' => 0, 'premium' => 7365], // Up to 1000
            ['capacity' => 800, 'premium' => 7365],
            ['capacity' => 1000, 'premium' => 7365],
            ['capacity' => 1001, 'premium' => 8495], // 1001-1600
            ['capacity' => 1200, 'premium' => 8495],
            ['capacity' => 1500, 'premium' => 8495],
            ['capacity' => 1600, 'premium' => 8495],
            ['capacity' => 1601, 'premium' => 10755], // Above 1600
            ['capacity' => 2000, 'premium' => 10755],
            ['capacity' => 2500, 'premium' => 10755],
            ['capacity' => 3000, 'premium' => 10755],
        ]);

        // Four-Wheeler Insurance (Diesel) - same as Petrol
        $this->createInsuranceRates($fiscalYear, '4W', 'Diesel', [
            ['capacity' => 0, 'premium' => 7365],
            ['capacity' => 800, 'premium' => 7365],
            ['capacity' => 1000, 'premium' => 7365],
            ['capacity' => 1001, 'premium' => 8495],
            ['capacity' => 1200, 'premium' => 8495],
            ['capacity' => 1500, 'premium' => 8495],
            ['capacity' => 1600, 'premium' => 8495],
            ['capacity' => 1601, 'premium' => 10755],
            ['capacity' => 2000, 'premium' => 10755],
            ['capacity' => 2500, 'premium' => 10755],
            ['capacity' => 3000, 'premium' => 10755],
        ]);

        // Electric Two-Wheeler Insurance - using specific Watt values
        $this->createInsuranceRates($fiscalYear, '2W', 'Electric', [
            ['capacity' => 0, 'premium' => 1715], // Up to 800W
            ['capacity' => 500, 'premium' => 1715],
            ['capacity' => 800, 'premium' => 1715],
            ['capacity' => 801, 'premium' => 1945], // 801-1200W
            ['capacity' => 1000, 'premium' => 1945],
            ['capacity' => 1200, 'premium' => 1945],
            ['capacity' => 1201, 'premium' => 2167], // Above 1200W
            ['capacity' => 1500, 'premium' => 2167],
            ['capacity' => 2000, 'premium' => 2167],
        ]);

        // Electric Four-Wheeler Insurance - using specific kW values
        $this->createInsuranceRates($fiscalYear, '4W', 'Electric', [
            ['capacity' => 0, 'premium' => 7365], // Up to 20kW
            ['capacity' => 10, 'premium' => 7365],
            ['capacity' => 20, 'premium' => 7365],
            ['capacity' => 21, 'premium' => 8495], // Above 20kW
            ['capacity' => 50, 'premium' => 8495],
            ['capacity' => 100, 'premium' => 8495],
        ]);

        // Commercial Vehicle Insurance (Petrol)
        $this->createInsuranceRates($fiscalYear, 'Commercial', 'Petrol', [
            ['capacity' => 0, 'premium' => 8495], // Similar to 4W rates
            ['capacity' => 1000, 'premium' => 8495],
            ['capacity' => 1600, 'premium' => 10755],
            ['capacity' => 3000, 'premium' => 10755],
        ]);

        // Commercial Vehicle Insurance (Diesel)
        $this->createInsuranceRates($fiscalYear, 'Commercial', 'Diesel', [
            ['capacity' => 0, 'premium' => 8495],
            ['capacity' => 1000, 'premium' => 8495],
            ['capacity' => 1600, 'premium' => 10755],
            ['capacity' => 3000, 'premium' => 10755],
        ]);

        // Heavy Vehicle Insurance (Diesel)
        $this->createInsuranceRates($fiscalYear, 'Heavy', 'Diesel', [
            ['capacity' => 0, 'premium' => 10755], // Higher premium for heavy vehicles
            ['capacity' => 3000, 'premium' => 10755],
            ['capacity' => 5000, 'premium' => 15000],
        ]);

        // Heavy Vehicle Insurance (Petrol)
        $this->createInsuranceRates($fiscalYear, 'Heavy', 'Petrol', [
            ['capacity' => 0, 'premium' => 10755],
            ['capacity' => 3000, 'premium' => 10755],
            ['capacity' => 5000, 'premium' => 15000],
        ]);
    }

    private function createInsuranceRates($fiscalYear, $vehicleType, $fuelType, $rates)
    {
        foreach ($rates as $rate) {
            InsuranceRate::firstOrCreate(
                [
                    'fiscal_year_id' => $fiscalYear->id,
                    'vehicle_type' => $vehicleType,
                    'fuel_type' => $fuelType,
                    'capacity_value' => $rate['capacity'],
                ],
                [
                    'annual_premium' => $rate['premium'],
                ]
            );
        }
    }
}
