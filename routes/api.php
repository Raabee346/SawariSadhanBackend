<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\VendorProfileController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\FiscalYearController;
use App\Http\Controllers\RenewalRequestController;
use App\Http\Controllers\DateConverterController;
use App\Http\Controllers\AdminFcmTokenController;

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
    Route::prefix('user')->middleware('customer')->group(function () {
        Route::get('/profile', [UserProfileController::class, 'show']);
        Route::post('/profile', [UserProfileController::class, 'updateOrCreate']);
        Route::post('/profile/picture', [UserProfileController::class, 'updateProfilePicture']);
        Route::delete('/profile/picture', [UserProfileController::class, 'deleteProfilePicture']);
    });

    // Vehicle Routes
    Route::prefix('vehicles')->group(function () {
        Route::get('/', [VehicleController::class, 'index']);
        Route::post('/', [VehicleController::class, 'store']);
        Route::get('/provinces', [VehicleController::class, 'provinces']);
        Route::get('/{id}', [VehicleController::class, 'show']);
        Route::post('/{id}', [VehicleController::class, 'update']);
        Route::delete('/{id}', [VehicleController::class, 'destroy']);
        Route::post('/{id}/calculate', [VehicleController::class, 'calculate']);
        Route::get('/{id}/check-expiry', [VehicleController::class, 'checkExpiry']);
    });

    // Payment Routes
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::get('/{id}', [PaymentController::class, 'show']);
        Route::put('/{id}/status', [PaymentController::class, 'updateStatus']);
        Route::post('/{id}/verify-khalti', [PaymentController::class, 'verifyKhaltiPayment']);
    });

    // Renewal Request Routes - accessible to both users and vendors
    Route::prefix('renewal-requests')->group(function () {
        // Customer-specific routes (must come before /{id} route to avoid route conflicts)
        Route::middleware('customer')->group(function () {
            Route::get('/', [RenewalRequestController::class, 'index']);
            Route::get('/in-progress', [RenewalRequestController::class, 'getInProgress']);
            Route::post('/', [RenewalRequestController::class, 'store']);
            Route::put('/{id}/status', [RenewalRequestController::class, 'updateStatus']);
        });
        
        // Vendor-specific routes (must come before /{id} route to avoid route conflicts)
        Route::middleware('vendor')->group(function () {
            Route::get('/available', [RenewalRequestController::class, 'getAvailable']);
            Route::get('/my-requests', [RenewalRequestController::class, 'getVendorRequests']);
            Route::post('/{id}/accept', [RenewalRequestController::class, 'accept']);
            Route::post('/{id}/decline', [RenewalRequestController::class, 'decline']);
            Route::post('/{id}/workflow-status', [RenewalRequestController::class, 'updateWorkflowStatus']);
            Route::post('/{id}/document-photo', [RenewalRequestController::class, 'uploadDocumentPhoto']);
            Route::post('/{id}/signature-photo', [RenewalRequestController::class, 'uploadSignaturePhoto']);
        });
        
        // Route accessible to both users and vendors (must come last to avoid conflicts)
        Route::get('/{id}', [RenewalRequestController::class, 'show']);
    });

    // FCM Token Update (available to both users and vendors)
    Route::post('/fcm-token', [RenewalRequestController::class, 'updateFcmToken']);
    // Vendor Profile Routes
    Route::prefix('vendor')->middleware('vendor')->group(function () {
        Route::get('/profile', [VendorProfileController::class, 'show']);
        Route::post('/profile', [VendorProfileController::class, 'updateOrCreate']);
        Route::post('/profile/document', [VendorProfileController::class, 'uploadDocument']);
        Route::post('/profile/documents', [VendorProfileController::class, 'uploadMultipleDocuments']);
        Route::post('/profile/service-area', [VendorProfileController::class, 'updateServiceArea']);
        
        // Availability Routes
        Route::get('/availability', [VendorProfileController::class, 'getAvailability']);
        Route::post('/availability', [VendorProfileController::class, 'updateAvailability']);
        
        // Status Toggle Routes
        Route::post('/toggle-online', [VendorProfileController::class, 'toggleOnlineStatus']);
        Route::post('/toggle-available', [VendorProfileController::class, 'toggleAvailableStatus']);
    });

    
});

// Public Routes
Route::get('/fiscal-years', [FiscalYearController::class, 'index']);
Route::get('/fiscal-years/current', [FiscalYearController::class, 'current']);

// Date Conversion Route (public, no auth required for date conversion)
Route::post('/convert-date', [DateConverterController::class, 'convertDate']);
Route::post('/convert-date-ad-to-bs', [DateConverterController::class, 'convertAdToBs']);

// Khalti Callback (called by Khalti server, no auth required)
Route::post('/payments/khalti/callback', [PaymentController::class, 'khaltiCallback']);

// Admin FCM Token Update (protected by admin session)
Route::middleware(['web', 'auth:admin'])->group(function () {
    Route::post('/admin/fcm-token', [AdminFcmTokenController::class, 'updateFcmToken']);
    
    // Admin Notification Management
    Route::prefix('admin/notifications')->group(function () {
        Route::get('/', [\App\Http\Controllers\AdminNotificationController::class, 'index']);
        Route::post('/{id}/read', [\App\Http\Controllers\AdminNotificationController::class, 'markAsRead']);
        Route::delete('/{id}', [\App\Http\Controllers\AdminNotificationController::class, 'delete']);
        Route::post('/mark-all-read', [\App\Http\Controllers\AdminNotificationController::class, 'markAllAsRead']);
    });
});