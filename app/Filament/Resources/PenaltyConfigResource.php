<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PenaltyConfigResource\Pages;
use App\Models\PenaltyConfig;
use BackedEnum;
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

class PenaltyConfigResource extends Resource
{
    protected static ?string $model = PenaltyConfig::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('duration_label')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g., First 30 Days'),
                TextInput::make('days_from_expiry')
                    ->numeric()
                    ->required()
                    ->label('Days From Expiry (after grace period)')
                    ->helperText('Days after the 90-day grace period'),
                TextInput::make('days_to')
                    ->numeric()
                    ->label('Days To (null = no limit)')
                    ->nullable(),
                TextInput::make('penalty_percentage')
                    ->numeric()
                    ->required()
                    ->suffix('%')
                    ->step(0.01),
                TextInput::make('renewal_fee_penalty_percentage')
                    ->numeric()
                    ->default(100)
                    ->suffix('%')
                    ->step(0.01),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('duration_label')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('days_from_expiry')
                    ->sortable(),
                TextColumn::make('days_to'),
                TextColumn::make('penalty_percentage')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('renewal_fee_penalty_percentage')
                    ->suffix('%'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('days_from_expiry', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePenaltyConfigs::route('/'),
            'view' => Pages\ViewPenaltyConfig::route('/{record}'),
            'edit' => Pages\EditPenaltyConfig::route('/{record}/edit'),
        ];
    }
}

