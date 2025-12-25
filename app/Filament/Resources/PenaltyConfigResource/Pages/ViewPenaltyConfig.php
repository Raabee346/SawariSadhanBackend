<?php

namespace App\Filament\Resources\PenaltyConfigResource\Pages;

use App\Filament\Resources\PenaltyConfigResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPenaltyConfig extends ViewRecord
{
    protected static string $resource = PenaltyConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}

