<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('province_id')->constrained()->onDelete('restrict');
            $table->string('owner_name');
            $table->string('registration_number')->unique();
            $table->string('chassis_number')->unique();
            $table->enum('vehicle_type', ['2W', '4W', 'Commercial', 'Heavy']);
            $table->enum('fuel_type', ['Petrol', 'Diesel', 'Electric']);
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->integer('engine_capacity'); // CC for petrol/diesel, Watts/kW for electric
            $table->integer('manufacturing_year')->nullable();
            $table->date('registration_date');
            $table->date('last_renewed_date')->nullable();
            $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_commercial')->default(false);
            $table->text('documents')->nullable(); // JSON array of document paths
            $table->timestamps();
            
            $table->index(['user_id', 'verification_status']);
            $table->index(['province_id', 'vehicle_type']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropForeign(['province_id']);
                $table->dropForeign(['user_id']);
            });
        }
        Schema::dropIfExists('vehicles');
    }
};

