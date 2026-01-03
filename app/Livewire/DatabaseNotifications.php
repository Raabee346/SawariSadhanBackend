<?php

namespace App\Livewire;

use Filament\Notifications\Livewire\DatabaseNotifications as BaseDatabaseNotifications;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DatabaseNotifications extends BaseDatabaseNotifications
{
    /**
     * Mount the component - verify it's being used
     * Override to prevent any automatic deletion that might happen in parent::mount()
     * DO NOT call parent::mount() if it's causing deletions
     */
    public function mount(): void
    {
        Log::info('🔔 Custom DatabaseNotifications component mounted');
        
        // Check notification count before mount
        $admin = auth('admin')->user();
        if ($admin) {
            $beforeCount = $admin->notifications()->count();
            $beforeIds = $admin->notifications()->pluck('id')->toArray();
            Log::info('🔔 Notifications before mount', [
                'admin_id' => $admin->id,
                'count' => $beforeCount,
                'ids' => $beforeIds,
            ]);
        }
        
        // Call parent mount - it should be safe now that we removed 'format' => 'filament'
        // from the notification data, which prevents Filament from auto-deleting
        try {
            parent::mount();
        } catch (\Exception $e) {
            Log::error('🔔 Error in parent::mount()', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
        
        // Check notification count after mount
        if ($admin) {
            $afterCount = $admin->notifications()->count();
            $afterIds = $admin->notifications()->pluck('id')->toArray();
            $deletedIds = array_diff($beforeIds, $afterIds);
            
            Log::info('🔔 Notifications after mount', [
                'admin_id' => $admin->id,
                'count' => $afterCount,
                'deleted_count' => $beforeCount - $afterCount,
                'deleted_ids' => $deletedIds,
            ]);
            
            if ($afterCount < $beforeCount) {
                Log::error('🔔 ❌ CRITICAL: Notifications were deleted during mount!', [
                    'admin_id' => $admin->id,
                    'before' => $beforeCount,
                    'after' => $afterCount,
                    'deleted_count' => $beforeCount - $afterCount,
                    'deleted_ids' => $deletedIds,
                ]);
            }
        }
    }
    
    /**
     * Get all notifications including read ones
     * Override Filament's default which only shows unread notifications
     * This property is used by Filament to display notifications in the bell dropdown
     * 
     * IMPORTANT: We filter by format = 'filament' to match Filament's view expectations
     * but we prevent deletion through our markAsRead override
     */
    public function getNotificationsProperty(): \Illuminate\Support\Collection
    {
        $admin = auth('admin')->user();
        
        if (!$admin) {
            Log::warning('No admin user found when getting notifications');
            return collect();
        }
        
        // Get ALL notifications (both read and unread) for the admin
        // Filter by format = 'filament' so Filament's view displays them
        // But we prevent deletion through our markAsRead override
        $notifications = $admin->notifications()
            ->whereJsonContains('data->format', 'filament')
            ->orderBy('created_at', 'desc')
            ->limit(50) // Limit to prevent too many notifications
            ->get();
        
        Log::info('🔔 Custom DatabaseNotifications: Getting notifications', [
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'total_count' => $notifications->count(),
            'unread_count' => $notifications->whereNull('read_at')->count(),
            'read_count' => $notifications->whereNotNull('read_at')->count(),
            'notification_ids' => $notifications->pluck('id')->toArray(),
        ]);
        
        // Log first notification details for debugging
        if ($notifications->isNotEmpty()) {
            $first = $notifications->first();
            Log::info('🔔 First notification details', [
                'id' => $first->id,
                'type' => $first->type,
                'data' => json_encode($first->data),
                'read_at' => $first->read_at,
                'notifiable_type' => $first->notifiable_type,
                'notifiable_id' => $first->notifiable_id,
                'has_format' => isset($first->data['format']),
            ]);
        } else {
            Log::warning('🔔 No notifications found for admin', [
                'admin_id' => $admin->id,
                'total_in_db' => $admin->notifications()->count(),
            ]);
        }
        
        return $notifications;
    }
    
    /**
     * Mark a notification as read without deleting it
     * This overrides Filament's default behavior which deletes notifications when marked as read
     * IMPORTANT: Do NOT call parent::markAsRead() as it will delete the notification
     */
    public function markAsRead(string $id): void
    {
        Log::info('🔔 markAsRead called in custom component', ['notification_id' => $id]);
        
        $notification = DatabaseNotification::find($id);
        
        if (!$notification) {
            Log::warning('🔔 Notification not found for markAsRead', ['id' => $id]);
            return;
        }
        
        $admin = auth('admin')->user();
        
        if (!$admin) {
            Log::warning('🔔 No admin user when marking notification as read', ['id' => $id]);
            return;
        }
        
        // Verify the notification belongs to the current admin
        if ($notification->notifiable_id === $admin->id && 
            $notification->notifiable_type === \App\Models\Admin::class) {
            
            // Check if already read
            if ($notification->read_at) {
                Log::info('🔔 Notification already marked as read, skipping', [
                    'admin_id' => $admin->id,
                    'notification_id' => $id,
                ]);
                return;
            }
            
            // Mark as read using direct database update (don't use $notification->markAsRead() as it might trigger events)
            // This ensures the notification is NOT deleted
            DB::table('notifications')
                ->where('id', $id)
                ->update(['read_at' => now()]);
            
            // Verify notification still exists after update
            $stillExists = DB::table('notifications')->where('id', $id)->exists();
            
            if (!$stillExists) {
                Log::error('🔔 ❌ CRITICAL: Notification was deleted after marking as read!', [
                    'admin_id' => $admin->id,
                    'notification_id' => $id,
                ]);
            } else {
                $readAt = DB::table('notifications')->where('id', $id)->value('read_at');
                Log::info('🔔 ✅ Notification marked as read (NOT deleted)', [
                    'admin_id' => $admin->id,
                    'notification_id' => $id,
                    'read_at' => $readAt,
                    'still_exists' => true,
                ]);
            }
        } else {
            Log::warning('🔔 Unauthorized attempt to mark notification as read', [
                'admin_id' => $admin->id,
                'notification_id' => $id,
                'notification_notifiable_id' => $notification->notifiable_id,
                'notification_notifiable_type' => $notification->notifiable_type,
            ]);
        }
    }
    
    /**
     * Delete a notification (only when explicitly called via delete button)
     * Add logging to track ALL deletions
     */
    public function delete(string $id): void
    {
        Log::warning('🔔 DELETE method called', [
            'notification_id' => $id,
            'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
        ]);
        
        $notification = DatabaseNotification::find($id);
        
        if (!$notification) {
            Log::warning('🔔 Notification not found for delete', ['id' => $id]);
            return;
        }
        
        $admin = auth('admin')->user();
        
        // Only allow deletion if it belongs to the current admin
        if ($admin && 
            $notification->notifiable_id === $admin->id && 
            $notification->notifiable_type === \App\Models\Admin::class) {
            $notification->delete();
            Log::info('🔔 Notification deleted by admin (explicit delete)', [
                'admin_id' => $admin->id,
                'notification_id' => $id,
            ]);
        } else {
            Log::warning('🔔 Unauthorized delete attempt blocked', [
                'admin_id' => $admin?->id,
                'notification_id' => $id,
            ]);
        }
    }
    
    /**
     * Override any method that might automatically mark notifications as read
     * Filament might have methods that auto-mark notifications on display
     */
    public function markAllAsRead(): void
    {
        Log::warning('🔔 markAllAsRead called - blocking to prevent auto-deletion');
        // Don't call parent - it might delete notifications
        // If admin wants to mark all as read, they should do it manually
    }
}

