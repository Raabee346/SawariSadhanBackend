<?php

namespace Database\Seeders;

use App\Models\Vendor;
use App\Models\VendorAvailability;
use Illuminate\Database\Seeder;

class VendorAvailabilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        $vendors = Vendor::all();
        
        foreach ($vendors as $vendor) {
            foreach ($days as $day) {
                VendorAvailability::updateOrCreate(
                    [
                        'vendor_id' => $vendor->id,
                        'day_of_week' => $day
                    ],
                    [
                        'is_available' => true,
                        'start_time' => '09:00',
                        'end_time' => '18:00',
                    ]
                );
            }
            
            $this->command->info("Initialized availability for vendor: {$vendor->name}");
        }
        
        $this->command->info('Vendor availability initialization completed!');
    }
}

