<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // KOSHI, MADHESH, BAGMATI, etc.
            $table->integer('number'); // 1-7
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Drop foreign keys that reference this table first
        if (Schema::hasTable('vehicles')) {
            try {
                Schema::table('vehicles', function (Blueprint $table) {
                    $table->dropForeign(['province_id']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }
        }
        if (Schema::hasTable('tax_rates')) {
            try {
                Schema::table('tax_rates', function (Blueprint $table) {
                    $table->dropForeign(['province_id']);
                });
            } catch (\Exception $e) {
                // Foreign key might not exist, continue
            }
        }
        Schema::dropIfExists('provinces');
    }
};
