<?php

namespace App\Filament\Resources\RenewalRequestResource\Pages;

use App\Filament\Resources\RenewalRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRenewalRequest extends ViewRecord
{
    protected static string $resource = RenewalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

