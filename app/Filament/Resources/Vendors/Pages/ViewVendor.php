<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\Vendors\VendorResource;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Carbon\Carbon;

class ViewVendor extends ViewRecord
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->schema([
                Placeholder::make('heading_vendor')
                    ->label('VENDOR INFORMATION')
                    ->content('')
                    ->columnSpanFull(),
                
                Placeholder::make('unique_id')
                    ->label('Unique ID')
                    ->content(fn ($record) => $record->unique_id),
                
                Placeholder::make('name')
                    ->label('Full Name')
                    ->content(fn ($record) => $record->name),
                
                Placeholder::make('email')
                    ->label('Email')
                    ->content(fn ($record) => $record->email),
                
                Placeholder::make('email_verified_at')
                    ->label('Email Verified')
                    ->content(fn ($record) => $record->email_verified_at ? '✅ Verified on ' . $record->email_verified_at->format('Y-m-d') : '❌ Not Verified'),
                
                Placeholder::make('created_at')
                    ->label('Registered On')
                    ->content(function ($record) {
                        // Get raw value from database
                        $rawValue = $record->getRawOriginal('created_at');
                        if (!$rawValue) {
                            return 'N/A';
                        }
                        try {
                            $carbon = Carbon::parse($rawValue);
                            // Display in AD format only (date without time)
                            return $carbon->format('Y-m-d');
                        } catch (\Exception $e) {
                            return $rawValue;
                        }
                    }),
                
                Placeholder::make('updated_at')
                    ->label('Last Updated')
                    ->content(function ($record) {
                        // Get raw value from database
                        $rawValue = $record->getRawOriginal('updated_at');
                        if (!$rawValue) {
                            return 'N/A';
                        }
                        try {
                            $carbon = Carbon::parse($rawValue);
                            // Display in AD format only (date without time)
                            return $carbon->format('Y-m-d');
                        } catch (\Exception $e) {
                            return $rawValue;
                        }
                    }),
                
                Placeholder::make('heading_personal')
                    ->label('PERSONAL DETAILS')
                    ->content('')
                    ->visible(fn ($record) => $record->profile !== null)
                    ->columnSpanFull(),
                
                Placeholder::make('profile.profile_picture')
                    ->label('📷 Profile Picture')
                    ->content(function ($record) {
                        if (!$record->profile || !$record->profile->profile_picture) {
                            return new HtmlString('<div style="text-align: center; padding: 10px; background: #f3f4f6; border-radius: 8px; display: inline-block; max-width: 200px;">
                                <svg style="width: 50px; height: 50px; margin: 0 auto; color: #9ca3af;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                </svg>
                                <p style="color: #6b7280; margin-top: 5px; font-size: 12px;">No profile picture uploaded</p>
                            </div>');
                        }
                        
                        // Check if file exists
                        if (!Storage::disk('public')->exists($record->profile->profile_picture)) {
                            return new HtmlString('<div style="text-align: center; padding: 10px; background: #f3f4f6; border-radius: 8px; display: inline-block; max-width: 200px;">
                                <svg style="width: 50px; height: 50px; margin: 0 auto; color: #9ca3af;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                </svg>
                                <p style="color: #6b7280; margin-top: 5px; font-size: 12px;">Profile picture file not found</p>
                            </div>');
                        }
                        
                        $imageUrl = asset('storage/' . $record->profile->profile_picture);
                        $uploadedDate = $record->profile->updated_at;
                        $uploadedDateFormatted = is_string($uploadedDate) ? $uploadedDate : ($uploadedDate ? $uploadedDate->format('Y-m-d H:i') : 'N/A');
                        return new HtmlString('<div style="text-align: center;">
                            <img src="' . htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') . '" alt="Profile Picture" style="max-width: 300px; max-height: 300px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); object-fit: cover;" onerror="this.onerror=null; this.src=\'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIiB2aWV3Qm94PSIwIDAgMTAwIDEwMCI+PHJlY3Qgd2lkdGg9IjEwMCIgaGVpZ2h0PSIxMDAiIGZpbGw9IiNlNWU3ZWIiLz48cGF0aCBmaWxsPSIjOWNhM2FmIiBkPSJNNTAgNDVhMTIgMTIgMCAxIDAgMC0yNCAxMiAxMiAwIDAgMCAwIDI0em0wIDVjLTEzLjggMC0yNSA4LjQtMjUgMTkuMmg1MEM3NSA1OC40IDYzLjggNTAgNTAgNTB6Ii8+PC9zdmc+\';">
                            <p style="margin-top: 10px; color: #6b7280; font-size: 12px;">Uploaded: ' . htmlspecialchars($uploadedDateFormatted, ENT_QUOTES, 'UTF-8') . '</p>
                        </div>');
                    })
                    ->visible(fn ($record) => $record->profile !== null)
                    ->columnSpanFull(),
                
                Placeholder::make('profile.phone_number')
                    ->label('📱 Phone Number')
                    ->content(fn ($record) => $record->profile?->phone_number ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.date_of_birth')
                    ->label('🎂 Date of Birth')
                    ->content(function ($record) {
                        $date = $record->profile?->date_of_birth;
                        if (!$date) {
                            return 'Not Provided';
                        }
                        return is_string($date) ? $date : ($date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : $date);
                    })
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.gender')
                    ->label('Gender')
                    ->content(fn ($record) => ucfirst($record->profile?->gender ?? 'Not Specified'))
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.address')
                    ->label('📍 Address')
                    ->content(fn ($record) => $record->profile?->address ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.city')
                    ->label('City')
                    ->content(fn ($record) => $record->profile?->city ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.state')
                    ->label('State')
                    ->content(fn ($record) => $record->profile?->state ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.pincode')
                    ->label('Pincode')
                    ->content(fn ($record) => $record->profile?->pincode ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('heading_vehicle')
                    ->label('VEHICLE DETAILS')
                    ->content('')
                    ->visible(fn ($record) => $record->profile !== null)
                    ->columnSpanFull(),
                
                Placeholder::make('profile.vehicle_type')
                    ->label('🚗 Vehicle Type')
                    ->content(fn ($record) => strtoupper($record->profile?->vehicle_type ?? 'Not Specified'))
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.vehicle_number')
                    ->label('🔢 Vehicle Number')
                    ->content(fn ($record) => $record->profile?->vehicle_number ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.vehicle_model')
                    ->label('Vehicle Model')
                    ->content(fn ($record) => $record->profile?->vehicle_model ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.vehicle_color')
                    ->label('Vehicle Color')
                    ->content(fn ($record) => $record->profile?->vehicle_color ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.vehicle_year')
                    ->label('Vehicle Year')
                    ->content(fn ($record) => $record->profile?->vehicle_year ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('heading_license')
                    ->label('LICENSE & DOCUMENTS')
                    ->content('')
                    ->visible(fn ($record) => $record->profile !== null)
                    ->columnSpanFull(),
                
                Placeholder::make('profile.license_number')
                    ->label('License Number')
                    ->content(fn ($record) => $record->profile?->license_number ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.license_expiry')
                    ->label('License Expiry')
                    ->content(function ($record) {
                        $date = $record->profile?->license_expiry;
                        if (!$date) {
                            return 'Not Provided';
                        }
                        if (is_string($date)) {
                            // BS date string - check if expired by converting to AD
                            try {
                                $adDate = \App\Services\NepalDateService::toAD($date);
                                return $date . ($adDate->isFuture() ? ' ✅' : ' ⚠️ EXPIRED');
                            } catch (\Exception $e) {
                                return $date;
                            }
                        }
                        // Carbon instance
                        return $date->format('Y-m-d') . ($date->isFuture() ? ' ✅' : ' ⚠️ EXPIRED');
                    })
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('all_documents')
                    ->label('📄 Uploaded Documents')
                    ->content(function ($record) {
                        if (!$record->profile) {
                            return 'No profile found';
                        }
                        
                        $documents = [
                            'License Document' => $record->profile->license_document,
                            'Vehicle RC Document' => $record->profile->vehicle_rc_document,
                            'Insurance Document' => $record->profile->insurance_document,
                            'Citizenship Document' => $record->profile->citizenship_document,
                            'PAN Document' => $record->profile->pan_document,
                        ];
                        
                        $html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 15px;">';
                        
                        foreach ($documents as $label => $path) {
                            if ($path) {
                                $fileUrl = asset('storage/' . $path);
                                $extension = pathinfo($path, PATHINFO_EXTENSION);
                                
                                if (in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                    // Display image
                                    $html .= '<div style="border: 2px solid #e5e7eb; border-radius: 8px; padding: 10px; background: white;">';
                                    $html .= '<h4 style="margin: 0 0 10px 0; font-size: 14px; color: #374151;">' . $label . '</h4>';
                                    $html .= '<a href="' . $fileUrl . '" target="_blank">';
                                    $html .= '<img src="' . $fileUrl . '" alt="' . $label . '" style="width: 100%; height: 200px; object-fit: cover; border-radius: 6px; cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform=\'scale(1.05)\'" onmouseout="this.style.transform=\'scale(1)\'">';
                                    $html .= '</a>';
                                    $html .= '<div style="margin-top: 8px; text-align: center;">';
                                    $html .= '<a href="' . $fileUrl . '" target="_blank" style="color: #3b82f6; text-decoration: none; font-size: 12px;">🔍 View Full Size</a>';
                                    $html .= '</div>';
                                    $html .= '</div>';
                                } else {
                                    // Display PDF or other file
                                    $html .= '<div style="border: 2px solid #e5e7eb; border-radius: 8px; padding: 20px; background: white; text-align: center;">';
                                    $html .= '<h4 style="margin: 0 0 15px 0; font-size: 14px; color: #374151;">' . $label . '</h4>';
                                    $html .= '<svg style="width: 80px; height: 80px; color: #ef4444; margin: 0 auto;" fill="currentColor" viewBox="0 0 20 20">';
                                    $html .= '<path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>';
                                    $html .= '</svg>';
                                    $html .= '<p style="margin: 10px 0; color: #6b7280; font-size: 12px; text-transform: uppercase;">' . strtoupper($extension) . ' File</p>';
                                    $html .= '<a href="' . $fileUrl . '" target="_blank" style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; font-size: 13px;">📥 Download / View</a>';
                                    $html .= '</div>';
                                }
                            } else {
                                // Document not uploaded
                                $html .= '<div style="border: 2px dashed #d1d5db; border-radius: 8px; padding: 20px; background: #f9fafb; text-align: center;">';
                                $html .= '<h4 style="margin: 0 0 10px 0; font-size: 14px; color: #9ca3af;">' . $label . '</h4>';
                                $html .= '<svg style="width: 60px; height: 60px; color: #d1d5db; margin: 0 auto;" fill="currentColor" viewBox="0 0 20 20">';
                                $html .= '<path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>';
                                $html .= '</svg>';
                                $html .= '<p style="margin-top: 10px; color: #9ca3af; font-size: 12px;">Not Uploaded</p>';
                                $html .= '</div>';
                            }
                        }
                        
                        $html .= '</div>';
                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->visible(fn ($record) => $record->profile !== null)
                    ->columnSpanFull(),
                
                Placeholder::make('heading_service')
                    ->label('SERVICE AREA')
                    ->content('')
                    ->visible(fn ($record) => $record->profile !== null)
                    ->columnSpanFull(),
                
                Placeholder::make('profile.service_address')
                    ->label('📍 Service Address')
                    ->content(fn ($record) => $record->profile?->service_address ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.service_latitude')
                    ->label('🌍 Latitude')
                    ->content(fn ($record) => $record->profile?->service_latitude ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.service_longitude')
                    ->label('🌍 Longitude')
                    ->content(fn ($record) => $record->profile?->service_longitude ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.service_radius')
                    ->label('Service Radius')
                    ->content(fn ($record) => $record->profile?->service_radius ? $record->profile->service_radius . ' meters' : 'Not Set')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('service_location_map')
                    ->label('📍 Service Location Map')
                    ->content(function ($record) {
                        if (!$record->profile || !$record->profile->service_latitude || !$record->profile->service_longitude) {
                            return 'Location coordinates not available';
                        }
                        
                        $lat = $record->profile->service_latitude;
                        $lng = $record->profile->service_longitude;
                        $radius = $record->profile->service_radius ?? 5000;
                        $mapId = 'vendor-map-' . $record->id;
                        
                        $html = '
                        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
                        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                        
                        <div id="' . $mapId . '" style="width: 100%; height: 400px; border-radius: 8px; border: 2px solid #e5e7eb;"></div>
                        
                        <script>
                            (function() {
                                if (document.getElementById("' . $mapId . '")._leaflet_id) {
                                    return;
                                }
                                
                                var map = L.map("' . $mapId . '").setView([' . $lat . ', ' . $lng . '], 13);
                                
                                L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                                    attribution: \'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors\',
                                    maxZoom: 19
                                }).addTo(map);
                                
                                var marker = L.marker([' . $lat . ', ' . $lng . ']).addTo(map)
                                    .bindPopup("<b>' . htmlspecialchars($record->name, ENT_QUOTES) . '</b><br>' . htmlspecialchars($record->profile->service_address ?? 'Service Location', ENT_QUOTES) . '")
                                    .openPopup();
                                
                                var circle = L.circle([' . $lat . ', ' . $lng . '], {
                                    color: "red",
                                    fillColor: "#f03",
                                    fillOpacity: 0.1,
                                    radius: ' . $radius . '
                                }).addTo(map);
                                
                                map.fitBounds(circle.getBounds());
                            })();
                        </script>
                        ';
                        
                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->visible(fn ($record) => $record->profile && $record->profile->service_latitude && $record->profile->service_longitude)
                    ->columnSpanFull(),
                
                Placeholder::make('heading_verification')
                    ->label('VERIFICATION & STATUS')
                    ->content('')
                    ->visible(fn ($record) => $record->profile !== null)
                    ->columnSpanFull(),
                
                Placeholder::make('profile.verification_status')
                    ->label('Verification Status')
                    ->content(fn ($record) => match($record->profile?->verification_status) {
                        'approved' => '✅ APPROVED',
                        'rejected' => '❌ REJECTED',
                        default => '⏳ PENDING',
                    })
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.is_verified')
                    ->label('Verified')
                    ->content(fn ($record) => $record->profile?->is_verified ? '✅ Yes' : '❌ No')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.is_online')
                    ->label('Online Status')
                    ->content(fn ($record) => $record->profile?->is_online ? '🟢 Online' : '⚫ Offline')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.is_available')
                    ->label('Availability')
                    ->content(fn ($record) => $record->profile?->is_available ? '✅ Available' : '⚠️ Unavailable')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.rating')
                    ->label('⭐ Rating')
                    ->content(fn ($record) => ($record->profile?->rating ?? 0) . ' / 5.0')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.total_rides')
                    ->label('🚗 Total Rides')
                    ->content(fn ($record) => $record->profile?->total_rides ?? 0)
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.rejection_reason')
                    ->label('❌ Rejection Reason')
                    ->content(fn ($record) => $record->profile?->rejection_reason ?? 'N/A')
                    ->visible(fn ($record) => $record->profile && $record->profile->verification_status === 'rejected'),
                
                Placeholder::make('heading_availability')
                    ->label('WEEKLY AVAILABILITY')
                    ->content('')
                    ->visible(fn ($record) => $record->availabilities && $record->availabilities->count() > 0)
                    ->columnSpanFull(),
                
                Placeholder::make('availability_schedule')
                    ->label('')
                    ->content(function ($record) {
                        if (!$record->availabilities || $record->availabilities->count() === 0) {
                            return 'No availability schedule set';
                        }

                        $html = '<div style="font-family: monospace;">';
                        $html .= '<table style="width: 100%; border-collapse: collapse;">';
                        $html .= '<thead><tr style="background: #f3f4f6;">';
                        $html .= '<th style="padding: 8px; border: 1px solid #e5e7eb; text-align: left;">Day</th>';
                        $html .= '<th style="padding: 8px; border: 1px solid #e5e7eb; text-align: left;">Status</th>';
                        $html .= '<th style="padding: 8px; border: 1px solid #e5e7eb; text-align: left;">Start Time</th>';
                        $html .= '<th style="padding: 8px; border: 1px solid #e5e7eb; text-align: left;">End Time</th>';
                        $html .= '</tr></thead><tbody>';
                        
                        foreach ($record->availabilities->sortBy('day_of_week') as $availability) {
                            $status = $availability->is_available ? '✅ Available' : '❌ Not Available';
                            $startTime = $availability->start_time ? \Carbon\Carbon::parse($availability->start_time)->format('H:i') : '—';
                            $endTime = $availability->end_time ? \Carbon\Carbon::parse($availability->end_time)->format('H:i') : '—';
                            
                            $html .= '<tr>';
                            $html .= '<td style="padding: 8px; border: 1px solid #e5e7eb;">' . ucfirst($availability->day_of_week) . '</td>';
                            $html .= '<td style="padding: 8px; border: 1px solid #e5e7eb;">' . $status . '</td>';
                            $html .= '<td style="padding: 8px; border: 1px solid #e5e7eb;">' . $startTime . '</td>';
                            $html .= '<td style="padding: 8px; border: 1px solid #e5e7eb;">' . $endTime . '</td>';
                            $html .= '</tr>';
                        }
                        
                        $html .= '</tbody></table></div>';
                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->visible(fn ($record) => $record->availabilities && $record->availabilities->count() > 0)
                    ->columnSpanFull(),
                
                Placeholder::make('heading_payouts')
                    ->label('PAYOUT INFORMATION')
                    ->content('')
                    ->columnSpanFull(),
                
                Placeholder::make('payout_summary')
                    ->label('Payout Summary')
                    ->content(function ($record) {
                        $completedCount = \App\Models\RenewalRequest::where('vendor_id', $record->id)
                            ->where('status', 'completed')
                            ->count();
                        $perRequest = 250.0;
                        $totalEarned = $completedCount * $perRequest;
                        $totalPaid = (float) \App\Models\VendorPayout::where('vendor_id', $record->id)
                            ->where('status', 'paid')
                            ->sum('amount');
                        $pending = max(0, $totalEarned - $totalPaid);
                        
                        $html = '<div style="background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">';
                        $html .= '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
                        $html .= '<div><strong>Completed Tasks:</strong><br><span style="font-size: 18px; color: #059669;">' . $completedCount . '</span></div>';
                        $html .= '<div><strong>Total Earned:</strong><br><span style="font-size: 18px; color: #059669;">NPR ' . number_format($totalEarned, 2) . '</span></div>';
                        $html .= '<div><strong>Total Paid:</strong><br><span style="font-size: 18px; color: #2563eb;">NPR ' . number_format($totalPaid, 2) . '</span></div>';
                        $html .= '<div><strong>Pending Payout:</strong><br><span style="font-size: 18px; color: ' . ($pending > 0 ? '#dc2626' : '#059669') . ';">NPR ' . number_format($pending, 2) . '</span></div>';
                        $html .= '</div></div>';
                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->columnSpanFull(),
                
                Placeholder::make('paid_statements')
                    ->label('Paid Statements')
                    ->content(function ($record) {
                        $paidPayouts = \App\Models\VendorPayout::where('vendor_id', $record->id)
                            ->where('status', 'paid')
                            ->orderByDesc('paid_at')
                            ->get();
                        
                        if ($paidPayouts->isEmpty()) {
                            return '<div style="padding: 15px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; text-align: center; color: #6b7280;">No paid statements yet.</div>';
                        }
                        
                        $html = '<div style="background: white; border-radius: 8px; border: 1px solid #e5e7eb; overflow: hidden;">';
                        $html .= '<table style="width: 100%; border-collapse: collapse;">';
                        $html .= '<thead><tr style="background: #f3f4f6;">';
                        $html .= '<th style="padding: 12px; border-bottom: 2px solid #e5e7eb; text-align: left; font-weight: 600;">Date</th>';
                        $html .= '<th style="padding: 12px; border-bottom: 2px solid #e5e7eb; text-align: right; font-weight: 600;">Amount</th>';
                        $html .= '<th style="padding: 12px; border-bottom: 2px solid #e5e7eb; text-align: left; font-weight: 600;">Period</th>';
                        $html .= '<th style="padding: 12px; border-bottom: 2px solid #e5e7eb; text-align: left; font-weight: 600;">Notes</th>';
                        $html .= '</tr></thead><tbody>';
                        
                        foreach ($paidPayouts as $payout) {
                            $paidDate = $payout->paid_at ? $payout->paid_at->format('Y-m-d') : 'N/A';
                            $monthName = $payout->month ? date('F', mktime(0, 0, 0, $payout->month, 1)) : '—';
                            $period = $monthName . ' ' . $payout->year;
                            $notes = $payout->notes ? substr($payout->notes, 0, 50) . (strlen($payout->notes) > 50 ? '...' : '') : '—';
                            
                            $html .= '<tr style="border-bottom: 1px solid #e5e7eb;">';
                            $html .= '<td style="padding: 12px; color: #059669; font-weight: 500;">' . htmlspecialchars($paidDate) . '</td>';
                            $html .= '<td style="padding: 12px; text-align: right; font-weight: 600; color: #059669;">NPR ' . number_format((float) $payout->amount, 2) . '</td>';
                            $html .= '<td style="padding: 12px; color: #6b7280;">' . htmlspecialchars($period) . '</td>';
                            $html .= '<td style="padding: 12px; color: #6b7280; font-size: 13px;">' . htmlspecialchars($notes) . '</td>';
                            $html .= '</tr>';
                        }
                        
                        $html .= '</tbody></table></div>';
                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->columnSpanFull(),
            ]);
    }
}
