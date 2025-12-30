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

];

