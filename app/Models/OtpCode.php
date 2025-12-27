<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Traits\HasBSTimestamps;

class OtpCode extends Model
{
    use HasBSTimestamps;
    
    protected $table = 'otp_codes';
    
    protected $fillable = [
        'email',
        'code',
        'type',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

}

