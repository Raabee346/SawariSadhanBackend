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
            // Change date columns to string to store BS dates directly
            $table->string('registration_date')->change();
            $table->string('last_renewed_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Revert back to date columns (if needed)
            $table->date('registration_date')->change();
            $table->date('last_renewed_date')->nullable()->change();
        });
    }
};
