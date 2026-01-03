# FCM Live Broadcast Notifications Setup

This document explains the implementation of live broadcast notifications in Filament using FCM for vehicle verification requests.

## Implementation Summary

### 1. Database Changes
- **Migration**: `2026_01_03_095355_add_fcm_token_to_admins_table.php`
  - Adds `fcm_token` column (nullable text) to `admins` table

### 2. Model Updates
- **Admin Model** (`app/Models/Admin.php`):
  - Added `fcm_token` to `$fillable` array
  - Added `routeNotificationForFcm()` method for FCM routing

### 3. Filament Panel Configuration
- **AdminPanelProvider** (`app/Providers/Filament/AdminPanelProvider.php`):
  - Enabled `databaseNotifications()` for storing notifications
  - Enabled `databaseNotificationsPolling('30s')` for live updates (polls every 30 seconds)

### 4. Notification Class
- **VehicleVerificationRequest** (`app/Notifications/VehicleVerificationRequest.php`):
  - Implements `ShouldQueue` for async processing
  - Uses `database` and `broadcast` channels
  - `toBroadcast()`: Creates live toast notifications in Filament
  - `toArray()`: Stores notification data in database

### 5. Vehicle Creation Hook
- **CreateVehicle Page** (`app/Filament/Resources/VehicleResource/Pages/CreateVehicle.php`):
  - `afterCreate()` method triggers notifications to all admins
  - Sends both Laravel notification (for Filament) and FCM push (for mobile/browser)

## Setup Instructions

### Step 1: Run Migration
```bash
php artisan migrate
```

This will add the `fcm_token` column to the `admins` table.

### Step 2: Ensure Notifications Table Exists
Laravel should automatically create the `notifications` table when you use database notifications. If it doesn't exist, run:

```bash
php artisan notifications:table
php artisan migrate
```

### Step 3: Set Up Broadcasting (Optional - for Real-time Updates)
For true "live" updates without polling, you can set up Laravel Reverb or Pusher:

```bash
php artisan install:broadcasting
```

Then update `.env`:
```env
BROADCAST_DRIVER=reverb
# or
BROADCAST_DRIVER=pusher
```

**Note**: The current implementation uses polling (30s), which works without additional setup. Broadcasting is optional for better performance.

### Step 4: Configure Queue (Required)
Since notifications implement `ShouldQueue`, ensure your queue is running:

```bash
php artisan queue:work
```

Or use a supervisor/systemd service for production.

### Step 5: Update Admin FCM Tokens
Admins need to register their FCM tokens. You can:
1. Add a form field in Filament Admin Resource to update FCM token
2. Create an API endpoint for admins to update their FCM token
3. Manually update via database

Example API endpoint (if needed):
```php
Route::post('/admin/fcm-token', function (Request $request) {
    $admin = Auth::guard('admin')->user();
    $admin->update(['fcm_token' => $request->fcm_token]);
    return response()->json(['message' => 'FCM token updated']);
})->middleware('auth:admin');
```

## How It Works

### When a Vehicle is Created:

1. **User adds vehicle** → `CreateVehicle::afterCreate()` is triggered

2. **Laravel Notification** (for Filament):
   - Notification is queued and sent to all admins
   - Stored in `notifications` table (database channel)
   - Broadcasted via Laravel Broadcasting (broadcast channel)
   - Filament polls every 30 seconds and shows toast notification
   - Notification appears in the bell icon (🔔) in Filament

3. **FCM Push Notification** (for mobile/browser):
   - `sendFcmNotification()` method sends push notification directly
   - Uses existing Firebase setup (`kreait/laravel-firebase`)
   - Sends to admin's device if `fcm_token` is set

### Notification Flow:

```
Vehicle Created
    ↓
afterCreate() hook
    ↓
┌─────────────────────────────────────┐
│  For each Admin:                    │
│  ├─ Laravel Notification            │
│  │  ├─ Database (stored)            │
│  │  └─ Broadcast (live toast)       │
│  └─ FCM Push (mobile/browser)        │
└─────────────────────────────────────┘
```

## Testing

1. **Create a test vehicle** in Filament admin panel
2. **Check Filament notifications**:
   - Should see toast notification (if admin panel is open)
   - Should see notification in bell icon
3. **Check FCM push**:
   - Admin should receive push notification on mobile/browser (if FCM token is set)

## Troubleshooting

### Notifications not appearing in Filament:
- Check if queue is running: `php artisan queue:work`
- Check `notifications` table exists
- Verify `databaseNotifications()` is enabled in AdminPanelProvider
- Check browser console for errors

### FCM push not working:
- Verify Firebase credentials file exists
- Check admin has `fcm_token` set
- Check Laravel logs for FCM errors
- Verify Firebase SDK is properly configured

### Polling not working:
- Increase polling interval if needed: `databaseNotificationsPolling('60s')`
- For real-time updates, set up broadcasting (Reverb/Pusher)

## Next Steps (Optional Enhancements)

1. **Add FCM token management in Filament**:
   - Create AdminResource with FCM token field
   - Allow admins to update their own FCM token

2. **Add notification preferences**:
   - Allow admins to enable/disable notifications
   - Add notification types (email, push, in-app)

3. **Improve real-time updates**:
   - Set up Laravel Reverb for true WebSocket-based live updates
   - Replace polling with WebSocket connections

4. **Add notification actions**:
   - Add "View Vehicle" button in notification
   - Add "Approve/Reject" quick actions

