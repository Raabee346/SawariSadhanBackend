<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\VendorProfileController;

// Authentication Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile/email', [AuthController::class, 'updateEmail']);
    Route::put('/profile/password', [AuthController::class, 'updatePassword']);
    
    // User Profile Routes
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserProfileController::class, 'show']);
        Route::post('/profile', [UserProfileController::class, 'updateOrCreate']);
        Route::post('/profile/picture', [UserProfileController::class, 'updateProfilePicture']);
        Route::delete('/profile/picture', [UserProfileController::class, 'deleteProfilePicture']);
    });

    // Vendor Profile Routes
    Route::prefix('vendor')->group(function () {
        Route::get('/profile', [VendorProfileController::class, 'show']);
        Route::post('/profile', [VendorProfileController::class, 'updateOrCreate']);
        Route::post('/profile/document', [VendorProfileController::class, 'uploadDocument']);
        Route::post('/profile/service-area', [VendorProfileController::class, 'updateServiceArea']);
        
        // Availability Routes
        Route::get('/availability', [VendorProfileController::class, 'getAvailability']);
        Route::post('/availability', [VendorProfileController::class, 'updateAvailability']);
        
        // Status Toggle Routes
        Route::post('/toggle-online', [VendorProfileController::class, 'toggleOnlineStatus']);
        Route::post('/toggle-available', [VendorProfileController::class, 'toggleAvailableStatus']);
    });
});