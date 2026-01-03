<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\TaxRate;
use App\Models\InsuranceRate;
use App\Models\PenaltyConfig;
use App\Models\FiscalYear;
use App\Services\NepaliDate;
use Carbon\Carbon;

class TaxCalculationService
{
    const GRACE_PERIOD_DAYS = 90;
    const MAX_YEARS_TO_CALCULATE = 4; // 4-year rule for amnesty

    /**
     * Calculate tax and insurance for a vehicle
     * 
     * @param Vehicle $vehicle
     * @param int|null $fiscalYearId
     * @param bool $includeInsurance Whether to include insurance in calculation (true = yes, false = no/has valid insurance)
     */
    public function calculate(Vehicle $vehicle, $fiscalYearId = null, $includeInsurance = true)
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
        
        // Use stored expiry_date if available (stored in AD format), otherwise calculate it
        $expiryDate = null;
        if ($vehicle->expiry_date) {
            // Use stored expiry_date (already in AD format: YYYY-MM-DD)
            try {
                $expiryDate = Carbon::createFromFormat('Y-m-d', $vehicle->expiry_date);
            } catch (\Exception $e) {
                \Log::warning('Failed to parse stored expiry_date, will recalculate', [
                    'expiry_date' => $vehicle->expiry_date,
                    'error' => $e->getMessage(),
                ]);
                $expiryDate = null;
            }
        }
        
        // Convert BS dates to AD for calculations (if expiry_date not available)
        $lastRenewedDate = null;
        $registrationDate = null;
        
