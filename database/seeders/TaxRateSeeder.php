<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\FiscalYear;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;

class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        $fiscalYear = FiscalYear::where('year', '2081/82')->first();
        if (!$fiscalYear) {
            $this->command->warn('Fiscal year 2081/82 not found. Please run FiscalYearSeeder first.');
            return;
        }

        $provinces = Province::all();
        
        foreach ($provinces as $province) {
            // Two-Wheeler Tax Rates - Petrol (comprehensive coverage)
            $this->createTaxRates($province, $fiscalYear, '2W', 'Petrol', [
                ['capacity' => 0, 'tax' => $this->getTwoWheelerRate($province->code, 125), 'renewal' => 300],
                ['capacity' => 50, 'tax' => $this->getTwoWheelerRate($province->code, 125), 'renewal' => 300],
                ['capacity' => 100, 'tax' => $this->getTwoWheelerRate($province->code, 125), 'renewal' => 300],
                ['capacity' => 125, 'tax' => $this->getTwoWheelerRate($province->code, 125), 'renewal' => 300],
                ['capacity' => 150, 'tax' => $this->getTwoWheelerRate($province->code, 150), 'renewal' => 300],
                ['capacity' => 175, 'tax' => $this->getTwoWheelerRate($province->code, 225), 'renewal' => 300],
                ['capacity' => 200, 'tax' => $this->getTwoWheelerRate($province->code, 225), 'renewal' => 300],
                ['capacity' => 225, 'tax' => $this->getTwoWheelerRate($province->code, 225), 'renewal' => 300],
                ['capacity' => 250, 'tax' => $this->getTwoWheelerRate($province->code, 400), 'renewal' => 300],
                ['capacity' => 300, 'tax' => $this->getTwoWheelerRate($province->code, 400), 'renewal' => 300],
                ['capacity' => 400, 'tax' => $this->getTwoWheelerRate($province->code, 400), 'renewal' => 300],
                ['capacity' => 500, 'tax' => $this->getTwoWheelerRate($province->code, 650), 'renewal' => 300],
                ['capacity' => 650, 'tax' => $this->getTwoWheelerRate($province->code, 650), 'renewal' => 300],
                ['capacity' => 800, 'tax' => $this->getTwoWheelerRate($province->code, 1000), 'renewal' => 300],
                ['capacity' => 1000, 'tax' => $this->getTwoWheelerRate($province->code, 1000), 'renewal' => 300],
            ]);

            // Two-Wheeler Tax Rates - Diesel (same as Petrol)
            $this->createTaxRates($province, $fiscalYear, '2W', 'Diesel', [
                ['capacity' => 0, 'tax' => $this->getTwoWheelerRate($province->code, 125), 'renewal' => 300],
                ['capacity' => 50, 'tax' => $this->getTwoWheelerRate($province->code, 125), 'renewal' => 300],
                ['capacity' => 100, 'tax' => $this->getTwoWheelerRate($province->code, 125), 'renewal' => 300],
                ['capacity' => 125, 'tax' => $this->getTwoWheelerRate($province->code, 125), 'renewal' => 300],
                ['capacity' => 150, 'tax' => $this->getTwoWheelerRate($province->code, 150), 'renewal' => 300],
                ['capacity' => 175, 'tax' => $this->getTwoWheelerRate($province->code, 225), 'renewal' => 300],
                ['capacity' => 200, 'tax' => $this->getTwoWheelerRate($province->code, 225), 'renewal' => 300],
                ['capacity' => 225, 'tax' => $this->getTwoWheelerRate($province->code, 225), 'renewal' => 300],
                ['capacity' => 250, 'tax' => $this->getTwoWheelerRate($province->code, 400), 'renewal' => 300],
                ['capacity' => 300, 'tax' => $this->getTwoWheelerRate($province->code, 400), 'renewal' => 300],
                ['capacity' => 400, 'tax' => $this->getTwoWheelerRate($province->code, 400), 'renewal' => 300],
                ['capacity' => 500, 'tax' => $this->getTwoWheelerRate($province->code, 650), 'renewal' => 300],
                ['capacity' => 650, 'tax' => $this->getTwoWheelerRate($province->code, 650), 'renewal' => 300],
                ['capacity' => 800, 'tax' => $this->getTwoWheelerRate($province->code, 1000), 'renewal' => 300],
                ['capacity' => 1000, 'tax' => $this->getTwoWheelerRate($province->code, 1000), 'renewal' => 300],
            ]);

            // Four-Wheeler Tax Rates - Petrol (comprehensive coverage)
            $this->createTaxRates($province, $fiscalYear, '4W', 'Petrol', [
                ['capacity' => 0, 'tax' => $this->getFourWheelerRate($province->code, 1000), 'renewal' => 500],
                ['capacity' => 800, 'tax' => $this->getFourWheelerRate($province->code, 1000), 'renewal' => 500],
                ['capacity' => 1000, 'tax' => $this->getFourWheelerRate($province->code, 1000), 'renewal' => 500],
                ['capacity' => 1200, 'tax' => $this->getFourWheelerRate($province->code, 1500), 'renewal' => 500],
                ['capacity' => 1500, 'tax' => $this->getFourWheelerRate($province->code, 1500), 'renewal' => 500],
                ['capacity' => 1800, 'tax' => $this->getFourWheelerRate($province->code, 2000), 'renewal' => 500],
                ['capacity' => 2000, 'tax' => $this->getFourWheelerRate($province->code, 2000), 'renewal' => 500],
                ['capacity' => 2200, 'tax' => $this->getFourWheelerRate($province->code, 2500), 'renewal' => 500],
                ['capacity' => 2500, 'tax' => $this->getFourWheelerRate($province->code, 2500), 'renewal' => 500],
                ['capacity' => 2800, 'tax' => $this->getFourWheelerRate($province->code, 3000), 'renewal' => 500],
                ['capacity' => 3000, 'tax' => $this->getFourWheelerRate($province->code, 3000), 'renewal' => 500],
                ['capacity' => 3500, 'tax' => $this->getFourWheelerRate($province->code, 3500), 'renewal' => 500],
                ['capacity' => 4000, 'tax' => $this->getFourWheelerRate($province->code, 4000), 'renewal' => 500],
            ]);

            // Four-Wheeler Tax Rates - Diesel (same as Petrol)
            $this->createTaxRates($province, $fiscalYear, '4W', 'Diesel', [
                ['capacity' => 0, 'tax' => $this->getFourWheelerRate($province->code, 1000), 'renewal' => 500],
                ['capacity' => 800, 'tax' => $this->getFourWheelerRate($province->code, 1000), 'renewal' => 500],
                ['capacity' => 1000, 'tax' => $this->getFourWheelerRate($province->code, 1000), 'renewal' => 500],
                ['capacity' => 1200, 'tax' => $this->getFourWheelerRate($province->code, 1500), 'renewal' => 500],
                ['capacity' => 1500, 'tax' => $this->getFourWheelerRate($province->code, 1500), 'renewal' => 500],
                ['capacity' => 1800, 'tax' => $this->getFourWheelerRate($province->code, 2000), 'renewal' => 500],
                ['capacity' => 2000, 'tax' => $this->getFourWheelerRate($province->code, 2000), 'renewal' => 500],
                ['capacity' => 2200, 'tax' => $this->getFourWheelerRate($province->code, 2500), 'renewal' => 500],
                ['capacity' => 2500, 'tax' => $this->getFourWheelerRate($province->code, 2500), 'renewal' => 500],
                ['capacity' => 2800, 'tax' => $this->getFourWheelerRate($province->code, 3000), 'renewal' => 500],
                ['capacity' => 3000, 'tax' => $this->getFourWheelerRate($province->code, 3000), 'renewal' => 500],
                ['capacity' => 3500, 'tax' => $this->getFourWheelerRate($province->code, 3500), 'renewal' => 500],
                ['capacity' => 4000, 'tax' => $this->getFourWheelerRate($province->code, 4000), 'renewal' => 500],
            ]);

            // Electric Two-Wheeler - using specific Watt values
            $this->createTaxRates($province, $fiscalYear, '2W', 'Electric', [
                ['capacity' => 0, 'tax' => 2000, 'renewal' => 300],
                ['capacity' => 500, 'tax' => 2000, 'renewal' => 300],
                ['capacity' => 1000, 'tax' => 2000, 'renewal' => 300],
                ['capacity' => 1500, 'tax' => 2500, 'renewal' => 300],
                ['capacity' => 2000, 'tax' => 3000, 'renewal' => 300],
            ]);

            // Electric Four-Wheeler - using specific kW values
            $this->createTaxRates($province, $fiscalYear, '4W', 'Electric', [
                ['capacity' => 0, 'tax' => 5000, 'renewal' => 500],
                ['capacity' => 50, 'tax' => 5000, 'renewal' => 500],
                ['capacity' => 125, 'tax' => 15000, 'renewal' => 500],
                ['capacity' => 200, 'tax' => 20000, 'renewal' => 500],
                ['capacity' => 250, 'tax' => 30000, 'renewal' => 500],
            ]);

            // Commercial Vehicle Tax Rates (Presumptive rates)
            $this->createTaxRates($province, $fiscalYear, 'Commercial', 'Petrol', [
                ['capacity' => 0, 'tax' => 5500, 'renewal' => 500], // Up to 1300 CC
                ['capacity' => 1300, 'tax' => 5500, 'renewal' => 500],
                ['capacity' => 2000, 'tax' => 6000, 'renewal' => 500],
                ['capacity' => 2900, 'tax' => 6500, 'renewal' => 500],
                ['capacity' => 4000, 'tax' => 8000, 'renewal' => 500],
                ['capacity' => 5000, 'tax' => 9000, 'renewal' => 500],
            ]);

            $this->createTaxRates($province, $fiscalYear, 'Commercial', 'Diesel', [
                ['capacity' => 0, 'tax' => 5500, 'renewal' => 500],
                ['capacity' => 1300, 'tax' => 5500, 'renewal' => 500],
                ['capacity' => 2000, 'tax' => 6000, 'renewal' => 500],
                ['capacity' => 2900, 'tax' => 6500, 'renewal' => 500],
                ['capacity' => 4000, 'tax' => 8000, 'renewal' => 500],
                ['capacity' => 5000, 'tax' => 9000, 'renewal' => 500],
            ]);

            // Heavy Vehicle Tax Rates (Flat rates)
            $this->createTaxRates($province, $fiscalYear, 'Heavy', 'Diesel', [
                ['capacity' => 0, 'tax' => 15500, 'renewal' => 500], // Dozer, Excavator, Loader, Roller, Crane
                ['capacity' => 5000, 'tax' => 15500, 'renewal' => 500],
                ['capacity' => 10000, 'tax' => 21000, 'renewal' => 500],
            ]);

            $this->createTaxRates($province, $fiscalYear, 'Heavy', 'Petrol', [
                ['capacity' => 0, 'tax' => 15500, 'renewal' => 500],
                ['capacity' => 5000, 'tax' => 15500, 'renewal' => 500],
                ['capacity' => 10000, 'tax' => 21000, 'renewal' => 500],
            ]);
        }
    }

    private function createTaxRates($province, $fiscalYear, $vehicleType, $fuelType, $rates)
    {
        foreach ($rates as $rate) {
            TaxRate::firstOrCreate(
                [
                    'province_id' => $province->id,
                    'fiscal_year_id' => $fiscalYear->id,
                    'vehicle_type' => $vehicleType,
                    'fuel_type' => $fuelType,
                    'capacity_value' => $rate['capacity'],
                ],
                [
                    'annual_tax_amount' => $rate['tax'],
                    'renewal_fee' => $rate['renewal'],
                ]
            );
        }
    }

    private function getTwoWheelerRate($provinceCode, $cc)
    {
        $rates = [
            'BAGMATI' => [
                125 => 3000, 150 => 5000, 225 => 6500, 400 => 12000, 650 => 25000, 1000 => 35000
            ],
            'KOSHI' => [
                125 => 2800, 150 => 4500, 225 => 4500, 400 => 9000, 650 => 9000, 1000 => 16500
            ],
            'MADHESH' => [
                125 => 2700, 150 => 4500, 225 => 6000, 400 => 10000, 650 => 10000, 1000 => 17000
            ],
            'GANDAKI' => [
                125 => 2600, 150 => 4500, 225 => 4500, 400 => 9500, 650 => 9500, 1000 => 20000
            ],
            'LUMBINI' => [
                125 => 2800, 150 => 4500, 225 => 6000, 400 => 10000, 650 => 10000, 1000 => 18000
            ],
            'KARNALI' => [
                125 => 2500, 150 => 4000, 225 => 4000, 400 => 8000, 650 => 8000, 1000 => 15000
            ],
            'SUDURPASHCHIM' => [
                125 => 2500, 150 => 4500, 225 => 5500, 400 => 8000, 650 => 8000, 1000 => 9000
            ],
        ];

        $provinceRates = $rates[$provinceCode] ?? $rates['BAGMATI'];
        
        if ($cc <= 125) return $provinceRates[125];
        if ($cc <= 150) return $provinceRates[150];
        if ($cc <= 225) return $provinceRates[225];
        if ($cc <= 400) return $provinceRates[400];
        if ($cc <= 650) return $provinceRates[650];
        return $provinceRates[1000];
    }

    private function getFourWheelerRate($provinceCode, $cc)
    {
        $rates = [
            'BAGMATI' => [
                1000 => 22000, 1500 => 25000, 2000 => 27000, 2500 => 37000, 3000 => 50000, 3500 => 65000, 4000 => 70000
            ],
            'KOSHI' => [
                1000 => 21000, 1500 => 23500, 2000 => 25500, 2500 => 35500, 3000 => 41000, 3500 => 58500, 4000 => 58500
            ],
            'MADHESH' => [
                1000 => 22000, 1500 => 25000, 2000 => 27000, 2500 => 37000, 3000 => 50000, 3500 => 60500, 4000 => 60500
            ],
            'GANDAKI' => [
                1000 => 22000, 1500 => 25000, 2000 => 27000, 2500 => 37000, 3000 => 50000, 3500 => 60000, 4000 => 65000
            ],
            'LUMBINI' => [
                1000 => 22000, 1500 => 25000, 2000 => 27000, 2500 => 37000, 3000 => 50000, 3500 => 60000, 4000 => 65000
            ],
            'KARNALI' => [
                1000 => 20000, 1500 => 23000, 2000 => 25000, 2500 => 35000, 3000 => 40000, 3500 => 55000, 4000 => 60000
            ],
            'SUDURPASHCHIM' => [
                1000 => 20000, 1500 => 23000, 2000 => 25000, 2500 => 35000, 3000 => 40000, 3500 => 55000, 4000 => 60000
            ],
        ];

        $provinceRates = $rates[$provinceCode] ?? $rates['BAGMATI'];
        
        if ($cc <= 1000) return $provinceRates[1000];
        if ($cc <= 1500) return $provinceRates[1500];
        if ($cc <= 2000) return $provinceRates[2000];
        if ($cc <= 2500) return $provinceRates[2500];
        if ($cc <= 3000) return $provinceRates[3000];
        if ($cc <= 3500) return $provinceRates[3500];
        return $provinceRates[4000];
    }
}
