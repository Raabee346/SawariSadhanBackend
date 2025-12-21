<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Rajesh Thapa',
                'email' => 'rajesh.thapa@example.com',
                'profile' => [
                    'phone_number' => '9841234567',
                    'date_of_birth' => '1995-05-15',
                    'gender' => 'male',
                    'address' => 'Thamel, Ward No. 26, House No. 123',
                    'city' => 'Kathmandu',
                    'state' => 'Bagmati Province',
                    'pincode' => '44600',
                    'country' => 'Nepal',
                    'latitude' => 27.7172,
                    'longitude' => 85.3240,
                ]
            ],
            [
                'name' => 'Anita Gurung',
                'email' => 'anita.gurung@example.com',
                'profile' => [
                    'phone_number' => '9851234568',
                    'date_of_birth' => '1992-08-20',
                    'gender' => 'female',
                    'address' => 'Lakeside, Ward No. 6, Street 15',
                    'city' => 'Pokhara',
                    'state' => 'Gandaki Province',
                    'pincode' => '33700',
                    'country' => 'Nepal',
                    'latitude' => 28.2096,
                    'longitude' => 83.9856,
                ]
            ],
            [
                'name' => 'Sunil Shrestha',
                'email' => 'sunil.shrestha@example.com',
                'profile' => [
                    'phone_number' => '9861234569',
                    'date_of_birth' => '1988-12-10',
                    'gender' => 'male',
                    'address' => 'Pulchowk, Lalitpur Metropolitan City',
                    'city' => 'Lalitpur',
                    'state' => 'Bagmati Province',
                    'pincode' => '44700',
                    'country' => 'Nepal',
                    'latitude' => 27.6710,
                    'longitude' => 85.3168,
                ]
            ],
            [
                'name' => 'Sita Rai',
                'email' => 'sita.rai@example.com',
                'profile' => [
                    'phone_number' => '9871234570',
                    'date_of_birth' => '1997-03-25',
                    'gender' => 'female',
                    'address' => 'Dharan Sub-Metropolitan City, Ward No. 5',
                    'city' => 'Dharan',
                    'state' => 'Koshi Province',
                    'pincode' => '56700',
                    'country' => 'Nepal',
                    'latitude' => 26.8125,
                    'longitude' => 87.2718,
                ]
            ],
            [
                'name' => 'Bikash Tamang',
                'email' => 'bikash.tamang@example.com',
                'profile' => [
                    'phone_number' => '9881234571',
                    'date_of_birth' => '1990-07-18',
                    'gender' => 'male',
                    'address' => 'Biratnagar Metropolitan City, Road No. 12',
                    'city' => 'Biratnagar',
                    'state' => 'Koshi Province',
                    'pincode' => '56613',
                    'country' => 'Nepal',
                    'latitude' => 26.4525,
                    'longitude' => 87.2718,
                ]
            ],
        ];

        foreach ($users as $userData) {
            // Check if user already exists
            $existingUser = User::where('email', $userData['email'])->first();
            
            if ($existingUser) {
                $this->command->info("User {$userData['email']} already exists. Skipping...");
                continue;
            }

            // Create user
            $user = User::create([
                'unique_id' => 'SS-USER-' . strtoupper(uniqid()),
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]);

            // Create user profile
            UserProfile::create(array_merge(
                ['user_id' => $user->id],
                $userData['profile']
            ));

            $this->command->info("Created user: {$userData['name']} ({$userData['email']})");
        }

        $this->command->info("\n=================================");
        $this->command->info("Dummy users created successfully!");
        $this->command->info("Default password for all users: password123");
        $this->command->info("=================================\n");
    }
}