        if (!$expiryDate && $lastRenewedDateBS) {
            // Convert BS to AD using NepaliDate service
            try {
                $nepaliDate = new NepaliDate();
                $lastRenewedADStr = $nepaliDate->convertBsToAd($lastRenewedDateBS);
                $lastRenewedDate = Carbon::createFromFormat('Y-m-d', $lastRenewedADStr);
                
                // Calculate expiry date (last renewed + 1 year)
                $expiryDate = $lastRenewedDate->copy()->addYear();
            } catch (\InvalidArgumentException $e) {
                // Date validation error (e.g., invalid year range)
                \Log::error('Invalid BS date format or range in tax calculation', [
                    'last_renewed_date_bs' => $lastRenewedDateBS,
                    'error' => $e->getMessage(),
                ]);
                throw new \Exception('Invalid renewal date: ' . $e->getMessage() . '. Please ensure the date is in valid BS format (YYYY-MM-DD) and within range 1975-2095 BS.');
            } catch (\Exception $e) {
                \Log::warning('Failed to convert BS date to AD in tax calculation', [
                    'last_renewed_date_bs' => $lastRenewedDateBS,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        if (!$expiryDate && $registrationDateBS) {
            // Fallback to registration date if last_renewed_date conversion failed
            try {
                $nepaliDate = new NepaliDate();
                $registrationADStr = $nepaliDate->convertBsToAd($registrationDateBS);
                $registrationDate = Carbon::createFromFormat('Y-m-d', $registrationADStr);
                $lastRenewedDate = $registrationDate;
                $expiryDate = $registrationDate->copy()->addYear();
            } catch (\InvalidArgumentException $e) {
                // Date validation error (e.g., invalid year range)
                \Log::error('Invalid registration date format or range in tax calculation', [
                    'registration_date_bs' => $registrationDateBS,
                    'error' => $e->getMessage(),
                ]);
                throw new \Exception('Invalid registration date: ' . $e->getMessage() . '. Please ensure the date is in valid BS format (YYYY-MM-DD) and within range 1975-2095 BS.');
            } catch (\Exception $e) {
                \Log::error('Failed to convert registration date to AD in tax calculation', [
                    'registration_date_bs' => $registrationDateBS,
                    'error' => $e->getMessage(),
                ]);
                throw new \Exception('Unable to calculate expiry date. Please ensure vehicle has valid renewal or registration date.');
            }
        }
        
        if (!$expiryDate) {
            throw new \Exception('Unable to determine vehicle expiry date. Please ensure vehicle has valid renewal or registration date.');
        }
        
        // Get lastRenewedDate for calculations if not already set
        if (!$lastRenewedDate && $expiryDate) {
            $lastRenewedDate = $expiryDate->copy()->subYear();
        }
        
        $gracePeriodEnd = $expiryDate->copy()->addDays(self::GRACE_PERIOD_DAYS);
        $today = Carbon::today();
        
        // Log vehicle expiry status for debugging
        \Log::info('Tax calculation started', [
            'vehicle_id' => $vehicle->id,
            'expiry_date_ad' => $expiryDate->format('Y-m-d'),
            'today_ad' => $today->format('Y-m-d'),
            'is_expired' => $expiryDate->lt($today),
            'days_since_expiry' => $expiryDate->lt($today) ? $today->diffInDays($expiryDate) : 0,
            'within_grace_period' => $today->lte($gracePeriodEnd),
        ]);

        // Calculate years to pay based on fiscal years
        $yearsToPay = $this->calculateYearsToPay($lastRenewedDate, $expiryDate, $today, $fiscalYear);

        // Calculate insurance only once for the current fiscal year (not for each overdue year)
        // Insurance is valid for one year from payment date, so it should only be calculated once
        $totalInsurance = 0;
        if ($includeInsurance) {
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

            $totalInsurance = $insuranceRate->annual_premium;
        }

        $calculations = [];
        $totalTax = 0;
        $totalRenewalFee = 0;
        $totalPenalty = 0;
        $totalRenewalFeePenalty = 0; // Track renewal fee penalty separately

        // Calculate tax, renewal fee, and penalties for each overdue year
        // Note: Insurance is NOT included in this loop - it's calculated once above
        foreach ($yearsToPay as $year) {
            $yearCalculation = $this->calculateForYear(
                $vehicle,
                $year['fiscal_year'],
                $year['expiry_date'],
                $year['days_delayed'],
                false // Set to false - insurance is calculated separately above, not per year
            );

            $calculations[] = $yearCalculation;
            $totalTax += $yearCalculation['tax_amount'];
            $totalRenewalFee += $yearCalculation['renewal_fee'];
            $totalPenalty += $yearCalculation['penalty_amount'];
            $totalRenewalFeePenalty += $yearCalculation['renewal_fee_penalty']; // Add renewal fee penalty
        }
        
        // Total penalty includes both tax penalty and renewal fee penalty
        $totalPenaltyAmount = $totalPenalty + $totalRenewalFeePenalty;

        // Service fee = 600 default (not calculated from renewal fee + penalty)
        $serviceFee = 600.0;
        
        // VAT = 13% on service fee only (not on other fees)
        $vatAmount = round($serviceFee * 0.13, 2);
        
        // Total amount = tax + insurance + renewal_fee + penalty (tax penalty + renewal fee penalty) + service_fee + VAT (on service fee only)
        $totalAmount = round($totalTax + $totalInsurance + $totalRenewalFee + $totalPenaltyAmount + $serviceFee + $vatAmount, 2);
        
        return [
            'vehicle_id' => $vehicle->id,
            'fiscal_year_id' => $fiscalYear->id,
            'vehicle_info' => [
                'registration_date_bs' => $vehicle->registration_date, // Already in BS format
                'registration_date_ad' => $registrationDate ? $registrationDate->format('Y-m-d') : null,
                'last_renewed_date_bs' => $vehicle->last_renewed_date ?? null, // Already in BS format
                'last_renewed_date_ad' => $lastRenewedDate ? $lastRenewedDate->format('Y-m-d') : null,
                'expiry_date_ad' => $expiryDate->format('Y-m-d'),
                'expiry_date_bs' => $this->convertADToBS($expiryDate),
                'today_ad' => $today->format('Y-m-d'),
                'today_bs' => $this->convertADToBS($today),
            ],
            'calculations' => $calculations,
            'summary' => [
                'total_tax' => (float)$totalTax,
                'total_renewal_fee' => (float)$totalRenewalFee,
                'total_penalty' => (float)$totalPenalty, // Tax penalty only
                'total_renewal_fee_penalty' => (float)$totalRenewalFeePenalty, // Renewal fee penalty
                'total_penalty_amount' => (float)$totalPenaltyAmount, // Total penalty (tax penalty + renewal fee penalty)
                'total_insurance' => (float)$totalInsurance,
                'renewal_fee' => (float)$totalRenewalFee, // Individual renewal fee
                'penalty_amount' => (float)$totalPenaltyAmount, // Total penalty amount (for backward compatibility)
                'service_fee' => (float)$serviceFee,
                'vat_amount' => (float)$vatAmount, // VAT on service fee only (13%)
                'total_amount' => (float)$totalAmount, // Total payable (tax + insurance + renewal_fee + penalty + service_fee + VAT on service fee)
                'years_count' => count($yearsToPay),
                // Debug info for penalty calculation
                'penalty_calculation_debug' => [
                    'total_tax_for_penalty' => (float)$totalTax,
                    'total_penalty_percentage_applied' => $totalTax > 0 ? round(($totalPenalty / $totalTax) * 100, 2) : 0,
                    'total_renewal_fee_for_penalty' => (float)$totalRenewalFee,
                    'total_renewal_fee_penalty_percentage_applied' => $totalRenewalFee > 0 ? round(($totalRenewalFeePenalty / $totalRenewalFee) * 100, 2) : 0,
                ],
            ],
        ];
    }

    /**
     * Calculate for a specific fiscal year
     * 
     * @param Vehicle $vehicle
     * @param FiscalYear $fiscalYear
     * @param Carbon $expiryDate
     * @param int $daysDelayed
     * @param bool $includeInsurance Whether to include insurance (true = calculate insurance, false = set to 0)
     */
    private function calculateForYear(Vehicle $vehicle, FiscalYear $fiscalYear, Carbon $expiryDate, $daysDelayed, $includeInsurance = true)
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

        $taxAmount = $taxRate->annual_tax_amount;
        $renewalFee = $taxRate->renewal_fee;
        $insuranceAmount = 0; // Default to 0

        // Only calculate insurance if user wants to include it
        // includeInsurance = true: User needs insurance (calculate insurance fee)
        // includeInsurance = false: User has valid insurance (set insurance to 0)
        if ($includeInsurance) {
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

            $insuranceAmount = $insuranceRate->annual_premium;
        }

        // Calculate penalty based on Nepal vehicle tax rules
        // Logic: Calculate days from expiry date, subtract 90 (grace period), then apply progressive penalty tiers
        $today = Carbon::today();
        $daysDiff = $expiryDate->diffInDays($today, false); // Days from expiry to today (negative if not expired)
        $overdueDays = 0;
        $penaltyPercentage = 0;
        $renewalFeePenaltyPercentage = 0; // Default 0% - no penalty if not delayed
        
        // Pre-calculate fiscal year info for penalty determination (used in both calculation and logging)
        $expiryFiscalYear = $this->findFiscalYearForDate($expiryDate);
        $todayFiscalYear = $this->findFiscalYearForDate($today);
        $gracePeriodEnd = $expiryDate->copy()->addDays(self::GRACE_PERIOD_DAYS);
        $gracePeriodFiscalYear = $this->findFiscalYearForDate($gracePeriodEnd);

        // Penalties only apply if more than 90 days have passed since expiry (grace period)
        if ($daysDiff > self::GRACE_PERIOD_DAYS) {
            $overdueDays = $daysDiff - self::GRACE_PERIOD_DAYS; // Days overdue after grace period
            
            // Progressive penalty tiers based on overdue days
            if ($overdueDays <= 30) {
                // First 30 days after grace period: 5%
                $penaltyPercentage = 5.00;
            } elseif ($overdueDays <= 45) {
                // 31-45 days after grace period: 10%
                $penaltyPercentage = 10.00;
            } else {
                // Check if payment is within the same fiscal year as expiry date
                // For 20%: payment must be within same fiscal year as expiry (after grace period)
                // For 32%: payment is after the fiscal year of expiry
                if ($expiryFiscalYear && $gracePeriodFiscalYear && 
                    $expiryFiscalYear->id === $gracePeriodFiscalYear->id &&
                    $today->lte($gracePeriodFiscalYear->end_date)) {
                    // Within the same fiscal year as expiry: 20%
                    $penaltyPercentage = 20.00;
                } else {
                    // After the fiscal year of expiry: 32%
                    $penaltyPercentage = 32.00;
                }
            }
            
            // Renewal fee penalty: 100% if overdue (daysDiff > 90)
            $renewalFeePenaltyPercentage = 100.00;
        }

        $penaltyAmount = ($taxAmount * $penaltyPercentage) / 100;
        $renewalFeePenalty = ($renewalFee * $renewalFeePenaltyPercentage) / 100;

        // Log penalty calculation for debugging
        if ($overdueDays > 0) {
            
            \Log::info('Penalty calculated (Nepal tax rules)', [
                'fiscal_year_id' => $fiscalYear->id,
                'expiry_date' => $expiryDate->format('Y-m-d'),
                'today' => $today->format('Y-m-d'),
                'days_diff_from_expiry' => $daysDiff,
                'overdue_days_after_grace' => $overdueDays,
                'tax_amount' => $taxAmount,
                'renewal_fee' => $renewalFee,
                'penalty_percentage' => $penaltyPercentage,
                'penalty_amount' => $penaltyAmount,
                'renewal_fee_penalty_percentage' => $renewalFeePenaltyPercentage,
                'renewal_fee_penalty' => $renewalFeePenalty,
                'total_penalty_for_year' => $penaltyAmount + $renewalFeePenalty,
                'expiry_fiscal_year_id' => $expiryFiscalYear ? $expiryFiscalYear->id : null,
                'today_fiscal_year_id' => $todayFiscalYear ? $todayFiscalYear->id : null,
                'grace_period_fiscal_year_id' => $gracePeriodFiscalYear ? $gracePeriodFiscalYear->id : null,
                'is_same_fiscal_year' => $expiryFiscalYear && $gracePeriodFiscalYear && $expiryFiscalYear->id === $gracePeriodFiscalYear->id,
            ]);
        } else {
            \Log::info('No penalty - vehicle not delayed (within grace period)', [
                'fiscal_year_id' => $fiscalYear->id,
                'expiry_date' => $expiryDate->format('Y-m-d'),
                'today' => $today->format('Y-m-d'),
                'days_diff_from_expiry' => $daysDiff,
                'overdue_days_after_grace' => $overdueDays,
                'penalty_amount' => $penaltyAmount,
                'renewal_fee_penalty' => $renewalFeePenalty,
            ]);
        }

        $expiryBS = $this->convertADToBS($expiryDate);

        return [
            'fiscal_year' => $fiscalYear->year,
            'fiscal_year_id' => $fiscalYear->id,
            'fiscal_year_period' => [
                'start_date_ad' => $fiscalYear->start_date->format('Y-m-d'),
                'start_date_bs' => $this->convertADToBS($fiscalYear->start_date),
                'end_date_ad' => $fiscalYear->end_date->format('Y-m-d'),
                'end_date_bs' => $this->convertADToBS($fiscalYear->end_date),
            ],
            'expiry_date_ad' => $expiryDate->format('Y-m-d'),
            'expiry_date_bs' => $expiryBS,
            'days_delayed' => (int)$overdueDays, // Days overdue after 90-day grace period (updated to use recalculated value)
            'days_diff_from_expiry' => (int)$daysDiff, // Days from expiry date to today
            'overdue_days_after_grace' => (int)$overdueDays, // Days overdue after 90-day grace period
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
            
            $expiryBS = $this->convertADToBS($expiryDate);
            
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
            // For year 0: use original expiry date
            // For year 1+: add years to original expiry (each year's renewal expiry)
            $yearExpiry = $expiryDate->copy()->addYears($count);
            $yearGraceEnd = $yearExpiry->copy()->addDays(self::GRACE_PERIOD_DAYS);
            
            // Calculate days delayed using Nepal tax calculation logic
            // Calculate days from expiry date, then subtract grace period (90 days)
            $daysDiff = $yearExpiry->diffInDays($today, false); // Days from expiry to today
            $daysDelayed = 0;
            
            // Penalties only apply if more than 90 days have passed since expiry
            if ($daysDiff > self::GRACE_PERIOD_DAYS) {
                $daysDelayed = $daysDiff - self::GRACE_PERIOD_DAYS; // Days overdue after grace period
                
                // Ensure it's positive (should be, but double-check)
                if ($daysDelayed < 0) {
                    \Log::warning('Negative days_delayed calculated, setting to 0', [
                        'year_expiry' => $yearExpiry->format('Y-m-d'),
                        'year_grace_end' => $yearGraceEnd->format('Y-m-d'),
                        'today' => $today->format('Y-m-d'),
                        'days_diff' => $daysDiff,
                        'calculated_days' => $daysDelayed,
                    ]);
                    $daysDelayed = 0;
                }
            } else {
                // Within grace period, no penalty
                $daysDelayed = 0;
            }

            // Only add if this year's expiry has passed or is current (count === 0 means first year)
            // For expired vehicles, we need to calculate for all overdue years
            if ($yearExpiry->lte($today) || $count === 0) {
                $yearExpiryBS = $this->convertADToBS($yearExpiry);
                
                \Log::info('Adding fiscal year for tax calculation', [
                    'fiscal_year_id' => $fiscalYear->id,
                    'fiscal_year' => $fiscalYear->year,
                    'year_expiry_ad' => $yearExpiry->format('Y-m-d'),
                    'year_grace_end_ad' => $yearGraceEnd->format('Y-m-d'),
                    'today_ad' => $today->format('Y-m-d'),
                    'days_delayed' => $daysDelayed,
                    'is_expired' => $yearExpiry->lt($today),
                    'is_past_grace_period' => $today->gt($yearGraceEnd),
                    'year_count' => $count,
                ]);
                
                $years[] = [
                    'fiscal_year' => $fiscalYear,
                    'expiry_date' => $yearExpiry,
                    'expiry_date_bs' => $yearExpiryBS,
                    'days_delayed' => $daysDelayed,
                ];
                
                $count++;
            } else {
                // Future year, stop here
                break;
            }
        }

        // If no years found, return at least current fiscal year
        // This handles edge cases where fiscal year matching fails
        if (empty($years)) {
            $fiscalYear = FiscalYear::where('is_current', true)->first() ?? $currentFiscalYear;
            
            // Calculate days delayed from grace period end
            // If expired beyond grace period, days_delayed will be positive; otherwise 0
            $daysDelayed = $today->gt($gracePeriodEnd) ? $today->diffInDays($gracePeriodEnd) : 0;
            $expiryBS = $this->convertADToBS($expiryDate);
            
            \Log::warning('No fiscal years matched in calculateYearsToPay, using fallback', [
                'expiry_date' => $expiryDate->format('Y-m-d'),
                'today' => $today->format('Y-m-d'),
                'fiscal_year_id' => $fiscalYear->id,
                'days_delayed' => $daysDelayed,
            ]);
            
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

    /**
     * Convert AD date to BS date string
     * Helper method to convert Carbon date to BS format
     */
    /**
     * Convert AD date to BS date string
     * Helper method to convert Carbon date to BS format
     * Note: AD year must be between 1918-2038 for conversion to work
     */
    private function convertADToBS(Carbon $adDate): string
    {
        try {
            $nepaliDate = new NepaliDate();
            $year = (int)$adDate->format('Y');
            $month = (int)$adDate->format('m');
            $day = (int)$adDate->format('d');
            
            // Check if year is within valid range before attempting conversion
            if ($year < 1918 || $year > 2038) {
                \Log::warning('AD date outside conversion range, returning AD format', [
                    'ad_date' => $adDate->format('Y-m-d'),
                    'year' => $year,
                    'valid_range' => '1918-2038',
                ]);
                return $adDate->format('Y-m-d'); // Fallback to AD format
            }
            
            $bsDate = $nepaliDate->get_nepali_date($year, $month, $day);
            return sprintf('%04d-%02d-%02d', $bsDate['y'], $bsDate['m'], $bsDate['d']);
        } catch (\InvalidArgumentException $e) {
            // Date validation error - log and return AD format as fallback
            \Log::warning('Invalid AD date for BS conversion', [
                'ad_date' => $adDate->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);
            return $adDate->format('Y-m-d'); // Fallback to AD format
        } catch (\Exception $e) {
            \Log::warning('Failed to convert AD to BS date', [
                'ad_date' => $adDate->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);
            return $adDate->format('Y-m-d'); // Fallback to AD format
        }
    }
}

