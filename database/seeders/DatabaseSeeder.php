<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user for Filament panel
        $this->call([
            AdminUserSeeder::class,
            DummyUsersSeeder::class,
            DummyVendorsSeeder::class,
            // Vehicle tax system seeders
            ProvinceSeeder::class,
            FiscalYearSeeder::class,
            PenaltyConfigSeeder::class,
            TaxRateSeeder::class,
            InsuranceRateSeeder::class,
            VehicleSeeder::class,
        ]);

        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
