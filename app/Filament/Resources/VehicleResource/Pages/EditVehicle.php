<?php

namespace App\Filament\Resources\VehicleResource\Pages;

use App\Filament\Resources\VehicleResource;
use App\Services\NepalDateService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Dates are already in BS format, no conversion needed
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Store BS dates directly (no conversion)

        // Update verification fields if status is being changed
        if (isset($data['verification_status'])) {
            $currentStatus = $this->record->verification_status;
            if ($data['verification_status'] !== $currentStatus && in_array($data['verification_status'], ['approved', 'rejected'])) {
                $data['verified_by'] = auth()->id();
                $data['verified_at'] = now();
                
                // Clear rejection reason if approving
                if ($data['verification_status'] === 'approved') {
                    $data['rejection_reason'] = null;
                }
            }
        }

        return $data;
    }
}
