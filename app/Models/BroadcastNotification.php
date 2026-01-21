<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BroadcastNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'target_type',
        'type',
        'extra_data',
    ];

    protected $casts = [
        'extra_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get all users who have read this notification
     */
    public function reads()
    {
        return $this->hasMany(NotificationRead::class, 'broadcast_notification_id');
    }

    /**
     * Check if a specific user has read this notification
     */
    public function isReadByUser($userId)
    {
        return $this->reads()
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Check if a specific vendor has read this notification
     */
    public function isReadByVendor($vendorId)
    {
        return $this->reads()
            ->where('vendor_id', $vendorId)
            ->exists();
    }

    /**
     * Check if a specific admin has read this notification
     */
    public function isReadByAdmin($adminId)
    {
        return $this->reads()
            ->where('admin_id', $adminId)
            ->exists();
    }

    /**
     * Mark as read by a user
     */
    public function markAsReadByUser($userId)
    {
        if (!$this->isReadByUser($userId)) {
            $this->reads()->create([
                'user_id' => $userId,
                'read_at' => now(),
            ]);
        }
    }

    /**
     * Mark as read by a vendor
     */
    public function markAsReadByVendor($vendorId)
    {
        if (!$this->isReadByVendor($vendorId)) {
            $this->reads()->create([
                'vendor_id' => $vendorId,
                'read_at' => now(),
            ]);
        }
    }

    /**
     * Mark as read by an admin
     */
    public function markAsReadByAdmin($adminId)
    {
        if (!$this->isReadByAdmin($adminId)) {
            $this->reads()->create([
                'admin_id' => $adminId,
                'read_at' => now(),
            ]);
        }
    }
}
