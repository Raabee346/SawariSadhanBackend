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
        Schema::table('vendor_profiles', function (Blueprint $table) {
            // Change date columns to string to store BS dates directly
            $table->string('date_of_birth')->nullable()->change();
            $table->string('license_expiry')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            // Revert back to date columns (if needed)
            $table->date('date_of_birth')->nullable()->change();
            $table->date('license_expiry')->nullable()->change();
        });
    }
};

