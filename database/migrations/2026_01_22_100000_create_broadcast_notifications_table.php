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
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->enum('target_type', ['users', 'vendors', 'admins', 'all'])->default('users');
            $table->enum('type', ['admin_broadcast', 'system', 'update'])->default('admin_broadcast');
            $table->json('extra_data')->nullable(); // For additional data like actions, links, etc.
            $table->timestamps();
            
            // Indexes for performance
            $table->index('target_type');
            $table->index('created_at');
        });
        
        // Pivot table to track which users have read which notifications
        Schema::create('notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_notification_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamp('read_at');
            $table->timestamps();
            
            // Indexes
            $table->index(['app_notification_id', 'user_id']);
            $table->index(['app_notification_id', 'vendor_id']);
            $table->index(['app_notification_id', 'admin_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
        Schema::dropIfExists('app_notifications');
    }
};
