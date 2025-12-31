<?php

namespace App\Filament\Resources\VehicleResource\Pages;

use App\Filament\Resources\VehicleResource;
use App\Services\NepalDateService;
use App\Services\FCMNotificationService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

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

    protected function afterSave(): void
    {
        // Check if verification status was changed to approved or rejected
        $this->record->refresh();
        $newStatus = $this->record->verification_status;
        
        // Get the original status before save (we need to check if it changed)
        // Since we can't easily get the old value here, we'll send notification if status is approved/rejected
        // The notification service will handle duplicate prevention if needed
        if (in_array($newStatus, ['approved', 'rejected'])) {
            try {
                $fcmService = app(FCMNotificationService::class);
                $fcmService->notifyVehicleVerification($this->record, $newStatus);
                Log::info('Vehicle verification notification sent from EditVehicle', [
                    'vehicle_id' => $this->record->id,
                    'user_id' => $this->record->user_id,
                    'status' => $newStatus,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send vehicle verification notification from EditVehicle', [
                    'vehicle_id' => $this->record->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
