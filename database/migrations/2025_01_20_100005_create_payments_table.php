<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->foreignId('fiscal_year_id')->constrained()->onDelete('restrict');
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('renewal_fee', 10, 2);
            $table->decimal('penalty_amount', 10, 2)->default(0);
            $table->decimal('insurance_amount', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->enum('payment_status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('payment_method')->nullable(); // e.g., 'esewa', 'khalti', 'bank_transfer'
            $table->string('transaction_id')->nullable()->unique();
            $table->text('payment_details')->nullable(); // JSON for additional payment info
            $table->date('payment_date')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'payment_status']);
            $table->index(['vehicle_id', 'fiscal_year_id']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropForeign(['vehicle_id']);
                $table->dropForeign(['fiscal_year_id']);
            });
        }
        Schema::dropIfExists('payments');
    }
};

