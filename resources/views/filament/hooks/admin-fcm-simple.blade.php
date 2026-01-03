<script type="module">
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.0.0/firebase-app.js";
import { getMessaging, getToken, onMessage, isSupported } from "https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging.js";

// Skip on login page
if (window.location.pathname.includes('/admin/login')) {
    // Do nothing on login page
} else {
    const firebaseConfig = {
        apiKey: "AIzaSyBPYfpXqWZViU_g9xaxsgzWiJFr_oNPlDA",
        authDomain: "sawarisewa-34f2e.firebaseapp.com",
        projectId: "sawarisewa-34f2e",
        storageBucket: "sawarisewa-34f2e.firebasestorage.app",
        messagingSenderId: "92166605836",
        appId: "1:92166605836:web:170575d8e5a203029eb229"
    };

    const app = initializeApp(firebaseConfig);

    // Check if messaging is supported
    isSupported().then((supported) => {
        if (!supported) {
            console.warn('[FCM] Firebase Messaging is not supported in this browser');
            return;
        }

        const messaging = getMessaging(app);

        // Function to save token to backend
        function saveTokenToBackend(token) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (!csrfToken) {
                console.error('[FCM] CSRF token not found. Checking alternative sources...');
                // Try to get CSRF token from Laravel's default location
                const csrfInput = document.querySelector('input[name="_token"]');
                if (csrfInput) {
                    console.log('[FCM] Found CSRF token in form input');
                    const tokenValue = csrfInput.value;
                    sendTokenRequest(token, tokenValue);
                } else {
                    console.error('[FCM] CSRF token not found in meta tag or form input');
                    return;
                }
            } else {
                // Get CSRF token value (support both .content and .getAttribute)
                const tokenValue = csrfToken.getAttribute('content') || csrfToken.content;
                sendTokenRequest(token, tokenValue);
            }
        }

        // Helper function to send token request
        function sendTokenRequest(fcmToken, csrfTokenValue) {
            fetch('/api/admin/fcm-token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfTokenValue,
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ fcm_token: fcmToken })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.message || `HTTP ${response.status}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    console.log('[FCM] ✅ Token saved to database');
                } else {
                    console.error('[FCM] ❌ Failed to save:', data.message);
                }
            })
            .catch(error => {
                console.error('[FCM] ❌ Error saving token:', error);
            });
        }

        // Request notification permission
        Notification.requestPermission().then((permission) => {
            if (permission !== 'granted') {
                console.log('[FCM] Notification permission denied');
                return;
            }

            // Register service worker FIRST, then get token
            if ('serviceWorker' in navigator) {
                // Wait a bit for page to fully load
                setTimeout(() => {
                    navigator.serviceWorker.register('/firebase-messaging-sw.js', { scope: '/' })
                        .then((registration) => {
                            console.log('[FCM] Service worker registered successfully');
                            
                            // Wait for service worker to be fully activated
                            return new Promise((resolve) => {
                                // If already active, resolve immediately
                                if (registration.active) {
                                    console.log('[FCM] Service worker is already active');
                                    resolve(registration);
                                } else if (registration.installing) {
                                    // Wait for the state change to 'activated'
                                    registration.installing.addEventListener('statechange', (e) => {
                                        if (e.target.state === 'activated') {
                                            console.log('[FCM] Service worker activated');
                                            resolve(registration);
                                        }
                                    });
                                } else if (registration.waiting) {
                                    // If waiting, activate it
                                    registration.waiting.addEventListener('statechange', (e) => {
                                        if (e.target.state === 'activated') {
                                            console.log('[FCM] Service worker activated from waiting');
                                            resolve(registration);
                                        }
                                    });
                                    // Try to skip waiting
                                    registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                                } else {
                                    // Fallback: wait for ready
                                    navigator.serviceWorker.ready.then(() => {
                                        console.log('[FCM] Service worker ready (fallback)');
                                        resolve(registration);
                                    });
                                }
                            });
                        })
                        .then((registration) => {
                            console.log('[FCM] Service worker is active. Getting token...');
                            // Get token with service worker registration
                            return getToken(messaging, { 
                                vapidKey: 'BP455FC6bZgMs6y7vUbXywNbs3H3JGFnhci2W_QhDfZQ5nWpG2Nc62hbZgL9xWyfHP3BN-jxQ4CGIbJT81ftOsA',
                                serviceWorkerRegistration: registration
                            });
                        })
                        .then((token) => {
                            if (token) {
                                console.log('[FCM] Token generated:', token.substring(0, 30) + '...');
                                saveTokenToBackend(token);
                            } else {
                                console.log('[FCM] No token available - need to request permission');
                            }
                        })
                        .catch((err) => {
                            console.error('[FCM] Error getting token:', err);
                            // If push service fails, try without service worker registration (fallback)
                            console.log('[FCM] Attempting fallback token generation...');
                            getToken(messaging, { 
                                vapidKey: 'BP455FC6bZgMs6y7vUbXywNbs3H3JGFnhci2W_QhDfZQ5nWpG2Nc62hbZgL9xWyfHP3BN-jxQ4CGIbJT81ftOsA'
                            })
                            .then((token) => {
                                if (token) {
                                    console.log('[FCM] Fallback token generated:', token.substring(0, 30) + '...');
                                    saveTokenToBackend(token);
                                }
                            })
                            .catch((fallbackErr) => {
                                console.error('[FCM] Fallback also failed:', fallbackErr);
                                // Even if token generation fails, try to call API with a placeholder to test connectivity
                                console.log('[FCM] Testing API connectivity...');
                                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                                if (csrfToken) {
                                    fetch('/api/admin/fcm-token', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': csrfToken.getAttribute('content') || csrfToken.content,
                                            'Accept': 'application/json'
                                        },
                                        credentials: 'same-origin',
                                        body: JSON.stringify({ fcm_token: 'test-connection' })
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        console.log('[FCM] API test response:', data);
                                    })
                                    .catch(error => {
                                        console.error('[FCM] API test failed:', error);
                                    });
                                }
                            });
                        });
                }, 1000); // Wait 1 second after page load
            } else {
                console.error('[FCM] Service Worker not supported in this browser');
            }

            // Handle foreground messages
            onMessage(messaging, (payload) => {
                console.log('[FCM] Message received:', payload);
                if (Notification.permission === 'granted') {
                    new Notification(payload.notification?.title || 'New Notification', {
                        body: payload.notification?.body || 'You have a new notification',
                        icon: '/favicon.ico'
                    });
                }
            });
        }).catch((err) => {
            console.error('[FCM] Error requesting permission:', err);
        });
    }).catch((err) => {
        console.error('[FCM] Error checking support:', err);
    });
}
</script>
