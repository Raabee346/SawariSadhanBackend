<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('message')->nullable();
            $table->dateTime('reminder_date');
            $table->boolean('is_notified')->default(false);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'reminder_date']);
            $table->index(['vehicle_id']);
            $table->index(['is_notified', 'reminder_date']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('reminders')) {
            Schema::table('reminders', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropForeign(['vehicle_id']);
            });
        }
        Schema::dropIfExists('reminders');
    }
};

