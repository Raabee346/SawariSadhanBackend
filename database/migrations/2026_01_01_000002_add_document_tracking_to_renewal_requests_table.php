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
        Schema::table('renewal_requests', function (Blueprint $table) {
            $table->timestamp('en_route_at')->nullable()->after('assigned_at');
            $table->timestamp('document_picked_up_at')->nullable()->after('started_at');
            $table->string('document_photo')->nullable()->after('document_picked_up_at');
            $table->timestamp('at_dotm_at')->nullable()->after('document_picked_up_at');
            $table->timestamp('delivered_at')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('renewal_requests', function (Blueprint $table) {
            $table->dropColumn(['en_route_at', 'document_picked_up_at', 'document_photo', 'at_dotm_at', 'delivered_at']);
        });
    }
};




