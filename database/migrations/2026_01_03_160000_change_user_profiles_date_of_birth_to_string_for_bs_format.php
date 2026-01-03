<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Change date_of_birth column from date to string to store BS dates directly
     */
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // Change date column to string to store BS dates directly (e.g., 2080-05-15)
            $table->string('date_of_birth')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // Revert back to date column (if needed)
            $table->date('date_of_birth')->nullable()->change();
        });
    }
};

