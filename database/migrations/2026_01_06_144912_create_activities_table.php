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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->nullable()->constrained()->onDelete('set null');
            
            // Activity type: 'payment' or 'service'
            $table->enum('activity_type', ['payment', 'service'])->default('service');
            
            // Related entity IDs (polymorphic approach)
            $table->unsignedBigInteger('related_id')->nullable(); // payment_id or renewal_request_id
            $table->string('related_type')->nullable(); // 'App\Models\Payment' or 'App\Models\RenewalRequest'
            
            // Activity details
            $table->string('title');
            $table->text('message')->nullable();
            $table->dateTime('activity_date');
            
            // Additional metadata
            $table->json('metadata')->nullable(); // Store additional info like amount, service type, etc.
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'activity_type']);
            $table->index(['user_id', 'activity_date']);
            $table->index('activity_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
