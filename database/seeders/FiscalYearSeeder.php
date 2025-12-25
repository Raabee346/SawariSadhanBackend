<?php

namespace Database\Seeders;

use App\Models\FiscalYear;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FiscalYearSeeder extends Seeder
{
    public function run(): void
    {
        $fiscalYears = [
            [
                'year' => '2080/81',
                'start_date' => Carbon::create(2023, 7, 16),
                'end_date' => Carbon::create(2024, 7, 15),
                'is_current' => false,
            ],
            [
                'year' => '2081/82',
                'start_date' => Carbon::create(2024, 7, 16),
                'end_date' => Carbon::create(2025, 7, 15),
                'is_current' => true,
            ],
            [
                'year' => '2082/83',
                'start_date' => Carbon::create(2025, 7, 16),
                'end_date' => Carbon::create(2026, 7, 15),
                'is_current' => false,
            ],
        ];

        foreach ($fiscalYears as $fy) {
            FiscalYear::firstOrCreate(
                ['year' => $fy['year']],
                $fy
            );
        }
    }
}

