<?php

namespace App\Http\Controllers;

use App\Models\BroadcastNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BroadcastNotificationController extends Controller
{
    /**
     * Get notifications for authenticated user
     */
    public function getUserNotifications(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        try {
            // Get notifications for users or all
            $notifications = BroadcastNotification::whereIn('target_type', ['users', 'all'])
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($notification) use ($user) {
                    return [
                        'id' => $notification->id,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'type' => $notification->type,
                        'is_read' => $notification->isReadByUser($user->id),
                        'created_at' => $notification->created_at->toISOString(),
                        'time_ago' => $notification->created_at->diffForHumans(),
                    ];
                });

            // Get unread count
            $unreadCount = BroadcastNotification::whereIn('target_type', ['users', 'all'])
                ->whereDoesntHave('reads', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->count();

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch user notifications', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications',
            ], 500);
        }
    }

    /**
     * Get notifications for authenticated vendor
     */
    public function getVendorNotifications(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not authenticated',
            ], 401);
        }

        try {
            // Get notifications for vendors or all
            $notifications = BroadcastNotification::whereIn('target_type', ['vendors', 'all'])
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get()
                ->map(function ($notification) use ($vendor) {
                    return [
                        'id' => $notification->id,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'type' => $notification->type,
                        'is_read' => $notification->isReadByVendor($vendor->id),
                        'created_at' => $notification->created_at->toISOString(),
                        'time_ago' => $notification->created_at->diffForHumans(),
                    ];
                });

            // Get unread count
            $unreadCount = BroadcastNotification::whereIn('target_type', ['vendors', 'all'])
                ->whereDoesntHave('reads', function ($query) use ($vendor) {
                    $query->where('vendor_id', $vendor->id);
                })
                ->count();

            return response()->json([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch vendor notifications', [
                'vendor_id' => $vendor->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications',
            ], 500);
        }
    }

    /**
     * Mark notification as read for user
     */
    public function markAsReadUser(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        try {
            $notification = BroadcastNotification::find($id);

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found',
                ], 404);
            }

            $notification->markAsReadByUser($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read', [
                'user_id' => $user->id,
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
     * Mark notification as read for vendor
     */
    public function markAsReadVendor(Request $request, $id)
    {
        $vendor = Auth::guard('vendor')->user();

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not authenticated',
            ], 401);
        }

        try {
            $notification = BroadcastNotification::find($id);

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found',
                ], 404);
            }

            $notification->markAsReadByVendor($vendor->id);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read', [
                'vendor_id' => $vendor->id,
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
     * Mark all notifications as read for user
     */
    public function markAllAsReadUser(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        try {
            $notifications = BroadcastNotification::whereIn('target_type', ['users', 'all'])
                ->whereDoesntHave('reads', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->get();

            foreach ($notifications as $notification) {
                $notification->markAsReadByUser($user->id);
            }

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
                'count' => $notifications->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
            ], 500);
        }
    }

    /**
     * Mark all notifications as read for vendor
     */
    public function markAllAsReadVendor(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not authenticated',
            ], 401);
        }

        try {
            $notifications = BroadcastNotification::whereIn('target_type', ['vendors', 'all'])
                ->whereDoesntHave('reads', function ($query) use ($vendor) {
                    $query->where('vendor_id', $vendor->id);
                })
                ->get();

            foreach ($notifications as $notification) {
                $notification->markAsReadByVendor($vendor->id);
            }

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
                'count' => $notifications->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read', [
                'vendor_id' => $vendor->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
            ], 500);
        }
    }
}
