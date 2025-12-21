<?php

namespace Database\Seeders;

use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Models\VendorAvailability;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyVendorsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendors = [
            [
                'name' => 'Ram Bahadur Magar',
                'email' => 'ram.magar@vendor.com',
                'profile' => [
                    'phone_number' => '9841567890',
                    'date_of_birth' => '1985-04-12',
                    'gender' => 'male',
                    'address' => 'Kalimati, Ward No. 14',
                    'city' => 'Kathmandu',
                    'state' => 'Bagmati Province',
                    'pincode' => '44600',
                    'vehicle_type' => 'auto',
                    'vehicle_number' => 'BA-1-KHA-1234',
                    'vehicle_model' => 'Bajaj RE Auto',
                    'vehicle_color' => 'Yellow',
                    'vehicle_year' => 2020,
                    'license_number' => 'KTM-058642019',
                    'license_expiry' => '2027-04-12',
                    'service_latitude' => 27.6974,
                    'service_longitude' => 85.2911,
                    'service_radius' => 5000,
                    'service_address' => 'Kalimati, Kathmandu',
                    'is_verified' => true,
                    'is_online' => true,
                    'is_available' => true,
                    'verification_status' => 'approved',
                    'rating' => 4.5,
                    'total_rides' => 342,
                ]
            ],
            [
                'name' => 'Krishna Adhikari',
                'email' => 'krishna.adhikari@vendor.com',
                'profile' => [
                    'phone_number' => '9851678901',
                    'date_of_birth' => '1990-11-05',
                    'gender' => 'male',
                    'address' => 'Lakeside, Ward No. 6',
                    'city' => 'Pokhara',
                    'state' => 'Gandaki Province',
                    'pincode' => '33700',
                    'vehicle_type' => 'bike',
                    'vehicle_number' => 'GA-2-PA-5678',
                    'vehicle_model' => 'Honda Activa',
                    'vehicle_color' => 'Black',
                    'vehicle_year' => 2021,
                    'license_number' => 'PKR-074562020',
                    'license_expiry' => '2028-11-05',
                    'service_latitude' => 28.2096,
                    'service_longitude' => 83.9856,
                    'service_radius' => 8000,
                    'service_address' => 'Lakeside, Pokhara',
                    'is_verified' => true,
                    'is_online' => false,
                    'is_available' => true,
                    'verification_status' => 'approved',
                    'rating' => 4.8,
                    'total_rides' => 567,
                ]
            ],
            [
                'name' => 'Nischal Khadka',
                'email' => 'nischal.khadka@vendor.com',
                'profile' => [
                    'phone_number' => '9861789012',
                    'date_of_birth' => '1987-09-22',
                    'gender' => 'male',
                    'address' => 'Jhamsikhel, Lalitpur',
                    'city' => 'Lalitpur',
                    'state' => 'Bagmati Province',
                    'pincode' => '44700',
                    'vehicle_type' => 'car',
                    'vehicle_number' => 'BA-3-CHA-9012',
                    'vehicle_model' => 'Maruti Suzuki Swift Dzire',
                    'vehicle_color' => 'White',
                    'vehicle_year' => 2019,
                    'license_number' => 'LTP-059872018',
                    'license_expiry' => '2026-09-22',
                    'service_latitude' => 27.6693,
                    'service_longitude' => 85.3157,
                    'service_radius' => 10000,
                    'service_address' => 'Jhamsikhel, Lalitpur',
                    'is_verified' => true,
                    'is_online' => true,
                    'is_available' => false,
                    'verification_status' => 'approved',
                    'rating' => 4.2,
                    'total_rides' => 189,
                ]
            ],
            [
                'name' => 'Dipendra Limbu',
                'email' => 'dipendra.limbu@vendor.com',
                'profile' => [
                    'phone_number' => '9871890123',
                    'date_of_birth' => '1993-02-14',
                    'gender' => 'male',
                    'address' => 'Dharan Sub-Metropolitan City, Ward No. 8',
                    'city' => 'Dharan',
                    'state' => 'Koshi Province',
                    'pincode' => '56700',
                    'vehicle_type' => 'auto',
                    'vehicle_number' => 'KO-1-GA-3456',
                    'vehicle_model' => 'Piaggio Ape Auto',
                    'vehicle_color' => 'Green',
                    'vehicle_year' => 2022,
                    'license_number' => 'DHR-096542021',
                    'license_expiry' => '2029-02-14',
                    'service_latitude' => 26.8125,
                    'service_longitude' => 87.2718,
                    'service_radius' => 6000,
                    'service_address' => 'Dharan Sub-Metropolitan City',
                    'is_verified' => false,
                    'is_online' => false,
                    'is_available' => false,
                    'verification_status' => 'pending',
                    'rating' => 0.0,
                    'total_rides' => 0,
                ]
            ],
            [
                'name' => 'Santosh Karki',
                'email' => 'santosh.karki@vendor.com',
                'profile' => [
                    'phone_number' => '9881901234',
                    'date_of_birth' => '1989-06-30',
                    'gender' => 'male',
                    'address' => 'Biratnagar Metropolitan City, Road No. 5',
                    'city' => 'Biratnagar',
                    'state' => 'Koshi Province',
                    'pincode' => '56613',
                    'vehicle_type' => 'bike',
                    'vehicle_number' => 'KO-2-KHA-7890',
                    'vehicle_model' => 'TVS Jupiter',
                    'vehicle_color' => 'Blue',
                    'vehicle_year' => 2020,
                    'license_number' => 'BTN-087652019',
                    'license_expiry' => '2027-06-30',
                    'service_latitude' => 26.4525,
                    'service_longitude' => 87.2718,
                    'service_radius' => 7000,
                    'service_address' => 'Biratnagar Metropolitan City',
                    'is_verified' => true,
                    'is_online' => true,
                    'is_available' => true,
                    'verification_status' => 'approved',
                    'rating' => 4.6,
                    'total_rides' => 423,
                ]
            ],
            [
                'name' => 'Prakash Bhandari',
                'email' => 'prakash.bhandari@vendor.com',
                'profile' => [
                    'phone_number' => '9841012345',
                    'date_of_birth' => '1991-08-17',
                    'gender' => 'male',
                    'address' => 'Butwal Sub-Metropolitan City, Ward No. 11',
                    'city' => 'Butwal',
                    'state' => 'Lumbini Province',
                    'pincode' => '32907',
                    'vehicle_type' => 'car',
                    'vehicle_number' => 'LU-5-PA-2345',
                    'vehicle_model' => 'Honda City',
                    'vehicle_color' => 'Silver',
                    'vehicle_year' => 2021,
                    'license_number' => 'BTW-063242020',
                    'license_expiry' => '2028-08-17',
                    'service_latitude' => 27.7004,
                    'service_longitude' => 83.4466,
                    'service_radius' => 12000,
                    'service_address' => 'Butwal Sub-Metropolitan City',
                    'is_verified' => true,
                    'is_online' => false,
                    'is_available' => true,
                    'verification_status' => 'approved',
                    'rating' => 4.9,
                    'total_rides' => 678,
                ]
            ],
        ];

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($vendors as $vendorData) {
            // Check if vendor already exists
            $existingVendor = Vendor::where('email', $vendorData['email'])->first();
            
            if ($existingVendor) {
                $this->command->info("Vendor {$vendorData['email']} already exists. Skipping...");
                continue;
            }

            // Create vendor
            $vendor = Vendor::create([
                'unique_id' => 'SS-VENDOR-' . strtoupper(uniqid()),
                'name' => $vendorData['name'],
                'email' => $vendorData['email'],
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]);

            // Create vendor profile
            VendorProfile::create(array_merge(
                ['vendor_id' => $vendor->id],
                $vendorData['profile']
            ));

            // Create availability for all days
            foreach ($days as $day) {
                VendorAvailability::create([
                    'vendor_id' => $vendor->id,
                    'day_of_week' => $day,
                    'is_available' => in_array($day, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']),
                    'start_time' => '09:00',
                    'end_time' => '20:00',
                ]);
            }

            $this->command->info("Created vendor: {$vendorData['name']} ({$vendorData['email']})");
        }

        $this->command->info("\n====================================");
        $this->command->info("Dummy vendors created successfully!");
        $this->command->info("Default password for all vendors: password123");
        $this->command->info("====================================\n");
    }
}

