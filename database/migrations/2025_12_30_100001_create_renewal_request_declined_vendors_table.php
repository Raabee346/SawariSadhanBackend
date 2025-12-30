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
        Schema::create('renewal_request_declined_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('renewal_request_id')->constrained('renewal_requests')->onDelete('cascade');
            $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');
            $table->timestamp('declined_at')->useCurrent();
            $table->timestamps();
            
            // Ensure a vendor can only decline a request once
            // Use shorter name to avoid MySQL 64-character identifier limit
            $table->unique(['renewal_request_id', 'vendor_id'], 'rr_declined_vendors_unique');
            
            // Index for faster queries
            $table->index('renewal_request_id', 'rr_declined_req_idx');
            $table->index('vendor_id', 'rr_declined_vendor_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renewal_request_declined_vendors');
    }
};

