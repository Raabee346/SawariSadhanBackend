# Troubleshooting Admin Notifications in Filament

## Issue: Notifications Not Appearing After Vehicle Creation

### Step 1: Check Admin FCM Token
```bash
php artisan tinker
```
```php
\App\Models\Admin::all()->each(function($admin) {
    echo "Admin {$admin->id} ({$admin->email}): " . ($admin->fcm_token ? "Has token" : "NO TOKEN") . PHP_EOL;
});
```

**If no token**: Admin needs to log in to Filament admin panel. The token should be captured automatically.

### Step 2: Check Queue Configuration
```bash
php artisan config:show queue.default
```

Should be `sync` for immediate processing, or `database` if using queue worker.

**If using database queue**: Make sure queue worker is running:
```bash
php artisan queue:work
```

### Step 3: Check Broadcasting Configuration
```bash
php artisan config:show broadcasting.default
```

Should be `database` or `log` for basic setup. For real-time updates, use `reverb` or `pusher`.

### Step 4: Check Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

Look for:
- `Vehicle created, notifying admins`
- `Sending notification to admin`
- `Laravel notification queued for admin`
- `FCM notification sent successfully to admin`
- Any error messages

### Step 5: Check Database Notifications Table
```bash
php artisan tinker
```
```php
\DB::table('notifications')->latest()->take(5)->get();
```

Check if notifications are being stored.

### Step 6: Check Filament Notification Polling
- Filament polls every 30 seconds by default
- Check browser console for errors
- Verify `databaseNotifications()` is enabled in `AdminPanelProvider`

### Step 7: Test Notification Manually
```bash
php artisan tinker
```
```php
$admin = \App\Models\Admin::first();
$vehicle = \App\Models\Vehicle::latest()->first();
$admin->notify(new \App\Notifications\VehicleVerificationRequest($vehicle));
```

### Common Issues:

1. **Queue Worker Not Running**
   - If using `database` queue, run: `php artisan queue:work`
   - Or set `QUEUE_CONNECTION=sync` in `.env`

2. **Admin Has No FCM Token**
   - Admin must log in to Filament
   - Check browser console for `[Admin FCM]` messages
   - Verify token is saved in database

3. **Broadcasting Not Configured**
   - Set `BROADCAST_DRIVER=database` in `.env`
   - Or use `log` for testing

4. **Notifications Table Missing**
   - Run: `php artisan notifications:table && php artisan migrate`

5. **Filament Not Polling**
   - Check `AdminPanelProvider` has `databaseNotificationsPolling('30s')`
   - Check browser console for polling errors

