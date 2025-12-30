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
        Schema::table('renewal_requests', function (Blueprint $table) {
            // Add drop-off location fields
            $table->string('dropoff_address')->nullable()->after('pickup_time_slot');
            $table->decimal('dropoff_latitude', 10, 8)->nullable()->after('dropoff_address');
            $table->decimal('dropoff_longitude', 11, 8)->nullable()->after('dropoff_latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('renewal_requests', function (Blueprint $table) {
            $table->dropColumn(['dropoff_address', 'dropoff_latitude', 'dropoff_longitude']);
        });
    }
};

