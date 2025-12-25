<?php

namespace App\Filament\Resources\InsuranceRateResource\Pages;

use App\Filament\Resources\InsuranceRateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInsuranceRates extends ManageRecords
{
    protected static string $resource = InsuranceRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

