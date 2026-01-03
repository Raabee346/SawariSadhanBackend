<?php

namespace App\Filament\Resources\VehicleResource\Pages;

use App\Filament\Resources\VehicleResource;
use App\Models\Admin;
use App\Notifications\VehicleVerificationRequest;
use App\Services\NepalDateService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Store BS dates directly (no conversion)
        return $data;
    }

    /**
     * After vehicle is created, notify all admins
     */
    protected function afterCreate(): void
    {
        // Log immediately to verify this method is being called
        Log::info('=== CreateVehicle::afterCreate() CALLED ===', [
            'record_id' => $this->record->id ?? 'NO_ID',
            'record_class' => get_class($this->record),
        ]);
        
        $vehicle = $this->record;
        
        if (!$vehicle || !$vehicle->id) {
            Log::error('Vehicle record is null or has no ID in afterCreate()', [
                'record' => $vehicle,
            ]);
            return;
        }
        
        // Get all admins
        $admins = Admin::all();

        Log::info('Vehicle created, notifying admins', [
            'vehicle_id' => $vehicle->id,
            'registration_number' => $vehicle->registration_number ?? 'N/A',
            'admin_count' => $admins->count(),
        ]);

        // Send notification to each admin
        foreach ($admins as $admin) {
            try {
                Log::info('Sending notification to admin', [
                    'admin_id' => $admin->id,
                    'admin_email' => $admin->email,
                    'has_fcm_token' => !empty($admin->fcm_token),
                    'vehicle_id' => $vehicle->id,
                ]);

                // Send Laravel notification (for Filament UI - database channel)
                // Since queue is sync, this will be processed immediately
                $notification = new VehicleVerificationRequest($vehicle);
                $admin->notify($notification);
                
                // Get the notification ID after it's been sent (it gets an ID when stored)
                $latestNotification = \DB::table('notifications')
                    ->where('notifiable_type', \App\Models\Admin::class)
                    ->where('notifiable_id', $admin->id)
                    ->latest()
                    ->first();
                
                Log::info('Laravel notification sent to admin', [
                    'admin_id' => $admin->id,
                    'notification_id' => $latestNotification->id ?? 'N/A',
                    'notification_stored' => $latestNotification !== null,
                ]);
                
                // Also send FCM notification directly (for web push)
                $this->sendFcmNotification($admin, $vehicle);
            } catch (\Exception $e) {
                Log::error('Failed to send vehicle verification notification to admin', [
                    'admin_id' => $admin->id,
                    'vehicle_id' => $vehicle->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    /**
     * Send FCM notification to admin
     */
    private function sendFcmNotification(Admin $admin, $vehicle): void
    {
        try {
            if (!$admin->fcm_token) {
                Log::warning('Admin has no FCM token, skipping FCM notification', [
                    'admin_id' => $admin->id,
                    'admin_email' => $admin->email,
                ]);
                return;
            }

            if (!class_exists(\Kreait\Laravel\Firebase\Facades\Firebase::class)) {
                Log::warning('Firebase facade not found, skipping FCM notification', [
                    'admin_id' => $admin->id,
                ]);
                return;
            }

            Log::info('Attempting to send FCM notification', [
                'admin_id' => $admin->id,
                'fcm_token_preview' => substr($admin->fcm_token, 0, 20) . '...',
                'vehicle_id' => $vehicle->id,
            ]);

            $messaging = \Kreait\Laravel\Firebase\Facades\Firebase::messaging();
            
            // Get user name - prefer user relationship, fallback to owner_name
            $userName = $vehicle->user?->name ?? $vehicle->owner_name ?? 'Unknown User';
            
            $title = 'New Vehicle Verification Request';
            $body = "{$userName} has submitted a new vehicle for verification.";
            $data = [
                'type' => 'vehicle_verification_request',
                'vehicle_id' => (string) $vehicle->id,
                'registration_number' => $vehicle->registration_number,
                'user_name' => $userName,
                'url' => '/admin/vehicles/' . $vehicle->id, // Add URL for notification click
            ];
            
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $admin->fcm_token)
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData($data);

            $messaging->send($message);
            
            Log::info('FCM notification sent successfully to admin', [
                'admin_id' => $admin->id,
                'vehicle_id' => $vehicle->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send FCM notification to admin', [
                'admin_id' => $admin->id,
                'vehicle_id' => $vehicle->id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
