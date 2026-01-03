# Admin FCM Token Setup Guide

## How to Set Your FCM Token as an Admin

To receive push notifications for vehicle verification requests, you need to set your FCM token in the Filament admin panel.

### Steps:

1. **Log in to Filament Admin Panel**
   - Go to `/admin` in your browser
   - Log in with your admin credentials

2. **Navigate to Admins Section**
   - Click on **Settings** in the left sidebar
   - Click on **Admins** (or go directly to `/admin/admins`)

3. **Find Your Admin Record**
   - Look for your admin account (by name or email)
   - Click the **Edit** button (pencil icon) on your record

4. **Add Your FCM Token**
   - Scroll down to the **FCM Token** field
   - Paste your FCM token (obtained from your mobile app or browser)
   - Click **Save**

5. **Verify**
   - After saving, you should see a success notification
   - The FCM Token column in the table will show a green checkmark if the token is set

## Getting Your FCM Token

### From Mobile App:
If you have a mobile app that uses Firebase Cloud Messaging, the FCM token is automatically generated. You can:
- Check the app logs for the FCM token
- Use Firebase Console to see registered tokens
- Contact the app developer for the token

### From Browser:
For web push notifications, you need to:
1. Request notification permission in the browser
2. Get the FCM token from the browser's service worker
3. Copy and paste it into the admin panel

## Testing Notifications

After setting your FCM token:
1. Create a new vehicle in the admin panel
2. You should receive:
   - A **Filament notification** (toast + bell icon) in the admin panel
   - An **FCM push notification** on your device/browser (if FCM token is set)

## Troubleshooting

### Notifications not appearing in Filament:
- Check if queue is running: `php artisan queue:work`
- Check browser console for errors
- Verify `notifications` table exists in database

### FCM push not working:
- Verify FCM token is correctly set (no extra spaces)
- Check Firebase credentials file exists
- Check Laravel logs for FCM errors
- Verify Firebase project is correctly configured

### FCM Token column shows red X:
- Your FCM token is not set
- Go to Settings > Admins > Edit your record > Add FCM token > Save

