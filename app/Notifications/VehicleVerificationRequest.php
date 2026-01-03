<?php

namespace App\Notifications;

use App\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class VehicleVerificationRequest extends Notification
{
    // Removed ShouldQueue to send synchronously for immediate storage
    // This ensures notifications are stored in database immediately

    public function __construct(public Vehicle $vehicle) 
    {
        Log::info('VehicleVerificationRequest notification created', [
            'vehicle_id' => $this->vehicle->id,
            'registration_number' => $this->vehicle->registration_number ?? 'N/A',
        ]);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Use database for Filament notifications (stored in notifications table)
        // Broadcast is optional - only works if BROADCAST_DRIVER is set to database/reverb/pusher
        // For now, use database only since it works with Filament polling
        // FCM will be sent separately in CreateVehicle::sendFcmNotification()
        return ['database'];
    }
    
    /**
     * Determine if the notification should be sent immediately.
     * For sync queue, this ensures immediate delivery
     */
    public function shouldSend($notifiable, $channel): bool
    {
        return true;
    }

    /**
     * Get the broadcast representation of the notification.
     * This creates the "live" toast notification in Filament
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        // Get user name - prefer user relationship, fallback to owner_name
        $userName = $this->vehicle->user?->name ?? $this->vehicle->owner_name ?? 'Unknown User';
        
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => static::class,
            'data' => [
                'format' => 'filament', // Required by Filament
                'title' => 'New Vehicle Verification Request',
                'body' => "{$userName} has submitted a new vehicle for verification.",
                'vehicle_id' => $this->vehicle->id,
                'registration_number' => $this->vehicle->registration_number,
                'user_name' => $userName,
                'icon' => 'heroicon-o-truck',
            ],
            'read_at' => null,
        ]);
    }

    /**
     * Get the array representation of the notification.
     * This is stored in the database and shown in the Filament notification bell
     * Notifications persist until manually cleared by admin - even after being clicked/read
     * 
     * IMPORTANT: We do NOT include 'format' => 'filament' here because Filament's
     * base DatabaseNotifications component automatically DELETES notifications with
     * format = 'filament' when they're marked as read. By removing it, we prevent
     * auto-deletion while still allowing Filament to display the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // Get user name - prefer user relationship, fallback to owner_name
        $userName = $this->vehicle->user?->name ?? $this->vehicle->owner_name ?? 'Unknown User';
        
        return [
            // We MUST include 'format' => 'filament' for Filament to display the notification
            // But we'll prevent deletion by overriding the markAsRead method in DatabaseNotifications
            'format' => 'filament',
            'vehicle_id' => $this->vehicle->id,
            'registration_number' => $this->vehicle->registration_number,
            'owner_name' => $this->vehicle->owner_name,
            'user_name' => $userName,
            'message' => "{$userName} has submitted a new vehicle for verification.",
            'title' => 'New Vehicle Verification Request',
            'body' => "{$userName} has submitted a new vehicle for verification.",
            'icon' => 'heroicon-o-truck',
            'iconColor' => 'warning',
            'url' => '/admin/vehicles/' . $this->vehicle->id,
            // Notification persists in database even after being clicked/read
            // Our custom DatabaseNotifications component prevents auto-deletion
        ];
    }
}
