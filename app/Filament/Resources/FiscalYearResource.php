<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FiscalYearResource\Pages;
use App\Models\FiscalYear;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class FiscalYearResource extends Resource
{
    protected static ?string $model = FiscalYear::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('year')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., 2081/82'),
                DatePicker::make('start_date')
                    ->required()
                    ->native(false),
                DatePicker::make('end_date')
                    ->required()
                    ->native(false),
                Toggle::make('is_current')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('year')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                IconColumn::make('is_current')
                    ->boolean()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('start_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageFiscalYears::route('/'),
            'view' => Pages\ViewFiscalYear::route('/{record}'),
            'edit' => Pages\EditFiscalYear::route('/{record}/edit'),
        ];
    }
}

