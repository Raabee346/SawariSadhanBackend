<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

