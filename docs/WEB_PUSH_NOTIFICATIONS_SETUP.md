# Web Push Notifications Setup for Admin Panel

This guide explains how to set up background push notifications for admins, which work even when the browser tab is closed.

## Features

✅ **Background Notifications**: Receive notifications even when the browser tab is closed  
✅ **Foreground Notifications**: Receive notifications when the tab is open  
✅ **Auto Token Capture**: FCM token is automatically captured and saved on admin login  
✅ **Service Worker**: Handles background message delivery  

## Prerequisites

1. Firebase project with Web App configured
2. Firebase Cloud Messaging (FCM) enabled
3. VAPID key generated for web push

## Setup Steps

### 1. Get Firebase Web App Configuration

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Select your project (`sawarisewa-34f2e`)
3. Go to **Project Settings** (gear icon)
4. Scroll down to **Your apps** section
5. If you don't have a Web App, click **Add app** → **Web** (</> icon)
6. Register your app and copy the configuration values

### 2. Generate VAPID Key

1. In Firebase Console, go to **Project Settings** → **Cloud Messaging** tab
2. Scroll to **Web configuration** section
3. Under **Web Push certificates**, click **Generate key pair**
4. Copy the **Key pair** (this is your VAPID key)

### 3. Add Environment Variables

Add these to your `.env` file:

```env
# Firebase Web App Configuration (for client-side FCM)
FIREBASE_API_KEY=your-api-key-here
FIREBASE_AUTH_DOMAIN=your-project-id.firebaseapp.com
FIREBASE_PROJECT_ID=sawarisewa-34f2e
FIREBASE_STORAGE_BUCKET=sawarisewa-34f2e.firebasestorage.app
FIREBASE_MESSAGING_SENDER_ID=your-sender-id-here
FIREBASE_APP_ID=your-app-id-here

# VAPID Key for Web Push (required for background notifications)
FIREBASE_VAPID_KEY=your-vapid-key-here
```

### 4. How It Works

1. **On Admin Login**:
   - JavaScript automatically requests notification permission
   - Registers service worker (`/firebase-messaging-sw.js`)
   - Captures FCM token from Firebase
   - Sends token to `/api/admin/fcm-token` endpoint
   - Token is saved to `admins.fcm_token` column

2. **When Notification is Sent**:
   - If tab is **open**: Notification appears in browser
   - If tab is **closed**: Service worker receives background message and shows notification
   - Clicking notification opens/focuses the admin panel

3. **Service Worker**:
   - Located at `/firebase-messaging-sw.js`
   - Handles background messages
   - Shows notifications even when tab is closed
   - Handles notification clicks

## Testing

### Test Background Notifications

1. **Login to Admin Panel**: `/admin`
2. **Check Browser Console**: Look for `[Admin FCM]` messages
3. **Check Database**: Verify `fcm_token` is saved in `admins` table
4. **Close the Tab**: Keep browser open but close the admin tab
5. **Send Test Notification**: Create a vehicle or trigger a notification
6. **Verify**: You should receive a notification even with tab closed

### Test Foreground Notifications

1. **Keep Tab Open**: Stay on admin panel
2. **Send Test Notification**: Create a vehicle
3. **Verify**: Notification appears in browser

## Troubleshooting

### Notifications Not Working

1. **Check Browser Console**:
   - Look for `[Admin FCM]` error messages
   - Check if service worker is registered
   - Verify Firebase is initialized

2. **Check Service Worker**:
   - Open DevTools → Application → Service Workers
   - Verify service worker is registered and active
   - Check for errors in service worker console

3. **Check Permission**:
   - Browser must have notification permission granted
   - Check in browser settings: `chrome://settings/content/notifications`

4. **Check Environment Variables**:
   - Verify all Firebase config values are set in `.env`
   - Verify VAPID key is correct
   - Clear config cache: `php artisan config:clear`

5. **Check FCM Token**:
   - Verify token is saved in database
   - Check Laravel logs for "Admin FCM token updated" message

### Service Worker Not Registering

- Ensure `/firebase-messaging-sw.js` route is accessible
- Check browser console for service worker registration errors
- Verify HTTPS is used (required for service workers in production)

### Background Messages Not Received

- Verify VAPID key is correctly set
- Check Firebase Console → Cloud Messaging → Web Push certificates
- Ensure service worker is active and registered
- Check service worker console for errors

## Browser Support

✅ **Chrome/Edge**: Full support  
✅ **Firefox**: Full support  
✅ **Safari**: Limited support (requires macOS/iOS)  
❌ **IE**: Not supported  

## Security Notes

- Service worker must be served over HTTPS (except localhost)
- VAPID key should be kept secret (in `.env`, not committed)
- FCM tokens are user-specific and should be protected

## Files Involved

- `public/firebase-messaging-sw.js` - Service worker (served dynamically)
- `resources/views/filament/hooks/admin-fcm-token-script.blade.php` - Client-side FCM code
- `resources/views/filament/hooks/admin-fcm-meta-tags.blade.php` - Firebase config meta tags
- `app/Http/Controllers/AdminFcmTokenController.php` - API endpoint for token updates
- `routes/web.php` - Service worker route
- `config/firebase.php` - Firebase configuration

