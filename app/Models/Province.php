<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasBSTimestamps;

class Province extends Model
{
    use HasFactory, HasBSTimestamps;

    protected $fillable = [
        'name',
        'code',
        'number',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function taxRates()
    {
        return $this->hasMany(TaxRate::class);
    }

}

