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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('rc_firstpage')->nullable()->after('documents');
            $table->string('rc_ownerdetails')->nullable()->after('rc_firstpage');
            $table->string('rc_vehicledetails')->nullable()->after('rc_ownerdetails');
            $table->string('lastrenewdate')->nullable()->after('rc_vehicledetails');
            $table->string('insurance')->nullable()->after('lastrenewdate');
            $table->string('owner_ctznship_front')->nullable()->after('insurance');
            $table->string('owner_ctznship_back')->nullable()->after('owner_ctznship_front');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'rc_firstpage',
                'rc_ownerdetails',
                'rc_vehicledetails',
                'lastrenewdate',
                'insurance',
                'owner_ctznship_front',
                'owner_ctznship_back',
            ]);
        });
    }
};
