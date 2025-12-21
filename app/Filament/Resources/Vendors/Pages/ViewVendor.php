<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\Vendors\VendorResource;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

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
                    ->content(fn ($record) => $record->created_at->format('Y-m-d H:i')),
                
                Placeholder::make('updated_at')
                    ->label('Last Updated')
                    ->content(fn ($record) => $record->updated_at->format('Y-m-d H:i')),
                
                Placeholder::make('heading_personal')
                    ->label('PERSONAL DETAILS')
                    ->content('')
                    ->visible(fn ($record) => $record->profile !== null)
                    ->columnSpanFull(),
                
                Placeholder::make('profile.phone_number')
                    ->label('📱 Phone Number')
                    ->content(fn ($record) => $record->profile?->phone_number ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.date_of_birth')
                    ->label('🎂 Date of Birth')
                    ->content(fn ($record) => $record->profile?->date_of_birth?->format('Y-m-d') ?? 'Not Provided')
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
                    ->content(fn ($record) => $record->profile?->license_expiry ? $record->profile->license_expiry->format('Y-m-d') . ($record->profile->license_expiry->isFuture() ? ' ✅' : ' ⚠️ EXPIRED') : 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.license_document')
                    ->label('📄 License Document')
                    ->content(fn ($record) => $record->profile?->license_document ? '✅ Uploaded' : '❌ Not Uploaded')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.vehicle_rc_document')
                    ->label('📄 Vehicle RC Document')
                    ->content(fn ($record) => $record->profile?->vehicle_rc_document ? '✅ Uploaded' : '❌ Not Uploaded')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.insurance_document')
                    ->label('📄 Insurance Document')
                    ->content(fn ($record) => $record->profile?->insurance_document ? '✅ Uploaded' : '❌ Not Uploaded')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.citizenship_document')
                    ->label('📄 Citizenship Document')
                    ->content(fn ($record) => $record->profile?->citizenship_document ? '✅ Uploaded' : '❌ Not Uploaded')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('profile.pan_document')
                    ->label('📄 PAN Document')
                    ->content(fn ($record) => $record->profile?->pan_document ? '✅ Uploaded' : '❌ Not Uploaded')
                    ->visible(fn ($record) => $record->profile !== null),
                
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
            ]);
    }
}
