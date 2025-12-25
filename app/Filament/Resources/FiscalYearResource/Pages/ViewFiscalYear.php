<?php

namespace App\Filament\Resources\FiscalYearResource\Pages;

use App\Filament\Resources\FiscalYearResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFiscalYear extends ViewRecord
{
    protected static string $resource = FiscalYearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}

