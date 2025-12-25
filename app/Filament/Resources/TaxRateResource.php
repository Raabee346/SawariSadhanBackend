<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxRateResource\Pages;
use App\Models\TaxRate;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TaxRateResource extends Resource
{
    protected static ?string $model = TaxRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static UnitEnum|string|null $navigationGroup = 'Tax & Insurance';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('province_id')
                    ->relationship('province', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('fiscal_year_id')
                    ->relationship('fiscalYear', 'year')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('vehicle_type')
                    ->options([
                        '2W' => 'Two Wheeler',
                        '4W' => 'Four Wheeler',
                        'Commercial' => 'Commercial',
                        'Heavy' => 'Heavy Vehicle',
                    ])
                    ->required(),
                Select::make('fuel_type')
                    ->options([
                        'Petrol' => 'Petrol',
                        'Diesel' => 'Diesel',
                        'Electric' => 'Electric',
                    ])
                    ->required(),
                TextInput::make('capacity_value')
                    ->numeric()
                    ->required()
                    ->label('Capacity (CC/Watts)')
                    ->helperText('Exact engine capacity value'),
                TextInput::make('annual_tax_amount')
                    ->numeric()
                    ->required()
                    ->prefix('NPR')
                    ->step(0.01),
                TextInput::make('renewal_fee')
                    ->numeric()
                    ->default(300)
                    ->prefix('NPR')
                    ->step(0.01),
                Textarea::make('notes')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('province.name')
                    ->label('Province')
                    ->sortable(),
                TextColumn::make('fiscalYear.year')
                    ->label('Fiscal Year')
                    ->sortable(),
                TextColumn::make('vehicle_type')
                    ->badge(),
                TextColumn::make('fuel_type')
                    ->badge(),
                TextColumn::make('capacity_value')
                    ->label('Capacity (CC/W)')
                    ->sortable(),
                TextColumn::make('annual_tax_amount')
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('renewal_fee')
                    ->money('NPR'),
            ])
            ->defaultSort('fiscal_year_id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTaxRates::route('/'),
        ];
    }
}

