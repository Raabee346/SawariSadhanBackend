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
        Schema::create('vendor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');
            
            // Personal Details
            $table->string('phone_number')->nullable();
            $table->string('profile_picture')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode', 10)->nullable();
            
            // Vehicle Details
            $table->enum('vehicle_type', ['bike', 'auto', 'car', 'van'])->nullable();
            $table->string('vehicle_number')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('vehicle_color')->nullable();
            $table->year('vehicle_year')->nullable();
            
            // License & Documents
            $table->string('license_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('license_document')->nullable(); // File path
            $table->string('vehicle_rc_document')->nullable(); // File path
            $table->string('insurance_document')->nullable(); // File path
            $table->string('citizenship_document')->nullable(); // File path
            $table->string('pan_document')->nullable(); // File path
            
            // Service Area
            $table->decimal('service_latitude', 10, 8)->nullable();
            $table->decimal('service_longitude', 11, 8)->nullable();
            $table->integer('service_radius')->default(50000); // in meters (50000 = 50km)
            $table->text('service_address')->nullable();
            
            // Status & Verification
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_online')->default(false);
            $table->boolean('is_available')->default(false);
            $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            
            // Rating
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->integer('total_rides')->default(0);
            
            $table->timestamps();
            
            $table->index('vendor_id');
            $table->index(['service_latitude', 'service_longitude']);
            $table->index('is_verified');
            $table->index('is_online');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_profiles');
    }
};

