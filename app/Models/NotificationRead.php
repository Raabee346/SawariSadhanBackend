<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationRead extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_notification_id',
        'user_id',
        'vendor_id',
        'admin_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * Get the notification that was read
     */
    public function notification()
    {
        return $this->belongsTo(AppNotification::class, 'app_notification_id');
    }

    /**
     * Get the user who read the notification
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the vendor who read the notification
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the admin who read the notification
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
