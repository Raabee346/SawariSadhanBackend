<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\TaxRate;
use App\Models\InsuranceRate;
use App\Models\PenaltyConfig;
use App\Models\FiscalYear;
use App\Services\NepalDateService;
use Carbon\Carbon;

class TaxCalculationService
{
    const GRACE_PERIOD_DAYS = 90;
    const MAX_YEARS_TO_CALCULATE = 4; // 4-year rule for amnesty

    /**
     * Calculate tax and insurance for a vehicle
     */
    public function calculate(Vehicle $vehicle, $fiscalYearId = null)
    {
        if (!$vehicle->isVerified()) {
            throw new \Exception('Vehicle must be verified before calculation');
        }

        $fiscalYear = $fiscalYearId 
            ? FiscalYear::findOrFail($fiscalYearId)
            : FiscalYear::where('is_current', true)->first();
        
        // If no current fiscal year, get the latest one
        if (!$fiscalYear) {
            $fiscalYear = FiscalYear::orderBy('start_date', 'desc')->first();
        }
        
        if (!$fiscalYear) {
            throw new \Exception('No fiscal year found. Please create a fiscal year first.');
        }

        // Get vehicle dates (stored as BS date strings in DB)
        $lastRenewedDateBS = $vehicle->last_renewed_date ?? $vehicle->registration_date;
        $registrationDateBS = $vehicle->registration_date;
        
        // Convert BS dates to AD for calculations
        $lastRenewedDate = $lastRenewedDateBS ? NepalDateService::toAD($lastRenewedDateBS) : null;
        $registrationDate = $registrationDateBS ? NepalDateService::toAD($registrationDateBS) : null;
        
        if (!$lastRenewedDate) {
            $lastRenewedDate = $registrationDate;
        }
        
        // Vehicle expires after 1 year from registration/renewal
        $expiryDate = $lastRenewedDate->copy()->addYear();
        $gracePeriodEnd = $expiryDate->copy()->addDays(self::GRACE_PERIOD_DAYS);
        $today = Carbon::today();

        // Calculate years to pay based on fiscal years
        $yearsToPay = $this->calculateYearsToPay($lastRenewedDate, $expiryDate, $today, $fiscalYear);

        $calculations = [];
        $totalTax = 0;
        $totalRenewalFee = 0;
        $totalPenalty = 0;
        $totalInsurance = 0;

        foreach ($yearsToPay as $year) {
            $yearCalculation = $this->calculateForYear(
                $vehicle,
                $year['fiscal_year'],
                $year['expiry_date'],
                $year['days_delayed']
            );

            $calculations[] = $yearCalculation;
            $totalTax += $yearCalculation['tax_amount'];
            $totalRenewalFee += $yearCalculation['renewal_fee'];
            $totalPenalty += $yearCalculation['penalty_amount'];
            $totalInsurance += $yearCalculation['insurance_amount'];
        }

        return [
            'vehicle_id' => $vehicle->id,
            'fiscal_year_id' => $fiscalYear->id,
            'vehicle_info' => [
                'registration_date_bs' => $vehicle->registration_date, // Already in BS format
                'registration_date_ad' => $registrationDate ? $registrationDate->format('Y-m-d') : null,
                'last_renewed_date_bs' => $vehicle->last_renewed_date ?? null, // Already in BS format
                'last_renewed_date_ad' => $lastRenewedDate ? $lastRenewedDate->format('Y-m-d') : null,
                'expiry_date_ad' => $expiryDate->format('Y-m-d'),
                'expiry_date_bs' => NepalDateService::toBS($expiryDate),
                'today_ad' => $today->format('Y-m-d'),
                'today_bs' => NepalDateService::toBS($today),
            ],
            'calculations' => $calculations,
            'summary' => [
                'total_tax' => (float)$totalTax,
                'total_renewal_fee' => (float)$totalRenewalFee,
                'total_penalty' => (float)$totalPenalty,
                'total_insurance' => (float)$totalInsurance,
                'grand_total' => (float)($totalTax + $totalRenewalFee + $totalPenalty + $totalInsurance),
                'years_count' => count($yearsToPay),
            ],
        ];
    }

