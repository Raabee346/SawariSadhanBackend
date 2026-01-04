<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renewal_requests', function (Blueprint $table) {
            $table->timestamp('processing_complete_at')->nullable()->after('at_dotm_at');
        });
    }

    public function down(): void
    {
        Schema::table('renewal_requests', function (Blueprint $table) {
            $table->dropColumn('processing_complete_at');
        });
    }
};

