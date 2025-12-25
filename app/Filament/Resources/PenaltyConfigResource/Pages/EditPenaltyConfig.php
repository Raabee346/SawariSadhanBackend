<?php

namespace App\Filament\Resources\PenaltyConfigResource\Pages;

use App\Filament\Resources\PenaltyConfigResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPenaltyConfig extends EditRecord
{
    protected static string $resource = PenaltyConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}

