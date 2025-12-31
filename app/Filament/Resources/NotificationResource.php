<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use BackedEnum;
use UnitEnum;

class NotificationResource extends Resource
{
    protected static ?string $model = null; // No model needed for this resource

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static UnitEnum|string|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Broadcast Notifications';

    public static function table(Table $table): Table
    {
        // This resource doesn't have a table since it's just for broadcasting
        return $table->columns([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\BroadcastNotification::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // We don't want a create page, just the broadcast page
    }
}

