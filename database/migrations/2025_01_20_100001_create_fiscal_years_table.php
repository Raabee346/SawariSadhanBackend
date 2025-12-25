<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('year'); // e.g., '2081/82', '2082/83'
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Drop foreign keys that reference this table first
        if (Schema::hasTable('tax_rates')) {
            try {
                Schema::table('tax_rates', function (Blueprint $table) {
                    $table->dropForeign(['fiscal_year_id']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }
        }
        if (Schema::hasTable('insurance_rates')) {
            try {
                Schema::table('insurance_rates', function (Blueprint $table) {
                    $table->dropForeign(['fiscal_year_id']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }
        }
        if (Schema::hasTable('payments')) {
            try {
                Schema::table('payments', function (Blueprint $table) {
                    $table->dropForeign(['fiscal_year_id']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }
        }
        Schema::dropIfExists('fiscal_years');
    }
};