    /**
     * Calculate for a specific fiscal year
     */
    private function calculateForYear(Vehicle $vehicle, FiscalYear $fiscalYear, Carbon $expiryDate, $daysDelayed)
    {
        // Get tax rate
        $taxRate = TaxRate::findRate(
            $vehicle->province_id,
            $fiscalYear->id,
            $vehicle->vehicle_type,
            $vehicle->fuel_type,
            $vehicle->engine_capacity
        );

        if (!$taxRate) {
            // Debug: Check what rates exist
            $availableRates = TaxRate::where('vehicle_type', $vehicle->vehicle_type)
                ->where('fuel_type', $vehicle->fuel_type)
                ->where('province_id', $vehicle->province_id)
                ->pluck('capacity_value')
                ->toArray();
            
            $availableFiscalYears = TaxRate::where('vehicle_type', $vehicle->vehicle_type)
                ->where('fuel_type', $vehicle->fuel_type)
                ->where('province_id', $vehicle->province_id)
                ->distinct()
                ->pluck('fiscal_year_id')
                ->toArray();
            
            $message = "Tax rate not found for vehicle type: {$vehicle->vehicle_type}, fuel: {$vehicle->fuel_type}, capacity: {$vehicle->engine_capacity}, province_id: {$vehicle->province_id}, fiscal_year_id: {$fiscalYear->id}. ";
            $message .= "Available capacities: " . implode(', ', $availableRates) . ". ";
            $message .= "Available fiscal years: " . implode(', ', $availableFiscalYears) . ". ";
            $message .= "Please add tax rates for this vehicle configuration in the admin panel.";
            
            throw new \Exception($message);
        }

        // Get insurance rate
        $insuranceRate = InsuranceRate::findRate(
            $fiscalYear->id,
            $vehicle->vehicle_type,
            $vehicle->fuel_type,
            $vehicle->engine_capacity
        );

        if (!$insuranceRate) {
            // Debug: Check what rates exist
            $availableRates = InsuranceRate::where('vehicle_type', $vehicle->vehicle_type)
                ->where('fuel_type', $vehicle->fuel_type)
                ->where('fiscal_year_id', $fiscalYear->id)
                ->pluck('capacity_value')
                ->toArray();
            
            $availableFiscalYears = InsuranceRate::where('vehicle_type', $vehicle->vehicle_type)
                ->where('fuel_type', $vehicle->fuel_type)
                ->distinct()
                ->pluck('fiscal_year_id')
                ->toArray();
            
            $message = "Insurance rate not found for vehicle type: {$vehicle->vehicle_type}, fuel: {$vehicle->fuel_type}, capacity: {$vehicle->engine_capacity}, fiscal_year_id: {$fiscalYear->id}. ";
            $message .= "Available capacities: " . implode(', ', $availableRates) . ". ";
            $message .= "Available fiscal years: " . implode(', ', $availableFiscalYears) . ". ";
            $message .= "Please add insurance rates for this vehicle configuration in the admin panel.";
            
            throw new \Exception($message);
        }

        $taxAmount = $taxRate->annual_tax_amount;
        $renewalFee = $taxRate->renewal_fee;
        $insuranceAmount = $insuranceRate->annual_premium;

        // Calculate penalty
        $penaltyPercentage = 0;
        $renewalFeePenaltyPercentage = 100; // Default 100% on renewal fee

        if ($daysDelayed > 0) {
            $penaltyPercentage = PenaltyConfig::getPenaltyPercentage($daysDelayed);
            $penaltyConfig = PenaltyConfig::where('is_active', true)
                ->where('days_from_expiry', '<=', $daysDelayed)
                ->where(function ($query) use ($daysDelayed) {
                    $query->whereNull('days_to')
                          ->orWhere('days_to', '>=', $daysDelayed);
                })
                ->orderBy('days_from_expiry', 'desc')
                ->first();

            if ($penaltyConfig) {
                $renewalFeePenaltyPercentage = $penaltyConfig->renewal_fee_penalty_percentage;
            }
        }

        $penaltyAmount = ($taxAmount * $penaltyPercentage) / 100;
        $renewalFeePenalty = ($renewalFee * $renewalFeePenaltyPercentage) / 100;

        $expiryBS = NepalDateService::toBS($expiryDate);

        return [
            'fiscal_year' => $fiscalYear->year,
            'fiscal_year_id' => $fiscalYear->id,
            'fiscal_year_period' => [
                'start_date_ad' => $fiscalYear->start_date->format('Y-m-d'),
                'start_date_bs' => NepalDateService::toBS($fiscalYear->start_date),
                'end_date_ad' => $fiscalYear->end_date->format('Y-m-d'),
                'end_date_bs' => NepalDateService::toBS($fiscalYear->end_date),
            ],
            'expiry_date_ad' => $expiryDate->format('Y-m-d'),
            'expiry_date_bs' => $expiryBS,
            'days_delayed' => $daysDelayed,
            'tax_amount' => (float)$taxAmount,
            'renewal_fee' => (float)$renewalFee,
            'penalty_percentage' => (float)$penaltyPercentage,
            'penalty_amount' => (float)$penaltyAmount,
            'renewal_fee_penalty_percentage' => (float)$renewalFeePenaltyPercentage,
            'renewal_fee_penalty' => (float)$renewalFeePenalty,
            'insurance_amount' => (float)$insuranceAmount,
            'subtotal' => (float)($taxAmount + $renewalFee + $penaltyAmount + $renewalFeePenalty + $insuranceAmount),
        ];
    }

