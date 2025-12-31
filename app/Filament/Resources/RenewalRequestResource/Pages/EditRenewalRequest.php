<?php

namespace App\Filament\Resources\RenewalRequestResource\Pages;

use App\Filament\Resources\RenewalRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRenewalRequest extends EditRecord
{
    protected static string $resource = RenewalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

