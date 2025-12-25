<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FiscalYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'start_date',
        'end_date',
        'is_current',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    public function taxRates()
    {
        return $this->hasMany(TaxRate::class);
    }

    public function insuranceRates()
    {
        return $this->hasMany(InsuranceRate::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}