    /**
     * Calculate which fiscal years need to be paid
     */
    private function calculateYearsToPay(Carbon $lastRenewedDate, Carbon $expiryDate, Carbon $today, FiscalYear $currentFiscalYear)
    {
        $years = [];
        $gracePeriodEnd = $expiryDate->copy()->addDays(self::GRACE_PERIOD_DAYS);

        // If within grace period, only calculate for current fiscal year
        if ($today->lte($gracePeriodEnd)) {
            $fiscalYear = $this->findFiscalYearForDate($today);
            
            if (!$fiscalYear) {
                $fiscalYear = $currentFiscalYear;
            }
            
            $expiryBS = NepalDateService::toBS($expiryDate);
            
            return [[
                'fiscal_year' => $fiscalYear,
                'expiry_date' => $expiryDate,
                'expiry_date_bs' => $expiryBS,
                'days_delayed' => 0,
            ]];
        }

        // Calculate for overdue years (up to 4 years)
        $allFiscalYears = FiscalYear::orderBy('start_date', 'asc')->get();
        
        // Find the fiscal year that contains the expiry date
        $startFiscalYear = $this->findFiscalYearForDate($expiryDate);
        if (!$startFiscalYear) {
            // Find closest fiscal year
            foreach ($allFiscalYears as $fy) {
                if ($expiryDate->gte($fy->start_date) && $expiryDate->lte($fy->end_date->copy()->addMonths(6))) {
                    $startFiscalYear = $fy;
                    break;
                }
            }
        }

        if (!$startFiscalYear) {
            $startFiscalYear = $currentFiscalYear;
        }

        // Get fiscal years from start to current (up to 4 years)
        $startIndex = $allFiscalYears->search(function ($fy) use ($startFiscalYear) {
            return $fy->id === $startFiscalYear->id;
        });

        if ($startIndex === false) {
            $startIndex = 0;
        }

        $count = 0;
        for ($i = $startIndex; $i < $allFiscalYears->count() && $count < self::MAX_YEARS_TO_CALCULATE; $i++) {
            $fiscalYear = $allFiscalYears[$i];
            
            // Calculate the expiry date for this fiscal year
            $yearExpiry = $expiryDate->copy()->addYears($count);
            $yearGraceEnd = $yearExpiry->copy()->addDays(self::GRACE_PERIOD_DAYS);
            
            // Calculate days delayed
            if ($today->gt($yearGraceEnd)) {
                $daysDelayed = $today->diffInDays($yearGraceEnd);
            } else {
                $daysDelayed = 0;
            }

            // Only add if this year's expiry has passed or is current
            if ($yearExpiry->lte($today) || $count === 0) {
                $yearExpiryBS = NepalDateService::toBS($yearExpiry);
                
                $years[] = [
                    'fiscal_year' => $fiscalYear,
                    'expiry_date' => $yearExpiry,
                    'expiry_date_bs' => $yearExpiryBS,
                    'days_delayed' => $daysDelayed,
                ];
                
                $count++;
            } else {
                break;
            }
        }

        // If no years found, return at least current fiscal year
        if (empty($years)) {
            $fiscalYear = FiscalYear::where('is_current', true)->first() ?? $currentFiscalYear;
            $daysDelayed = max(0, $today->diffInDays($gracePeriodEnd));
            $expiryBS = NepalDateService::toBS($expiryDate);
            
            $years[] = [
                'fiscal_year' => $fiscalYear,
                'expiry_date' => $expiryDate,
                'expiry_date_bs' => $expiryBS,
                'days_delayed' => $daysDelayed,
            ];
        }

        return $years;
    }

    /**
     * Find fiscal year for a given date
     */
    private function findFiscalYearForDate(Carbon $date): ?FiscalYear
    {
        return FiscalYear::where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }
}

