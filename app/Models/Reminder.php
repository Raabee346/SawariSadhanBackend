<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasBSTimestamps;

class Reminder extends Model
{
    use HasFactory, HasBSTimestamps;

    protected $fillable = [
        'user_id',
        'vehicle_id',
        'title',
        'message',
        'reminder_date',
        'is_notified',
        'notified_at',
    ];

    protected $casts = [
        'reminder_date' => 'datetime',
        'notified_at' => 'datetime',
        'is_notified' => 'boolean',
    ];

    /**
     * Get the user that owns the reminder.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the vehicle associated with the reminder.
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Check if reminder is upcoming (not yet notified and date is in future)
     */
    public function isUpcoming(): bool
    {
        return !$this->is_notified && $this->reminder_date > now();
    }

    /**
     * Check if reminder is past (already notified or date has passed)
     */
    public function isPast(): bool
    {
        return $this->is_notified || $this->reminder_date <= now();
    }
}

