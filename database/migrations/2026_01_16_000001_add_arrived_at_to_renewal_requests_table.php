<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renewal_requests', function (Blueprint $table) {
            $table->timestamp('arrived_at')->nullable()->after('en_route_at');
        });
    }

    public function down(): void
    {
        Schema::table('renewal_requests', function (Blueprint $table) {
            $table->dropColumn('arrived_at');
        });
    }
};

