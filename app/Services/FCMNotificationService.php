<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Laravel\Firebase\Facades\Firebase;

class FCMNotificationService
{
    protected $messaging;
    protected $isAvailable = false;

    public function __construct()
    {
        try {
            // Check if Firebase classes are available
            $firebaseFacadeClass = 'Kreait\Laravel\Firebase\Facades\Firebase';
            $cloudMessageClass = 'Kreait\Firebase\Messaging\CloudMessage';
            
            if (class_exists($firebaseFacadeClass) && class_exists($cloudMessageClass)) {
                try {
                    // Get credentials path from config or environment
                    $credentialsPath = config('firebase.credentials.file');
                    
                    // Also check GOOGLE_APPLICATION_CREDENTIALS environment variable
                    if (empty($credentialsPath) || !file_exists($credentialsPath)) {
                        $credentialsPath = env('GOOGLE_APPLICATION_CREDENTIALS');
                    }
                    
                    // Fallback to default path
                    if (empty($credentialsPath) || !file_exists($credentialsPath)) {
                        $credentialsPath = base_path('storage/app/firebase/sawarisewa-34f2e-firebase-adminsdk-fbsvc-1bbe5f1da9.json');
                    }
                    
                    // Check if credentials file exists
                    if (!file_exists($credentialsPath)) {
                        Log::error('Firebase credentials file not found', [
                            'path' => $credentialsPath,
                            'resolved_path' => realpath($credentialsPath) ?: 'file does not exist',
                            'config_path' => config('firebase.credentials.file'),
                            'env_path' => env('GOOGLE_APPLICATION_CREDENTIALS')
                        ]);
                        $this->isAvailable = false;
                        return;
                    }
                    
                    // Verify credentials file is readable
                    if (!is_readable($credentialsPath)) {
                        Log::error('Firebase credentials file is not readable', ['path' => $credentialsPath]);
                        $this->isAvailable = false;
                        return;
                    }
                    
                    // Try to read project ID from credentials file
                    $credentialsContent = file_get_contents($credentialsPath);
                    $credentials = json_decode($credentialsContent, true);
                    
                    if (!$credentials) {
                        Log::error('Firebase credentials file is not valid JSON', ['path' => $credentialsPath]);
                        $this->isAvailable = false;
                        return;
                    }
                    
                    // Get project ID from credentials file or config
                    $projectId = $credentials['project_id'] ?? config('firebase.project_id', 'sawarisewa-34f2e');
                    
                    if (empty($projectId)) {
                        Log::error('Firebase project_id not found in credentials file or config', [
                            'path' => $credentialsPath,
                            'has_project_id_in_file' => isset($credentials['project_id']),
                            'config_project_id' => config('firebase.project_id')
                        ]);
                        $this->isAvailable = false;
                        return;
                    }
                    
                    // Set GOOGLE_APPLICATION_CREDENTIALS environment variable if not set
                    // This helps the Firebase SDK find the credentials
                    if (!env('GOOGLE_APPLICATION_CREDENTIALS')) {
                        putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $credentialsPath);
                    }
                    
                    Log::info('Firebase credentials loaded', [
                        'project_id' => $projectId,
                        'credentials_path' => $credentialsPath,
                        'file_exists' => file_exists($credentialsPath),
                        'file_readable' => is_readable($credentialsPath)
                    ]);
                    
                    // Initialize Firebase messaging
                    // The package should automatically read from GOOGLE_APPLICATION_CREDENTIALS or config
                    $firebase = \Kreait\Laravel\Firebase\Facades\Firebase::messaging();
                    $this->messaging = $firebase;
                    $this->isAvailable = true;
                    Log::info('Firebase FCM initialized successfully', ['project_id' => $projectId]);
                } catch (\Exception $e) {
                    Log::error('Firebase messaging initialization failed', [
                        'error' => $e->getMessage(),
                        'class' => get_class($e),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->isAvailable = false;
                }
            } else {
                Log::warning('Firebase classes not found. FCM notifications will be disabled. Make sure kreait/laravel-firebase is installed and service provider is registered.');
                $this->isAvailable = false;
            }
        } catch (\Exception $e) {
            Log::error('FCMNotificationService constructor error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->isAvailable = false;
        }
    }

    /**
     * Send notification to a single user
     */
    public function sendToUser(User $user, string $title, string $body, array $data = [])
    {
        if (!$this->isAvailable) {
            Log::debug("FCM not available, skipping notification to user {$user->id}");
            return false;
        }

        if (!$user->fcm_token) {
            Log::warning("No FCM token found for user {$user->id}");
            return false;
        }

        try {
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $user->fcm_token)
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);
            Log::info("FCM notification sent to user {$user->id}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send FCM notification to user {$user->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to multiple users (vendors)
     */
    public function sendToMultipleUsers(array $userIds, string $title, string $body, array $data = [])
    {
        $users = User::whereIn('id', $userIds)
            ->whereNotNull('fcm_token')
            ->get();

        $successCount = 0;
        foreach ($users as $user) {
            if ($this->sendToUser($user, $title, $body, $data)) {
                $successCount++;
            }
        }

        Log::info("Sent FCM notifications to {$successCount} out of " . count($users) . " users");
        return $successCount;
    }

    /**
     * Send notification to all online vendors
     * Queries both User and Vendor models
     */
    public function sendToOnlineVendors(string $title, string $body, array $data = [])
    {
        // Get vendors from Vendor model
        $vendors = Vendor::whereHas('profile', function ($query) {
            $query->where('is_online', true)
                  ->where('is_available', true);
        })
        ->whereNotNull('fcm_token')
        ->get();

        $successCount = 0;
        foreach ($vendors as $vendor) {
            if ($this->sendToVendor($vendor, $title, $body, $data)) {
                $successCount++;
            }
        }

        Log::info("Sent FCM notifications to {$successCount} online vendors");
        return $successCount;
    }
    
    /**
     * Send notification to an admin
     */
    public function sendToAdmin($admin, string $title, string $body, array $data = [])
    {
        if (!$this->isAvailable) {
            Log::debug("FCM not available, skipping notification to admin {$admin->id}");
            return false;
        }

        if (!$admin->fcm_token) {
            Log::warning("No FCM token found for admin {$admin->id}");
            return false;
        }

        try {
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $admin->fcm_token)
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);
            Log::info("FCM notification sent to admin {$admin->id}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send FCM notification to admin {$admin->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to a vendor (Vendor model)
     */
    public function sendToVendor(Vendor $vendor, string $title, string $body, array $data = [])
    {
        if (!$this->isAvailable) {
            Log::warning("FCM not available, skipping notification to vendor {$vendor->id}");
            return false;
        }

        // Refresh vendor to ensure we have latest FCM token
        $vendor->refresh();
        
        if (!$vendor->fcm_token || empty(trim($vendor->fcm_token))) {
            Log::warning("No FCM token found for vendor", [
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->name,
                'fcm_token_is_null' => $vendor->fcm_token === null,
                'fcm_token_is_empty' => $vendor->fcm_token === '',
            ]);
            return false;
        }

        try {
            // Create message with both notification and data payload
            // When app is in background, Android shows notification automatically
            // When app is in foreground, onMessageReceived is called
            // Data payload ensures the app can process the notification even when in background
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $vendor->fcm_token)
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData($data)
                // Set priority to high to ensure delivery
                ->withAndroidConfig(\Kreait\Firebase\Messaging\AndroidConfig::fromArray([
                    'priority' => 'high',
                ]));

            $this->messaging->send($message);
            Log::info("FCM notification sent to vendor successfully", [
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->name,
                'fcm_token_preview' => substr($vendor->fcm_token, 0, 20) . '...',
            ]);
            return true;
        } catch (\Kreait\Firebase\Exception\Messaging\InvalidArgument $e) {
            Log::error("Invalid FCM token for vendor", [
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->name,
                'error' => $e->getMessage(),
            ]);
            return false;
        } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
            Log::error("FCM token not found (vendor may have uninstalled app)", [
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->name,
                'error' => $e->getMessage(),
            ]);
            // Optionally clear the invalid token
            // $vendor->update(['fcm_token' => null]);
            return false;
        } catch (\Exception $e) {
            Log::error("Failed to send FCM notification to vendor", [
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->name,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Send notification to a topic (broadcast)
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = [])
    {
        if (!$this->isAvailable) {
            Log::debug("FCM not available, skipping notification to topic {$topic}");
            return false;
        }

        try {
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('topic', $topic);
            
            // Only add notification if title or body is provided (for silent data-only messages)
            if (!empty($title) || !empty($body)) {
                $message = $message->withNotification(\Kreait\Firebase\Messaging\Notification::create($title ?: 'Sawari Sewa', $body ?: 'Update'));
            }
            
            $message = $message->withData($data)
                // Set priority to high to ensure delivery
                ->withAndroidConfig(\Kreait\Firebase\Messaging\AndroidConfig::fromArray([
                    'priority' => 'high',
                ]));

            $this->messaging->send($message);
            Log::info("FCM message sent to topic: {$topic}", [
                'has_notification' => !empty($title) || !empty($body),
                'data_keys' => array_keys($data),
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send FCM notification to topic {$topic}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to all users
     */
    public function sendToAllUsers(string $title, string $body, array $data = [])
    {
        if (!$this->isAvailable) {
            Log::warning('FCM not available, skipping broadcast to all users');
            return false;
        }

        try {
            // Send to 'users' topic
            $success = $this->sendToTopic('users', $title, $body, $data);
            
            if ($success) {
                Log::info('Broadcast notification sent to all users via topic');
            }
            
            return $success;
        } catch (\Exception $e) {
            Log::error('Failed to send notification to all users: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to all vendors
     */
    public function sendToAllVendors(string $title, string $body, array $data = [])
    {
        if (!$this->isAvailable) {
            Log::warning('FCM not available, skipping broadcast to all vendors');
            return false;
        }

        try {
            // Send to 'vendors' topic
            $success = $this->sendToTopic('vendors', $title, $body, $data);
            
            if ($success) {
                Log::info('Broadcast notification sent to all vendors via topic');
            }
            
            return $success;
        } catch (\Exception $e) {
            Log::error('Failed to send notification to all vendors: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send renewal request notification to vendors
     * Sends to ALL vendors (online and offline) so they get notified even when not in app
     */
    public function sendNewRenewalRequest($renewalRequest)
    {
        if (!$this->isAvailable) {
            Log::warning('FCM not available, skipping renewal request notification', [
                'renewal_request_id' => $renewalRequest->id ?? null,
            ]);
            return false;
        }

        try {
            // Ensure vehicle relationship is loaded
            if (!$renewalRequest->relationLoaded('vehicle')) {
                $renewalRequest->load('vehicle');
            }
            
            $vehicleNumber = $renewalRequest->vehicle ? $renewalRequest->vehicle->registration_number : 'Unknown';
            $title = "New Bluebook Renewal Request";
            $body = "New request for {$vehicleNumber}";
            
            // Handle pickup_date - it might be a Carbon instance, date string, or string
            $pickupDateFormatted = $renewalRequest->pickup_date;
            if ($pickupDateFormatted instanceof \Carbon\Carbon) {
                $pickupDateFormatted = $pickupDateFormatted->format('Y-m-d');
            } elseif (is_string($pickupDateFormatted)) {
                // If it's already a string in YYYY-MM-DD format, use it as-is
                // If it's in another format, try to parse it
                try {
                    $pickupDateFormatted = \Carbon\Carbon::parse($pickupDateFormatted)->format('Y-m-d');
                } catch (\Exception $e) {
                    // If parsing fails, use the string as-is
                    $pickupDateFormatted = $renewalRequest->pickup_date;
                }
            } else {
                // Fallback: try to convert to string
                $pickupDateFormatted = (string) $renewalRequest->pickup_date;
            }
            
            $data = [
                'type' => 'new_renewal_request',
                'renewal_request_id' => (string) $renewalRequest->id,
                'vehicle_id' => (string) $renewalRequest->vehicle_id,
                'pickup_address' => $renewalRequest->pickup_address,
                'pickup_date' => $pickupDateFormatted,
                'total_amount' => (string) $renewalRequest->total_amount,
                // Include title and body in data payload for data-only message support
                'title' => $title,
                'body' => $body,
            ];

            Log::info('Sending FCM notification for new renewal request', [
                'renewal_request_id' => $renewalRequest->id,
                'vehicle_number' => $vehicleNumber,
            ]);

            // Get renewal request location
            $requestLat = $renewalRequest->pickup_latitude;
            $requestLng = $renewalRequest->pickup_longitude;
            
            $topicSent = false;
            $successCount = 0;
            
            if (!$requestLat || !$requestLng) {
                Log::warning('Renewal request missing pickup coordinates, sending to all vendors via topic', [
                    'renewal_request_id' => $renewalRequest->id,
                ]);
                // If no coordinates, send to all vendors via topic (fallback only)
                $topicSent = $this->sendToTopic('vendors', $title, $body, $data);
            } else {
                // Filter vendors by service area radius - send to vendors within radius
                $vendors = $this->getVendorsWithinRadius($requestLat, $requestLng);
                
                Log::info('Filtered vendors by service area radius', [
                    'renewal_request_id' => $renewalRequest->id,
                    'request_lat' => $requestLat,
                    'request_lng' => $requestLng,
                    'vendors_count' => $vendors->count(),
                ]);
                
                // Send individual notifications only to vendors within service radius
                $failedVendors = [];
                foreach ($vendors as $vendor) {
                    try {
                        // Ensure vendor has FCM token before attempting to send
                        if (empty($vendor->fcm_token)) {
                            Log::warning('Vendor has no FCM token, skipping', [
                                'vendor_id' => $vendor->id,
                                'vendor_name' => $vendor->name,
                            ]);
                            $failedVendors[] = $vendor->id;
                            continue;
                        }
                        
                        if ($this->sendToVendor($vendor, $title, $body, $data)) {
                            $successCount++;
                            Log::info('FCM notification sent to vendor successfully', [
                                'vendor_id' => $vendor->id,
                                'vendor_name' => $vendor->name,
                                'renewal_request_id' => $renewalRequest->id,
                            ]);
                        } else {
                            Log::warning('FCM notification failed for vendor (sendToVendor returned false)', [
                                'vendor_id' => $vendor->id,
                                'vendor_name' => $vendor->name,
                                'renewal_request_id' => $renewalRequest->id,
                            ]);
                            $failedVendors[] = $vendor->id;
                        }
                    } catch (\Exception $e) {
                        Log::error('Exception while sending notification to vendor', [
                            'vendor_id' => $vendor->id,
                            'vendor_name' => $vendor->name,
                            'renewal_request_id' => $renewalRequest->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        $failedVendors[] = $vendor->id;
                        // Continue with other vendors even if one fails
                    }
                }
                
                // Always send to topic as well to ensure all vendors receive notification
                // This is a backup in case individual token-based notifications fail
                // Vendors subscribed to 'vendors' topic will receive it
                Log::info('Sending notification to vendors topic as backup', [
                    'renewal_request_id' => $renewalRequest->id,
                    'individual_notifications_sent' => $successCount,
                    'failed_vendors' => $failedVendors,
                ]);
                $topicSent = $this->sendToTopic('vendors', $title, $body, $data);
                
                // If no individual notifications succeeded but we have vendors, log warning
                if ($successCount === 0 && $vendors->count() > 0) {
                    Log::warning('No vendors received individual notifications, but topic notification sent', [
                        'renewal_request_id' => $renewalRequest->id,
                        'vendors_count' => $vendors->count(),
                        'failed_vendor_ids' => $failedVendors,
                    ]);
                }
            }

            Log::info('FCM notification sent for renewal request - FINAL SUMMARY', [
                'renewal_request_id' => $renewalRequest->id,
                'request_lat' => $requestLat,
                'request_lng' => $requestLng,
                'vendors_found_in_radius' => isset($vendors) ? $vendors->count() : 0,
                'vendors_notified_individual' => $successCount,
                'topic_sent' => $topicSent,
                'has_location' => !empty($requestLat) && !empty($requestLng),
                'notification_method' => $topicSent ? 'topic (all vendors subscribed)' : ($successCount > 0 ? 'individual tokens only' : 'failed'),
                'note' => 'All vendors subscribed to "vendors" topic will receive notification via topic. Individual token notifications sent to ' . $successCount . ' vendors.',
            ]);

            // Return true if topic was sent OR individual notifications succeeded
            // Topic ensures all vendors receive notification even if individual tokens fail
            return $successCount > 0 || $topicSent;
        } catch (\Exception $e) {
            Log::error('Error sending FCM notification for renewal request', [
                'renewal_request_id' => $renewalRequest->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Get vendors within service radius of a location
     * Uses Haversine formula to calculate distance
     * If vendor doesn't have service area set, include them (fallback to show all vendors)
     */
    private function getVendorsWithinRadius($latitude, $longitude)
    {
        // Get all vendors with FCM tokens
        // Include vendors with or without service area set
        $vendors = \App\Models\Vendor::whereNotNull('fcm_token')
            ->with('profile')
            ->get();
        
        Log::info('Getting vendors within radius', [
            'request_lat' => $latitude,
            'request_lng' => $longitude,
            'total_vendors_with_fcm' => $vendors->count(),
            'vendor_ids' => $vendors->pluck('id')->toArray(),
            'vendor_fcm_tokens' => $vendors->pluck('fcm_token')->map(function($token) {
                return $token ? substr($token, 0, 20) . '...' : 'null';
            })->toArray(),
        ]);
        
        $vendorsWithinRadius = collect();
        
        foreach ($vendors as $vendor) {
            // Refresh profile to ensure we have latest data
            $vendor->load('profile');
            $profile = $vendor->profile;
            
            // Log vendor details for debugging
            Log::info('Checking vendor for notification', [
                'vendor_id' => $vendor->id,
                'vendor_name' => $vendor->name,
                'has_fcm_token' => !empty($vendor->fcm_token),
                'fcm_token_preview' => $vendor->fcm_token ? substr($vendor->fcm_token, 0, 20) . '...' : 'null',
                'has_profile' => $profile !== null,
                'has_service_lat' => $profile && $profile->service_latitude !== null,
                'has_service_lng' => $profile && $profile->service_longitude !== null,
                'service_lat' => $profile ? $profile->service_latitude : null,
                'service_lng' => $profile ? $profile->service_longitude : null,
                'service_radius' => $profile ? $profile->service_radius : null,
            ]);
            
            // If vendor doesn't have profile or service area set, include them anyway
            // This ensures vendors without service area configured still receive notifications
            if (!$profile || !$profile->service_latitude || !$profile->service_longitude) {
                Log::info('Vendor without service area - will receive notification (fallback)', [
                    'vendor_id' => $vendor->id,
                    'has_profile' => $profile !== null,
                    'has_service_area' => $profile && $profile->service_latitude && $profile->service_longitude,
                ]);
                $vendorsWithinRadius->push($vendor);
                continue;
            }
            
            $vendorLat = (float) $profile->service_latitude;
            $vendorLng = (float) $profile->service_longitude;
            // service_radius is stored in meters, convert to kilometers for comparison
            $radiusMeters = $profile->service_radius ?? 50000; // Default 50000 meters = 50km
            $radius = $radiusMeters / 1000; // Convert meters to kilometers
            
            // Validate coordinates
            if ($vendorLat == 0 || $vendorLng == 0 || abs($vendorLat) > 90 || abs($vendorLng) > 180) {
                Log::warning('Vendor has invalid service coordinates - including in fallback', [
                    'vendor_id' => $vendor->id,
                    'vendor_lat' => $vendorLat,
                    'vendor_lng' => $vendorLng,
                ]);
                $vendorsWithinRadius->push($vendor);
                continue;
            }
            
            // Calculate distance using Haversine formula (returns kilometers)
            $distance = $this->calculateDistance($latitude, $longitude, $vendorLat, $vendorLng);
            
            // Check if request location is within vendor's service radius (both in kilometers)
            if ($distance <= $radius) {
                $vendorsWithinRadius->push($vendor);
                Log::info('Vendor within service radius - will receive notification', [
                    'vendor_id' => $vendor->id,
                    'vendor_name' => $vendor->name,
                    'request_lat' => $latitude,
                    'request_lng' => $longitude,
                    'vendor_lat' => $vendorLat,
                    'vendor_lng' => $vendorLng,
                    'distance_km' => round($distance, 2),
                    'service_radius_km' => $radius,
                    'service_radius_meters' => $radiusMeters,
                ]);
            } else {
                Log::info('Vendor outside service radius - will NOT receive notification', [
                    'vendor_id' => $vendor->id,
                    'vendor_name' => $vendor->name,
                    'request_lat' => $latitude,
                    'request_lng' => $longitude,
                    'vendor_lat' => $vendorLat,
                    'vendor_lng' => $vendorLng,
                    'distance_km' => round($distance, 2),
                    'service_radius_km' => $radius,
                    'service_radius_meters' => $radiusMeters,
                ]);
            }
        }
        
        Log::info('Vendors filtered by radius', [
            'total_vendors' => $vendors->count(),
            'vendors_within_radius' => $vendorsWithinRadius->count(),
            'vendor_ids_within_radius' => $vendorsWithinRadius->pluck('id')->toArray(),
        ]);
        
        return $vendorsWithinRadius;
    }
    
    /**
     * Calculate distance between two points using Haversine formula
     * Returns distance in kilometers
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        // Validate input coordinates
        if (!is_numeric($lat1) || !is_numeric($lon1) || !is_numeric($lat2) || !is_numeric($lon2)) {
            Log::error('Invalid coordinates for distance calculation', [
                'lat1' => $lat1,
                'lon1' => $lon1,
                'lat2' => $lat2,
                'lon2' => $lon2,
            ]);
            return PHP_INT_MAX; // Return very large distance if invalid
        }
        
        // Convert to float to ensure proper calculation
        $lat1 = (float) $lat1;
        $lon1 = (float) $lon1;
        $lat2 = (float) $lat2;
        $lon2 = (float) $lon2;
        
        $earthRadius = 6371; // Earth's radius in kilometers
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        $distance = $earthRadius * $c;
        
        Log::debug('Distance calculated', [
            'lat1' => $lat1,
            'lon1' => $lon1,
            'lat2' => $lat2,
            'lon2' => $lon2,
            'distance_km' => round($distance, 2),
        ]);
        
        return $distance;
    }

    /**
     * Notify vendors that request was accepted by another vendor
     * NOTE: This is now DISABLED - we don't notify other vendors when request is accepted
     * They will simply see the request disappear from their available list
     */
    public function notifyRequestAcceptedByOther($renewalRequest)
    {
        // DO NOT send notifications to other vendors
        // The request will automatically disappear from their available requests list
        // This prevents notification spam and confusion
        return 0;
    }

    /**
     * Notify user about request status update
     */
    public function notifyUserRequestUpdate($renewalRequest, string $status)
    {
        $user = $renewalRequest->user;
        
        $titles = [
            'assigned' => 'Request Assigned',
            'in_progress' => 'Service Started',
            'en_route' => 'Rider En Route',
            'arrived' => 'Rider Has Arrived',
            'document_picked_up' => 'Documents Picked Up',
            'at_dotm' => 'At DoTM Office',
            'processing_complete' => 'Processing Complete',
            'en_route_dropoff' => 'Rider En Route for Drop-Off',
            'arrived_dropoff' => 'Rider Arrived for Drop-Off',
            'delivered' => 'Documents Delivered',
            'completed' => 'Service Completed',
            'cancelled' => 'Request Cancelled',
        ];

        $bodies = [
            'assigned' => "A rider has accepted your renewal request",
            'in_progress' => "Rider has started processing your renewal",
            'en_route' => "Rider is on the way to pickup your documents",
            'arrived' => "Rider has arrived at your pickup location. Please be ready with your documents.",
            'document_picked_up' => "Rider has picked up your documents",
            'at_dotm' => "Rider is at DoTM office processing your renewal",
            'processing_complete' => "Your bluebook renewal processing at Yatayat is complete. Rider will deliver your documents soon.",
            'en_route_dropoff' => "Rider is on the way to deliver your documents. Please be ready to receive them.",
            'arrived_dropoff' => "Rider has arrived for document drop-off. Please be ready to receive your documents.",
            'delivered' => "Your documents have been delivered successfully. Thank you for using Sawari Sewa!",
            'completed' => "Your bluebook renewal has been completed",
            'cancelled' => "Your renewal request has been cancelled",
        ];

        $title = $titles[$status] ?? 'Request Update';
        $body = $bodies[$status] ?? "Your request status has been updated to {$status}";

        $data = [
            'type' => 'renewal_request_update',
            'renewal_request_id' => (string) $renewalRequest->id,
            'status' => $status,
            // Include title and body in data payload for notification display
            'title' => $title,
            'body' => $body,
        ];

        return $this->sendToUser($user, $title, $body, $data);
    }

    /**
     * Notify user about vehicle verification status
     */
    public function notifyVehicleVerification($vehicle, string $status)
    {
        if (!$this->isAvailable) {
            Log::warning('FCM not available, skipping vehicle verification notification');
            return false;
        }

        $user = $vehicle->user;
        if (!$user) {
            Log::warning("No user found for vehicle {$vehicle->id}");
            return false;
        }

        // Refresh vehicle to ensure we have latest data including rejection_reason
        $vehicle->refresh();

        $vehicleNumber = $vehicle->registration_number ?? 'N/A';
        
        $titles = [
            'approved' => 'Vehicle Verified!',
            'rejected' => 'Vehicle Verification Rejected',
        ];

        // Build body message
        if ($status === 'approved') {
            $body = "Your vehicle {$vehicleNumber} has been verified and approved. You can now proceed with renewal services.";
        } elseif ($status === 'rejected') {
            $rejectionReason = $vehicle->rejection_reason ?? 'No reason provided';
            $body = "Your vehicle {$vehicleNumber} verification was rejected.\n\nReason: {$rejectionReason}\n\nPlease update your vehicle information and resubmit for verification.";
        } else {
            $body = "Your vehicle {$vehicleNumber} verification status has been updated to {$status}.";
        }

        $title = $titles[$status] ?? 'Vehicle Verification Update';

        $data = [
            'type' => 'vehicle_verification_update',
            'vehicle_id' => (string) $vehicle->id,
            'verification_status' => $status,
            'registration_number' => $vehicleNumber,
            // Include title and body in data payload for data-only message support
            'title' => $title,
            'body' => $body,
        ];

        // Include rejection reason in data payload if rejected
        if ($status === 'rejected' && $vehicle->rejection_reason) {
            $data['rejection_reason'] = $vehicle->rejection_reason;
        }

        $success = $this->sendToUser($user, $title, $body, $data);
        
        if ($success) {
            Log::info('Vehicle verification notification sent', [
                'vehicle_id' => $vehicle->id,
                'user_id' => $user->id,
                'status' => $status,
                'has_rejection_reason' => $status === 'rejected' && !empty($vehicle->rejection_reason),
            ]);
        }
        
        return $success;
    }
}
