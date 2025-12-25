<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Province;
use App\Models\Vehicle;
// BS dates are stored directly, no conversion needed
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::take(5)->get();
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please create users first.');
            return;
        }

        $provinces = Province::all();
        if ($provinces->isEmpty()) {
            $this->command->warn('No provinces found. Please run ProvinceSeeder first.');
            return;
        }

        $vehicles = [
            [
                'registration_number' => 'BA-01-1234',
                'owner_name' => 'Ram Bahadur Shrestha',
                'chassis_number' => 'CH-2080-001',
                'vehicle_type' => '2W',
                'fuel_type' => 'Petrol',
                'brand' => 'Honda',
                'model' => 'Activa',
                'engine_capacity' => 110,
                'manufacturing_year' => 2020,
                'registration_date' => '2077-02-01', // BS date stored directly
                'is_commercial' => false,
                'verification_status' => 'approved',
            ],
            [
                'registration_number' => 'BA-02-5678',
                'owner_name' => 'Shyam Kumar Tamang',
                'chassis_number' => 'CH-2081-002',
                'vehicle_type' => '4W',
                'fuel_type' => 'Petrol',
                'brand' => 'Toyota',
                'model' => 'Corolla',
                'engine_capacity' => 1800,
                'manufacturing_year' => 2021,
                'registration_date' => '2078-05-04', // BS date stored directly
                'is_commercial' => false,
                'verification_status' => 'approved',
            ],
            [
                'registration_number' => 'KO-01-9999',
                'owner_name' => 'Gita Devi Karki',
                'chassis_number' => 'CH-2079-003',
                'vehicle_type' => '2W',
                'fuel_type' => 'Electric',
                'brand' => 'Yatri',
                'model' => 'P1',
                'engine_capacity' => 1200,
                'manufacturing_year' => 2022,
                'registration_date' => '2078-11-26', // BS date stored directly
                'is_commercial' => false,
                'verification_status' => 'pending',
            ],
            [
                'registration_number' => 'BA-03-1111',
                'owner_name' => 'Hari Prasad Thapa',
                'chassis_number' => 'CH-2080-004',
                'vehicle_type' => '4W',
                'fuel_type' => 'Diesel',
                'brand' => 'Mahindra',
                'model' => 'Bolero',
                'engine_capacity' => 2500,
                'manufacturing_year' => 2019,
                'registration_date' => '2076-07-19', // BS date stored directly
                'is_commercial' => true,
                'verification_status' => 'approved',
            ],
            [
                'registration_number' => 'LU-01-2222',
                'owner_name' => 'Sita Kumari Magar',
                'chassis_number' => 'CH-2081-005',
                'vehicle_type' => '2W',
                'fuel_type' => 'Petrol',
                'brand' => 'Yamaha',
                'model' => 'FZ',
                'engine_capacity' => 150,
                'manufacturing_year' => 2023,
                'registration_date' => '2080-02-17', // BS date stored directly
                'is_commercial' => false,
                'verification_status' => 'approved',
            ],
        ];

        foreach ($vehicles as $index => $vehicleData) {
            $user = $users[$index % $users->count()];
            $province = $provinces->random();

            Vehicle::firstOrCreate(
                [
                    'registration_number' => $vehicleData['registration_number'],
                ],
                array_merge($vehicleData, [
                    'user_id' => $user->id,
                    'province_id' => $province->id,
                ])
            );
        }
    }
}

