<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
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
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['profile_picture_url'];

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

    /**
     * Get the profile picture URL.
     */
    public function getProfilePictureUrlAttribute()
    {
        try {
            if (!$this->profile_picture) {
                return null;
            }

            // If it's already a full URL, return as is
            if (str_starts_with($this->profile_picture, 'http://') || str_starts_with($this->profile_picture, 'https://')) {
                return $this->profile_picture;
            }

            // Construct full URL from storage path
            return asset('storage/' . $this->profile_picture);
        } catch (\Exception $e) {
            Log::warning('Error generating profile picture URL', [
                'profile_id' => $this->id,
                'profile_picture' => $this->profile_picture,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

}

