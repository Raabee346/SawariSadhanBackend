<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasBSTimestamps;

class UserProfile extends Model
{
    use HasFactory, HasBSTimestamps;

    protected $fillable = [
        'user_id',
        'phone_number',
        'profile_picture',
        'date_of_birth',
        'gender',
        'address',
        'city',
        'state',
        'pincode',
        'country',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    /**
     * Get the user that owns the profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the full address.
     */
    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->pincode,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

}

