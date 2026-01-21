<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\FCMNotificationService;
use App\Models\User;

class AdminNotificationController extends Controller
{
    /**
     * Mark a notification as read (but don't delete it)
     */
    public function markAsRead(Request $request, string $id)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not authenticated',
            ], 401);
        }

        try {
            $notification = DB::table('notifications')
                ->where('id', $id)
                ->where('notifiable_type', \App\Models\Admin::class)
                ->where('notifiable_id', $admin->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found',
                ], 404);
            }

            // Mark as read but don't delete
            DB::table('notifications')
                ->where('id', $id)
                ->update(['read_at' => now()]);

            Log::info('Notification marked as read', [
                'admin_id' => $admin->id,
                'notification_id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read', [
                'admin_id' => $admin->id,
                'notification_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
            ], 500);
        }
    }

    /**
     * Delete a notification (permanently remove it)
     */
    public function delete(Request $request, string $id)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not authenticated',
            ], 401);
        }

        try {
            $notification = DB::table('notifications')
                ->where('id', $id)
                ->where('notifiable_type', \App\Models\Admin::class)
                ->where('notifiable_id', $admin->id)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found',
                ], 404);
            }

            // Permanently delete the notification
            DB::table('notifications')
                ->where('id', $id)
                ->delete();

            Log::info('Notification deleted', [
                'admin_id' => $admin->id,
                'notification_id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete notification', [
                'admin_id' => $admin->id,
                'notification_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification',
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not authenticated',
            ], 401);
        }

        try {
            DB::table('notifications')
                ->where('notifiable_type', \App\Models\Admin::class)
                ->where('notifiable_id', $admin->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            Log::info('All notifications marked as read', [
                'admin_id' => $admin->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read', [
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
            ], 500);
        }
    }

    /**
     * Get all notifications (both read and unread)
     */
    public function index(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not authenticated',
            ], 401);
        }

        try {
            $notifications = DB::table('notifications')
                ->where('notifiable_type', \App\Models\Admin::class)
                ->where('notifiable_id', $admin->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch notifications', [
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications',
            ], 500);
        }
    }

    /**
     * Send broadcast notification to all users
     */
    public function sendBroadcast(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return response()->json([
                'success' => false,
                'message' => 'Admin not authenticated',
            ], 401);
        }

        // Validate request
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'target' => 'required|in:users,vendors,all', // Target audience
        ]);

        try {
            $title = $request->input('title');
            $message = $request->input('message');
            $target = $request->input('target');

            Log::info('Admin sending broadcast notification', [
                'admin_id' => $admin->id,
                'title' => $title,
                'target' => $target,
            ]);

            $fcmService = new FCMNotificationService();
            
            // Send to appropriate topic based on target
            if ($target === 'users' || $target === 'all') {
                $fcmService->sendAdminBroadcast($title, $message, 'users');
            }
            
            if ($target === 'vendors' || $target === 'all') {
                $fcmService->sendAdminBroadcast($title, $message, 'vendors');
            }

            Log::info('Broadcast notification sent successfully', [
                'admin_id' => $admin->id,
                'target' => $target,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Broadcast notification sent successfully',
                'data' => [
                    'title' => $title,
                    'message' => $message,
                    'target' => $target,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send broadcast notification', [
                'admin_id' => $admin->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send broadcast notification: ' . $e->getMessage(),
            ], 500);
        }
    }
}
