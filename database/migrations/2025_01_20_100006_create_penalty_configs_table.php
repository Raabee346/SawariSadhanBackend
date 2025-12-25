<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalty_configs', function (Blueprint $table) {
            $table->id();
            $table->string('duration_label'); // e.g., 'First 30 Days', 'Up to 45 Days'
            $table->integer('days_from_expiry'); // Days after grace period (90 days)
            $table->integer('days_to')->nullable(); // End of this penalty period (null = no limit)
            $table->decimal('penalty_percentage', 5, 2); // e.g., 5.00, 10.00, 20.00
            $table->decimal('renewal_fee_penalty_percentage', 5, 2)->default(100.00); // Usually 100%
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_configs');
    }
};

