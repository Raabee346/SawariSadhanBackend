<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

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
                Placeholder::make('heading_user')
                    ->label('USER INFORMATION')
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
                    ->content(fn ($record) => $record->email_verified_at ? '✅ Verified on ' . $record->email_verified_at->format('Y-m-d H:i') : '❌ Not Verified'),
                
                Placeholder::make('created_at')
                    ->label('Registered On')
                    ->content(fn ($record) => $record->created_at->format('Y-m-d H:i')),
                
                Placeholder::make('updated_at')
                    ->label('Last Updated')
                    ->content(fn ($record) => $record->updated_at->format('Y-m-d H:i')),
                
                Placeholder::make('heading_profile')
                    ->label('PROFILE INFORMATION')
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
                
                Placeholder::make('heading_address')
                    ->label('ADDRESS INFORMATION')
                    ->content('')
                    ->visible(fn ($record) => $record->profile !== null)
                    ->columnSpanFull(),
                
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
                
                Placeholder::make('profile.country')
                    ->label('Country')
                    ->content(fn ($record) => $record->profile?->country ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null),
                
                Placeholder::make('heading_location')
                    ->label('LOCATION COORDINATES')
                    ->content('')
                    ->visible(fn ($record) => $record->profile !== null && ($record->profile->latitude || $record->profile->longitude))
                    ->columnSpanFull(),
                
                Placeholder::make('profile.latitude')
                    ->label('🌍 Latitude')
                    ->content(fn ($record) => $record->profile?->latitude ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null && ($record->profile->latitude || $record->profile->longitude)),
                
                Placeholder::make('profile.longitude')
                    ->label('🌍 Longitude')
                    ->content(fn ($record) => $record->profile?->longitude ?? 'Not Provided')
                    ->visible(fn ($record) => $record->profile !== null && ($record->profile->latitude || $record->profile->longitude)),
                
                Placeholder::make('user_location_map')
                    ->label('📍 Location Map')
                    ->content(function ($record) {
                        if (!$record->profile || !$record->profile->latitude || !$record->profile->longitude) {
                            return 'Location coordinates not available';
                        }
                        
                        $lat = $record->profile->latitude;
                        $lng = $record->profile->longitude;
                        $mapId = 'user-map-' . $record->id;
                        
                        $html = '
                        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
                        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                        
                        <div id="' . $mapId . '" style="width: 100%; height: 400px; border-radius: 8px; border: 2px solid #e5e7eb;"></div>
                        
                        <script>
                            (function() {
                                if (document.getElementById("' . $mapId . '")._leaflet_id) {
                                    return;
                                }
                                
                                var map = L.map("' . $mapId . '").setView([' . $lat . ', ' . $lng . '], 14);
                                
                                L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                                    attribution: \'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors\',
                                    maxZoom: 19
                                }).addTo(map);
                                
                                var marker = L.marker([' . $lat . ', ' . $lng . ']).addTo(map)
                                    .bindPopup("<b>' . htmlspecialchars($record->name, ENT_QUOTES) . '</b><br>' . htmlspecialchars($record->profile->address ?? 'User Location', ENT_QUOTES) . '")
                                    .openPopup();
                            })();
                        </script>
                        ';
                        
                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->visible(fn ($record) => $record->profile && $record->profile->latitude && $record->profile->longitude)
                    ->columnSpanFull(),
            ]);
    }
}
