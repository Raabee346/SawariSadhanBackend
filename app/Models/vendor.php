<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasBSTimestamps;

class Vendor extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasBSTimestamps;

    protected $fillable = [
        'unique_id',
        'name',
        'email',
        'password',
        'email_verified_at',
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
     * Get the vendor's profile.
     */
    public function profile()
    {
        return $this->hasOne(VendorProfile::class);
    }

    /**
     * Get the vendor's availability schedules.
     */
    public function availabilities()
    {
        return $this->hasMany(VendorAvailability::class);
    }

    /**
     * Get availability for a specific day.
     */
    public function getAvailabilityForDay($day)
    {
        return $this->availabilities()->where('day_of_week', strtolower($day))->first();
    }

    /**
     * Check if vendor is available today.
     */
    public function isAvailableToday()
    {
        $today = strtolower(now()->format('l'));
        $availability = $this->getAvailabilityForDay($today);

        return $availability && $availability->isAvailableAt();
    }

}