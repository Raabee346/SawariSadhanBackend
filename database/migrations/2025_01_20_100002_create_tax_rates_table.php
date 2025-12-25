<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->onDelete('cascade');
            $table->foreignId('fiscal_year_id')->constrained()->onDelete('cascade');
            $table->enum('vehicle_type', ['2W', '4W', 'Commercial', 'Heavy']); // 2W = Two-wheeler, 4W = Four-wheeler
            $table->enum('fuel_type', ['Petrol', 'Diesel', 'Electric']);
            $table->integer('capacity_value')->comment('Exact CC/Watts value');
            $table->decimal('annual_tax_amount', 10, 2);
            $table->decimal('renewal_fee', 10, 2)->default(300); // Nabikaran charge
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['province_id', 'fiscal_year_id', 'vehicle_type', 'fuel_type', 'capacity_value'], 'idx_tax_rates_lookup');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('tax_rates')) {
            Schema::table('tax_rates', function (Blueprint $table) {
                $table->dropForeign(['province_id']);
                $table->dropForeign(['fiscal_year_id']);
            });
        }
        Schema::dropIfExists('tax_rates');
    }
};

