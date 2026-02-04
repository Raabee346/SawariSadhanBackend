<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorPayoutResource\Pages;
use App\Models\VendorPayout;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class VendorPayoutResource extends Resource
{
    protected static ?string $model = VendorPayout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static UnitEnum|string|null $navigationGroup = 'Vehicle Management';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('vendor_id')
                ->relationship('vendor', 'name')
                ->required()
                ->searchable()
                ->preload(),
            TextInput::make('amount')
                ->numeric()
                ->prefix('NPR')
                ->step(0.01)
                ->required(),
            Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'processing' => 'Processing',
                    'paid' => 'Paid',
                    'failed' => 'Failed',
                ])
                ->required(),
            TextInput::make('month')
                ->numeric()
                ->minValue(1)
                ->maxValue(12)
                ->required(),
            TextInput::make('year')
                ->numeric()
                ->minValue(2000)
                ->required(),
            TextInput::make('currency')
                ->maxLength(3)
                ->default('NPR'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable(),
                TextColumn::make('amount')
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'processing' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('month')
                    ->label('Month'),
                TextColumn::make('year')
                    ->label('Year'),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->label('Paid At')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorPayouts::route('/'),
        ];
    }
}

