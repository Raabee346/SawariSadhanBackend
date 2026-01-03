/**
 * Admin FCM Token Auto-Capture
 * Automatically captures and saves FCM token when admin logs into Filament
 */

(function() {
    'use strict';

    // Only run if Firebase is available and we're in admin panel
    if (typeof firebase === 'undefined' || !window.location.pathname.includes('/admin')) {
        return;
    }

    // Check if admin is authenticated (check for admin session)
    // We'll send the token on page load if admin is logged in
    
    function initializeFCM() {
        // Check if browser supports notifications
        if (!('Notification' in window)) {
            console.log('This browser does not support notifications');
            return;
        }

        // Check if service worker is supported
        if (!('serviceWorker' in navigator)) {
            console.log('This browser does not support service workers');
            return;
        }

        // Request notification permission
        Notification.requestPermission().then(function(permission) {
            if (permission === 'granted') {
                // Initialize Firebase Cloud Messaging
                const messaging = firebase.messaging();

                // Get FCM token
                messaging.getToken({
                    vapidKey: getVapidKey() // You'll need to set this
                }).then(function(currentToken) {
                    if (currentToken) {
                        // Send token to server
                        sendTokenToServer(currentToken);
                    } else {
                        console.log('No FCM token available');
                    }
                }).catch(function(err) {
                    console.log('An error occurred while retrieving token:', err);
                });

                // Handle token refresh
                messaging.onTokenRefresh(function() {
                    messaging.getToken({
                        vapidKey: getVapidKey()
                    }).then(function(refreshedToken) {
                        if (refreshedToken) {
                            sendTokenToServer(refreshedToken);
                        }
                    }).catch(function(err) {
                        console.log('Unable to retrieve refreshed token:', err);
                    });
                });
            } else {
                console.log('Notification permission denied');
            }
        });
    }

    function sendTokenToServer(token) {
        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        if (!csrfToken) {
            console.error('CSRF token not found');
            return;
        }

        // Send token to server
        fetch('/api/admin/fcm-token', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                fcm_token: token
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('FCM token saved successfully');
            } else {
                console.error('Failed to save FCM token:', data.message);
            }
        })
        .catch(error => {
            console.error('Error sending FCM token to server:', error);
        });
    }

    function getVapidKey() {
        // Get VAPID key from meta tag or config
        // You'll need to add this to your Filament layout
        const vapidKey = document.querySelector('meta[name="fcm-vapid-key"]')?.getAttribute('content');
        return vapidKey || ''; // Return empty string if not found
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeFCM);
    } else {
        initializeFCM();
    }
})();

