<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\HasBSTimestamps;

class Admin extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasBSTimestamps;

    protected $table = 'admins';

    protected $fillable = [
        'name',
        'email',
        'password',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Determine if the user can access the Filament panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Get the FCM token for routing notifications
     */
    public function routeNotificationForFcm()
    {
        return $this->fcm_token;
    }

    /**
     * Override the default notification behavior to prevent auto-deletion
     * Notifications will persist even after being clicked/read
     */
    public function markNotificationAsRead($notificationId): void
    {
        // Only mark as read, don't delete
        $this->notifications()
            ->where('id', $notificationId)
            ->update(['read_at' => now()]);
    }

    /**
     * Get all notifications including read ones
     */
    public function getAllNotifications()
    {
        return $this->notifications()->orderBy('created_at', 'desc')->get();
    }

}

