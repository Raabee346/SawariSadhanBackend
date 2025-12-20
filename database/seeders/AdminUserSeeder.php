<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin user already exists
        $adminExists = User::where('email', 'admin@sawarisadhan.com')->exists();

        if (!$adminExists) {
            User::create([
                'unique_id' => 'SS-ADMIN-' . strtoupper(uniqid()),
                'name' => 'Admin User',
                'email' => 'admin@sawarisadhan.com',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]);

            $this->command->info('Admin user created successfully!');
            $this->command->info('Email: admin@sawarisadhan.com');
            $this->command->info('Password: admin123');
        } else {
            $this->command->info('Admin user already exists!');
        }
    }
}

