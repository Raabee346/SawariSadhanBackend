<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'province_id',
        'owner_name',
        'registration_number',
        'chassis_number',
        'vehicle_type',
        'fuel_type',
        'brand',
        'model',
        'engine_capacity',
        'manufacturing_year',
        'registration_date',
        'last_renewed_date',
        'verification_status',
        'rejection_reason',
        'verified_by',
        'verified_at',
        'is_commercial',
        'documents',
        'rc_firstpage',
        'rc_ownerdetails',
        'rc_vehicledetails',
        'lastrenewdate',
        'insurance',
        'owner_ctznship_front',
        'owner_ctznship_back',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'is_commercial' => 'boolean',
        'documents' => 'array',
        // registration_date and last_renewed_date are stored as BS date strings, no casting
    ];

    protected $appends = [
        'rc_firstpage_url',
        'rc_ownerdetails_url',
        'rc_vehicledetails_url',
        'lastrenewdate_url',
        'insurance_url',
        'owner_ctznship_front_url',
        'owner_ctznship_back_url',
    ];

    /**
     * Get the full URL for a document path
     */
    private function getDocumentUrl($path)
    {
        if (!$path) {
            return null;
        }
        return Storage::disk('public')->url($path);
    }

    // Document URL accessors
    public function getRcFirstpageUrlAttribute()
    {
        return $this->getDocumentUrl($this->rc_firstpage);
    }

    public function getRcOwnerdetailsUrlAttribute()
    {
        return $this->getDocumentUrl($this->rc_ownerdetails);
    }

    public function getRcVehicledetailsUrlAttribute()
    {
        return $this->getDocumentUrl($this->rc_vehicledetails);
    }

    public function getLastrenewdateUrlAttribute()
    {
        return $this->getDocumentUrl($this->lastrenewdate);
    }

    public function getInsuranceUrlAttribute()
    {
        return $this->getDocumentUrl($this->insurance);
    }

    public function getOwnerCtznshipFrontUrlAttribute()
    {
        return $this->getDocumentUrl($this->owner_ctznship_front);
    }

    public function getOwnerCtznshipBackUrlAttribute()
    {
        return $this->getDocumentUrl($this->owner_ctznship_back);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(Admin::class, 'verified_by');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }
}

