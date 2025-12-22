<?php

namespace App\Filament\Widgets;

use App\Models\Vendor;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TotalVendorWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Vendors', Vendor::count())
                ->description('Total number of registered vendors')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary'),
        ];
    }
}