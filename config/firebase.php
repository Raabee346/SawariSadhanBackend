<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Project Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for Firebase services.
    | The credentials file should be a service account JSON file downloaded
    | from Firebase Console.
    |
    | The kreait/laravel-firebase package reads credentials from:
    | 1. GOOGLE_APPLICATION_CREDENTIALS environment variable
    | 2. config('firebase.credentials.file') path
    | 3. Default location: storage/app/firebase/
    |
    */

    'credentials' => [
        'file' => env('FIREBASE_CREDENTIALS', env('GOOGLE_APPLICATION_CREDENTIALS', base_path('storage/app/firebase/sawarisewa-34f2e-firebase-adminsdk-fbsvc-1bbe5f1da9.json'))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Project ID
    |--------------------------------------------------------------------------
    |
    | The Firebase project ID. This is usually read from the credentials file,
    | but can be explicitly set here if needed.
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID', 'sawarisewa-34f2e'),

    /*
    |--------------------------------------------------------------------------
    | Firebase Database URL
    |--------------------------------------------------------------------------
    |
    | The Firebase Realtime Database URL (if using Realtime Database).
    |
    */

    'database' => [
        'url' => env('FIREBASE_DATABASE_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Storage
    |--------------------------------------------------------------------------
    |
    | Firebase Cloud Storage bucket configuration.
    |
    */

    'storage' => [
        'default_bucket' => env('FIREBASE_STORAGE_BUCKET', 'sawarisewa-34f2e.firebasestorage.app'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase Cloud Messaging (FCM).
    |
    */

    'messaging' => [
        'sender_id' => env('FIREBASE_SENDER_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Web App Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase Web SDK (for browser/client-side use).
    | These values are used for FCM web push notifications.
    |
    */

    'api_key' => env('FIREBASE_API_KEY', 'AIzaSyBPYfpXqWZViU_g9xaxsgzWiJFr_oNPlDA'),
    'auth_domain' => env('FIREBASE_AUTH_DOMAIN', 'sawarisewa-34f2e.firebaseapp.com'),
    'storage_bucket' => env('FIREBASE_STORAGE_BUCKET', 'sawarisewa-34f2e.firebasestorage.app'),
    'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID', '92166605836'),
    'app_id' => env('FIREBASE_APP_ID', '1:92166605836:web:170575d8e5a203029eb229'),
    'measurement_id' => env('FIREBASE_MEASUREMENT_ID', 'G-KHX5ES3HKF'),
    'vapid_key' => env('FIREBASE_VAPID_KEY', 'BP455FC6bZgMs6y7vUbXywNbs3H3JGFnhci2W_QhDfZQ5nWpG2Nc62hbZgL9xWyfHP3BN-jxQ4CGIbJT81ftOsA'),

];

