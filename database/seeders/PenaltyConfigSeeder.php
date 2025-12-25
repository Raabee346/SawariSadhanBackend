<?php

namespace Database\Seeders;

use App\Models\PenaltyConfig;
use Illuminate\Database\Seeder;

class PenaltyConfigSeeder extends Seeder
{
    public function run(): void
    {
        $penalties = [
            [
                'duration_label' => 'First 30 Days',
                'days_from_expiry' => 0,
                'days_to' => 30,
                'penalty_percentage' => 5.00,
                'renewal_fee_penalty_percentage' => 100.00,
                'is_active' => true,
            ],
            [
                'duration_label' => 'Up to 45 Days',
                'days_from_expiry' => 31,
                'days_to' => 45,
                'penalty_percentage' => 10.00,
                'renewal_fee_penalty_percentage' => 100.00,
                'is_active' => true,
            ],
            [
                'duration_label' => 'Within the same Fiscal Year',
                'days_from_expiry' => 46,
                'days_to' => null,
                'penalty_percentage' => 20.00,
                'renewal_fee_penalty_percentage' => 100.00,
                'is_active' => true,
            ],
            [
                'duration_label' => 'Beyond the same Fiscal Year (Up to 5 Years)',
                'days_from_expiry' => 365,
                'days_to' => null,
                'penalty_percentage' => 32.00,
                'renewal_fee_penalty_percentage' => 100.00,
                'is_active' => true,
            ],
        ];

        foreach ($penalties as $penalty) {
            PenaltyConfig::firstOrCreate(
                ['duration_label' => $penalty['duration_label']],
                $penalty
            );
        }
    }
}

