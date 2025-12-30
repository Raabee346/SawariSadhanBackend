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
        Schema::create('renewal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('vendor_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Service details
            $table->enum('service_type', ['bluebook_renewal'])->default('bluebook_renewal');
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed', 'cancelled'])->default('pending');
            
            // Location details
            $table->string('pickup_address');
            $table->decimal('pickup_latitude', 10, 8);
            $table->decimal('pickup_longitude', 11, 8);
            
            // Date and time
            $table->date('pickup_date');
            $table->string('pickup_time_slot'); // e.g., "10:00 AM - 12:00 PM"
            
            // Insurance information
            $table->boolean('has_insurance')->default(true); // true = user has insurance, false = needs insurance
            
            // Fiscal year for renewal
            $table->foreignId('fiscal_year_id')->nullable()->constrained()->onDelete('set null');
            
            // Amount details
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('renewal_fee', 10, 2)->default(0);
            $table->decimal('penalty_amount', 10, 2)->default(0);
            $table->decimal('insurance_amount', 10, 2)->default(0);
            $table->decimal('service_fee', 10, 2)->default(0);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            
            // Payment details
            $table->enum('payment_method', ['khalti', 'cash_on_delivery', 'esewa', 'ime_pay'])->nullable();
            $table->enum('payment_status', ['pending', 'completed', 'failed'])->default('pending');
            
            // Timestamps
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['vendor_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renewal_requests');
    }
};