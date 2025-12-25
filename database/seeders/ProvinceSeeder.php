<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    public function run(): void
    {
        $provinces = [
            ['name' => 'Koshi', 'code' => 'KOSHI', 'number' => 1],
            ['name' => 'Madhesh', 'code' => 'MADHESH', 'number' => 2],
            ['name' => 'Bagmati', 'code' => 'BAGMATI', 'number' => 3],
            ['name' => 'Gandaki', 'code' => 'GANDAKI', 'number' => 4],
            ['name' => 'Lumbini', 'code' => 'LUMBINI', 'number' => 5],
            ['name' => 'Karnali', 'code' => 'KARNALI', 'number' => 6],
            ['name' => 'Sudurpashchim', 'code' => 'SUDURPASHCHIM', 'number' => 7],
        ];

        foreach ($provinces as $province) {
            Province::firstOrCreate(
                ['code' => $province['code']],
                $province
            );
        }
    }
}

