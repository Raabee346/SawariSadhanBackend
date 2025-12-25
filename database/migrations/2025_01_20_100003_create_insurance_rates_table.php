<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_rates', function (Blueprint $table) {
            $table->id();
            $table->enum('vehicle_type', ['2W', '4W', 'Commercial', 'Heavy']);
            $table->enum('fuel_type', ['Petrol', 'Diesel', 'Electric']);
            $table->integer('capacity_value')->comment('Exact CC/Watts value');
            $table->decimal('annual_premium', 10, 2);
            $table->foreignId('fiscal_year_id')->constrained()->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['vehicle_type', 'fuel_type', 'fiscal_year_id', 'capacity_value'], 'idx_insurance_rates_lookup');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('insurance_rates')) {
            Schema::table('insurance_rates', function (Blueprint $table) {
                $table->dropForeign(['fiscal_year_id']);
            });
        }
        Schema::dropIfExists('insurance_rates');
    }
};

