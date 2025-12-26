<?php

namespace App\Filament\Resources\VehicleResource\Pages;

use App\Filament\Resources\VehicleResource;
use App\Services\NepalDateService;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class ViewVehicle extends ViewRecord
{
    protected static string $resource = VehicleResource::class;

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
                Placeholder::make('heading_vehicle')
                    ->label('VEHICLE INFORMATION')
                    ->content('')
                    ->columnSpanFull(),
                
                Placeholder::make('owner_name')
                    ->label('Owner Name')
                    ->content(fn ($record) => $record->owner_name ?? 'N/A'),
                
                Placeholder::make('registration_number')
                    ->label('Registration Number')
                    ->content(fn ($record) => $record->registration_number),
                
                Placeholder::make('chassis_number')
                    ->label('Chassis Number')
                    ->content(fn ($record) => $record->chassis_number ?? 'N/A'),
                
                Placeholder::make('user.name')
                    ->label('Added By')
                    ->content(fn ($record) => $record->user->name ?? 'N/A'),
                
                Placeholder::make('province.name')
                    ->label('Province')
                    ->content(fn ($record) => $record->province->name ?? 'N/A'),
                
                Placeholder::make('vehicle_type')
                    ->label('Vehicle Type')
                    ->content(fn ($record) => $record->vehicle_type),
                
                Placeholder::make('fuel_type')
                    ->label('Fuel Type')
                    ->content(fn ($record) => $record->fuel_type),
                
                Placeholder::make('brand')
                    ->label('Brand')
                    ->content(fn ($record) => $record->brand ?? 'N/A'),
                
                Placeholder::make('model')
                    ->label('Model')
                    ->content(fn ($record) => $record->model ?? 'N/A'),
                
                Placeholder::make('engine_capacity')
                    ->label('Engine Capacity')
                    ->content(fn ($record) => $record->engine_capacity . ' ' . ($record->fuel_type === 'Electric' ? 'Watts' : 'CC')),
                
                Placeholder::make('manufacturing_year')
                    ->label('Manufacturing Year')
                    ->content(fn ($record) => $record->manufacturing_year ?? 'N/A'),
                
                Placeholder::make('registration_date')
                    ->label('Registration Date (BS)')
                    ->content(fn ($record) => $record->registration_date ?? 'N/A'),
                
                Placeholder::make('last_renewed_date')
                    ->label('Last Renewed Date (BS)')
                    ->content(fn ($record) => $record->last_renewed_date ?? 'N/A'),
                
                Placeholder::make('verification_status')
                    ->label('Verification Status')
                    ->content(fn ($record) => match($record->verification_status) {
                        'approved' => '✅ APPROVED',
                        'rejected' => '❌ REJECTED',
                        default => '⏳ PENDING',
                    }),
                
                Placeholder::make('is_commercial')
                    ->label('Commercial Vehicle')
                    ->content(fn ($record) => $record->is_commercial ? '✅ Yes' : '❌ No'),
                
                Placeholder::make('rejection_reason')
                    ->label('Rejection Reason')
                    ->content(fn ($record) => $record->rejection_reason ?? 'N/A')
                    ->visible(fn ($record) => $record->verification_status === 'rejected')
                    ->columnSpanFull(),
                
                Placeholder::make('verifiedBy.name')
                    ->label('Verified By')
                    ->content(fn ($record) => $record->verifiedBy->name ?? 'N/A')
                    ->visible(fn ($record) => $record->verified_by !== null),
                
                Placeholder::make('verified_at')
                    ->label('Verified At')
                    ->content(fn ($record) => $record->verified_at ? $record->verified_at->format('Y-m-d H:i') : 'N/A')
                    ->visible(fn ($record) => $record->verified_at !== null),
                
                Placeholder::make('heading_documents')
                    ->label('📄 VEHICLE DOCUMENTS')
                    ->content('')
                    ->columnSpanFull(),
                
                Placeholder::make('all_documents')
                    ->label('Uploaded Documents')
                    ->content(function ($record) {
                        $documents = [
                            'RC First Page' => $record->rc_firstpage,
                            'RC Owner Details' => $record->rc_ownerdetails,
                            'RC Vehicle Details' => $record->rc_vehicledetails,
                            'Last Renewal Date' => $record->lastrenewdate,
                            'Insurance' => $record->insurance,
                            'Owner Citizenship (Front)' => $record->owner_ctznship_front,
                            'Owner Citizenship (Back)' => $record->owner_ctznship_back,
                        ];
                        
                        $html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 15px;">';
                        
                        foreach ($documents as $label => $path) {
                            if ($path) {
                                // Check if file exists
                                if (!Storage::disk('public')->exists($path)) {
                                    $html .= '<div style="border: 2px dashed #d1d5db; border-radius: 8px; padding: 20px; background: #f9fafb; text-align: center;">';
                                    $html .= '<h4 style="margin: 0 0 10px 0; font-size: 14px; color: #9ca3af;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</h4>';
                                    $html .= '<p style="margin-top: 10px; color: #9ca3af; font-size: 12px;">File not found: ' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '</p>';
                                    $html .= '</div>';
                                    continue;
                                }
                                
                                // Generate URL - files are stored at storage/app/public/vehicles/documents/
                                // The path in DB is like: vehicles/documents/filename.jpg
                                // We need: /storage/vehicles/documents/filename.jpg
                                $fileUrl = asset('storage/' . $path);
                                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                
                                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                    // Display image
                                    $html .= '<div style="border: 2px solid #e5e7eb; border-radius: 8px; padding: 10px; background: white;">';
                                    $html .= '<h4 style="margin: 0 0 10px 0; font-size: 14px; color: #374151;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</h4>';
                                    $html .= '<a href="' . htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank">';
                                    $html .= '<img src="' . htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" style="width: 100%; height: 200px; object-fit: cover; border-radius: 6px; cursor: pointer; transition: transform 0.2s; background: #f3f4f6;" onmouseover="this.style.transform=\'scale(1.05)\'" onmouseout="this.style.transform=\'scale(1)\'">';
                                    $html .= '</a>';
                                    $html .= '<div style="margin-top: 8px; text-align: center;">';
                                    $html .= '<a href="' . htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" style="color: #3b82f6; text-decoration: none; font-size: 12px;">🔍 View Full Size</a>';
                                    $html .= '</div>';
                                    $html .= '</div>';
                                } else {
                                    // Display PDF or other file
                                    $html .= '<div style="border: 2px solid #e5e7eb; border-radius: 8px; padding: 20px; background: white; text-align: center;">';
                                    $html .= '<h4 style="margin: 0 0 15px 0; font-size: 14px; color: #374151;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</h4>';
                                    $html .= '<svg style="width: 80px; height: 80px; color: #ef4444; margin: 0 auto;" fill="currentColor" viewBox="0 0 20 20">';
                                    $html .= '<path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>';
                                    $html .= '</svg>';
                                    $html .= '<p style="margin: 10px 0; color: #6b7280; font-size: 12px; text-transform: uppercase;">' . strtoupper($extension) . ' File</p>';
                                    $html .= '<a href="' . htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; font-size: 13px;">📥 Download / View</a>';
                                    $html .= '</div>';
                                }
                            } else {
                                // Document not uploaded
                                $html .= '<div style="border: 2px dashed #d1d5db; border-radius: 8px; padding: 20px; background: #f9fafb; text-align: center;">';
                                $html .= '<h4 style="margin: 0 0 10px 0; font-size: 14px; color: #9ca3af;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</h4>';
                                $html .= '<svg style="width: 60px; height: 60px; color: #d1d5db; margin: 0 auto;" fill="currentColor" viewBox="0 0 20 20">';
                                $html .= '<path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>';
                                $html .= '</svg>';
                                $html .= '<p style="margin-top: 10px; color: #9ca3af; font-size: 12px;">Not Uploaded</p>';
                                $html .= '</div>';
                            }
                        }
                        
                        $html .= '</div>';
                        return new HtmlString($html);
                    })
                    ->columnSpanFull(),
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Dates are already in BS format, no conversion needed
        return $data;
    }
}
