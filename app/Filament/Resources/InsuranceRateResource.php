<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InsuranceRateResource\Pages;
use App\Models\InsuranceRate;
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

class InsuranceRateResource extends Resource
{
    protected static ?string $model = InsuranceRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Tax & Insurance';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                TextInput::make('annual_premium')
                    ->numeric()
                    ->required()
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
                TextColumn::make('annual_premium')
                    ->money('NPR')
                    ->sortable(),
            ])
            ->defaultSort('fiscal_year_id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageInsuranceRates::route('/'),
        ];
    }
}

