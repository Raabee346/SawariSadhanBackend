<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renewal_requests', function (Blueprint $table) {
            $table->timestamp('en_route_dropoff_at')->nullable()->after('processing_complete_at');
        });
    }

    public function down(): void
    {
        Schema::table('renewal_requests', function (Blueprint $table) {
            $table->dropColumn('en_route_dropoff_at');
        });
    }
};

