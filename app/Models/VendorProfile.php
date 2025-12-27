<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasBSTimestamps;

class VendorProfile extends Model
{
    use HasFactory, HasBSTimestamps;

    protected $fillable = [
        'vendor_id',
        'phone_number',
        'profile_picture',
        'date_of_birth',
        'gender',
        'address',
        'city',
        'state',
        'pincode',
        'vehicle_type',
        'vehicle_number',
        'vehicle_model',
        'vehicle_color',
        'vehicle_year',
        'license_number',
        'license_expiry',
        'license_document',
        'vehicle_rc_document',
        'insurance_document',
        'citizenship_document',
        'pan_document',
        'service_latitude',
        'service_longitude',
        'service_radius',
        'service_address',
        'is_verified',
        'is_online',
        'is_available',
        'verification_status',
        'rejection_reason',
        'rating',
        'total_rides',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'license_expiry' => 'date',
        'service_latitude' => 'decimal:8',
        'service_longitude' => 'decimal:8',
        'is_verified' => 'boolean',
        'is_online' => 'boolean',
        'is_available' => 'boolean',
        'rating' => 'decimal:2',
        'total_rides' => 'integer',
        'service_radius' => 'integer',
        'vehicle_year' => 'integer',
    ];

    /**
     * Get the vendor that owns the profile.
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
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
        ]);

        return implode(', ', $parts);
    }

    /**
     * Check if vendor is currently available for rides.
     */
    public function isCurrentlyAvailable()
    {
        return $this->is_verified && $this->is_online && $this->is_available;
    }

    /**
     * Check if license is expired.
     */
    public function isLicenseExpired()
    {
        if (!$this->license_expiry) {
            return false;
        }

        return $this->license_expiry->isPast();
    }


}

