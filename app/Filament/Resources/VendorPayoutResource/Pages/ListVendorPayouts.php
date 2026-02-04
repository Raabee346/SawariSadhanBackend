<?php

namespace App\Filament\Resources\VendorPayoutResource\Pages;

use App\Filament\Resources\VendorPayoutResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVendorPayouts extends ListRecords
{
    protected static string $resource = VendorPayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // We primarily create payouts via API/admin workflow, so no create action here.
        ];
    }
}

