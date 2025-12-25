<?php

namespace App\Filament\Resources\PenaltyConfigResource\Pages;

use App\Filament\Resources\PenaltyConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePenaltyConfigs extends ManageRecords
{
    protected static string $resource = PenaltyConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

