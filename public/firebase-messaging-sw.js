// Use the compat libraries to avoid "ReferenceError"
importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js');

// Hardcode your config here. 
// Note: These are CLIENT-SIDE keys, so they are safe to be public.
firebase.initializeApp({
    apiKey: "AIzaSyBPYfpXqWZViU_g9xaxsgzWiJFr_oNPlDA",
    authDomain: "sawarisewa-34f2e.firebaseapp.com",
    projectId: "sawarisewa-34f2e",
    storageBucket: "sawarisewa-34f2e.firebasestorage.app",
    messagingSenderId: "92166605836",
    appId: "1:92166605836:web:170575d8e5a203029eb229"
});

const messaging = firebase.messaging();

// Simple Notify: This allows the browser to receive background hits
messaging.onBackgroundMessage((payload) => {
    console.log('Background message received: ', payload);
    const notificationTitle = payload.notification?.title || 'New Notification';
    const notificationOptions = {
        body: payload.notification?.body || 'You have a new notification',
        icon: payload.notification?.icon || '/favicon.ico',
        badge: '/favicon.ico',
        data: payload.data || {},
    };
    self.registration.showNotification(notificationTitle, notificationOptions);
});
