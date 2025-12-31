<?php

namespace App\Filament\Resources\RenewalRequestResource\Pages;

use App\Filament\Resources\RenewalRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRenewalRequests extends ListRecords
{
    protected static string $resource = RenewalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

