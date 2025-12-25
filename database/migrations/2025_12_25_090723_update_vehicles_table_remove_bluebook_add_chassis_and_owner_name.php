<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Remove bluebook_number
            if (Schema::hasColumn('vehicles', 'bluebook_number')) {
                try {
                    $table->dropUnique(['bluebook_number']);
                } catch (\Exception $e) {
                    // Index might not exist
                }
                $table->dropColumn('bluebook_number');
            }
        });
        
        // Add owner_name and chassis_number as nullable first (no unique constraint yet)
        Schema::table('vehicles', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicles', 'owner_name')) {
                $table->string('owner_name')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('vehicles', 'chassis_number')) {
                $table->string('chassis_number')->nullable()->after('registration_number');
            }
        });
        
        // Update existing records with unique values
        $vehicles = \DB::table('vehicles')->get();
        $baseTime = time();
        foreach ($vehicles as $index => $vehicle) {
            $uniqueChassis = 'CH-' . $vehicle->id . '-' . ($baseTime + $index) . '-' . rand(1000, 9999);
            \DB::table('vehicles')
                ->where('id', $vehicle->id)
                ->update([
                    'owner_name' => $vehicle->owner_name ?? ('Unknown Owner ' . $vehicle->id),
                    'chassis_number' => $vehicle->chassis_number ?? $uniqueChassis,
                ]);
        }
        
        // Now add unique constraint to chassis_number if it doesn't exist
        $indexExists = \DB::select("SHOW INDEX FROM vehicles WHERE Key_name = 'vehicles_chassis_number_unique'");
        if (empty($indexExists)) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->unique('chassis_number');
            });
        }
        
        // Make owner_name required
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('owner_name')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Restore bluebook_number
            $table->string('bluebook_number')->unique()->after('registration_number');
            
            // Remove owner_name and chassis_number
            $table->dropColumn(['owner_name', 'chassis_number']);
        });
    }
};
