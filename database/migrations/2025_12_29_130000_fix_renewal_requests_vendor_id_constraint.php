<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renewal_requests', function (Blueprint $table) {
            // Drop the old foreign key constraint (if it exists)
            $table->dropForeign(['vendor_id']);
        });

        Schema::table('renewal_requests', function (Blueprint $table) {
            // Add new foreign key constraint pointing to vendors table
            $table->foreign('vendor_id')
                ->references('id')
                ->on('vendors')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('renewal_requests', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
        });

        Schema::table('renewal_requests', function (Blueprint $table) {
            // Restore original constraint pointing to users table
            $table->foreign('vendor_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }
};

