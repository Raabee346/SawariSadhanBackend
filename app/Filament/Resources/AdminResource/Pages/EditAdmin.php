<?php

namespace App\Filament\Resources\AdminResource\Pages;

use App\Filament\Resources\AdminResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditAdmin extends EditRecord
{
    protected static string $resource = AdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // If admin is editing their own record and FCM token is being updated, show a notification
        if (Auth::guard('admin')->id() === $this->record->id && isset($data['fcm_token']) && !empty($data['fcm_token'])) {
            Notification::make()
                ->title('FCM Token Updated')
                ->body('You will now receive push notifications for vehicle verification requests.')
                ->success()
                ->send();
        }
        
        return $data;
    }
}
