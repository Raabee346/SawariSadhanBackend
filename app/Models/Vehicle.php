<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\HasBSTimestamps;

class Vehicle extends Model
{
    use HasFactory, HasBSTimestamps;

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
        'expiry_date',
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

    /**
     * Boot the model
     * Auto-calculate expiry_date when last_renewed_date is set/changed
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-calculate expiry_date when saving/updating
        static::saving(function ($vehicle) {
            // Only calculate if last_renewed_date is set and expiry_date is not already set or needs recalculation
            if ($vehicle->last_renewed_date && (!$vehicle->expiry_date || $vehicle->isDirty('last_renewed_date'))) {
                try {
                    $nepaliDate = new \App\Services\NepaliDate();
                    $lastRenewedAD = $nepaliDate->convertBsToAd($vehicle->last_renewed_date);
                    
                    if ($lastRenewedAD) {
                        $expiryDate = \Carbon\Carbon::createFromFormat('Y-m-d', $lastRenewedAD)
                            ->addYear()
                            ->format('Y-m-d');
                        $vehicle->expiry_date = $expiryDate;
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to auto-calculate expiry_date in Vehicle model', [
                        'last_renewed_date' => $vehicle->last_renewed_date,
                        'error' => $e->getMessage(),
                    ]);
                    // Don't throw exception, just log the warning
                }
            }
        });

        // Notify admins when a new vehicle is created (works for both Filament and API)
        static::created(function ($vehicle) {
            \Log::info('=== Vehicle::created event triggered ===', [
                'vehicle_id' => $vehicle->id,
                'registration_number' => $vehicle->registration_number ?? 'N/A',
            ]);

            static::notifyAdminsAboutVehicle($vehicle, 'new');
        });

        // Notify admins when a rejected vehicle is resubmitted
        static::updated(function ($vehicle) {
            // Only notify if verification_status changed from 'rejected' to 'pending'
            if ($vehicle->isDirty('verification_status')) {
                $originalStatus = $vehicle->getOriginal('verification_status');
                $newStatus = $vehicle->verification_status;
                
                \Log::info('=== Vehicle::updated event - status changed ===', [
                    'vehicle_id' => $vehicle->id,
                    'original_status' => $originalStatus,
                    'new_status' => $newStatus,
                ]);
                
                if ($originalStatus === 'rejected' && $newStatus === 'pending') {
                    \Log::info('Rejected vehicle resubmitted, notifying admins', [
                        'vehicle_id' => $vehicle->id,
                        'registration_number' => $vehicle->registration_number,
                    ]);
                    
                    static::notifyAdminsAboutVehicle($vehicle, 'resubmitted');
                }
            }
        });
    }

    /**
     * Notify admins about vehicle (new or resubmitted)
     */
    private static function notifyAdminsAboutVehicle($vehicle, $type = 'new')
    {
        try {
            $admins = \App\Models\Admin::all();
            
            $isResubmitted = $type === 'resubmitted';
            $notificationTitle = $isResubmitted 
                ? 'Vehicle Resubmitted for Verification' 
                : 'New Vehicle Verification Request';
            
            \Log::info('Notifying admins about vehicle', [
                'vehicle_id' => $vehicle->id,
                'type' => $type,
                'admin_count' => $admins->count(),
            ]);

            foreach ($admins as $admin) {
                    try {
                        \Log::info('Sending notification to admin', [
                            'admin_id' => $admin->id,
                            'admin_email' => $admin->email,
                            'has_fcm_token' => !empty($admin->fcm_token),
                            'vehicle_id' => $vehicle->id,
                            'type' => $type,
                        ]);

                        // Send Filament notification - must use sendToDatabase() + keepAfterClosed() + send()
                        try {
                            $userName = $vehicle->user?->name ?? $vehicle->owner_name ?? 'Unknown User';
                            $bodyMessage = $isResubmitted
                                ? "{$userName} has resubmitted a previously rejected vehicle for verification."
                                : "{$userName} has submitted a new vehicle for verification.";
                            
                            \Filament\Notifications\Notification::make()
                                ->title($notificationTitle)
                                ->body($bodyMessage)
                                ->success()
                                ->icon('heroicon-o-truck')
                                // 1. Send to the database record (CRITICAL for CRUD)
                                ->sendToDatabase($admin)
                                // 2. IMPORTANT: This prevents the auto-delete query
                                ->keepAfterClosed()
                                ->actions([
                                    \Filament\Notifications\Actions\Action::make('view')
                                        ->label('View Vehicle')
                                        ->url('/admin/vehicles/' . $vehicle->id)
                                        ->button()
                                        ->color('primary'),
                                    
                                    \Filament\Notifications\Actions\Action::make('markAsRead')
                                        ->label('Mark as Read')
                                        ->markAsRead()
                                        ->color('gray'),
                                ])
                                // 3. This shows the popup on the screen
                                ->send();
                            
                            \Log::info('Filament notification sent to admin', [
                                'admin_id' => $admin->id,
                                'vehicle_id' => $vehicle->id,
                            ]);
                        } catch (\Exception $e) {
                            \Log::error('Exception while sending Filament notification', [
                                'admin_id' => $admin->id,
                                'vehicle_id' => $vehicle->id,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        // Send FCM notification (simple approach)
                        if ($admin->fcm_token) {
                            try {
                                // Get user name - prefer user relationship, fallback to owner_name
                                $userName = $vehicle->user?->name ?? $vehicle->owner_name ?? 'Unknown User';
                                $fcmBody = $isResubmitted
                                    ? "{$userName} has resubmitted a previously rejected vehicle for verification."
                                    : "{$userName} has submitted a new vehicle for verification.";
                                
                                $fcmService = app(\App\Services\FCMNotificationService::class);
                                $fcmService->sendToAdmin(
                                    $admin,
                                    $notificationTitle,
                                    $fcmBody,
                                    [
                                        'type' => $isResubmitted ? 'vehicle_resubmitted' : 'vehicle_verification_request',
                                        'vehicle_id' => (string) $vehicle->id,
                                        'registration_number' => $vehicle->registration_number,
                                        'user_name' => $userName,
                                        'url' => '/admin/vehicles/' . $vehicle->id,
                                    ]
                                );
                            } catch (\Exception $e) {
                                \Log::error('Failed to send FCM notification to admin', [
                                    'admin_id' => $admin->id,
                                    'vehicle_id' => $vehicle->id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::error('Failed to send notification to admin', [
                            'admin_id' => $admin->id,
                            'vehicle_id' => $vehicle->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Failed to notify admins about vehicle', [
                    'vehicle_id' => $vehicle->id,
                    'type' => $type,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

}

