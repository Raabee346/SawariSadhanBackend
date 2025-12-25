<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static UnitEnum|string|null $navigationGroup = 'Vehicle Management';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('vehicle_id')
                    ->relationship('vehicle', 'registration_number')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('fiscal_year_id')
                    ->relationship('fiscalYear', 'year')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('tax_amount')
                    ->numeric()
                    ->prefix('NPR')
                    ->step(0.01),
                TextInput::make('renewal_fee')
                    ->numeric()
                    ->prefix('NPR')
                    ->step(0.01),
                TextInput::make('penalty_amount')
                    ->numeric()
                    ->prefix('NPR')
                    ->step(0.01),
                TextInput::make('insurance_amount')
                    ->numeric()
                    ->prefix('NPR')
                    ->step(0.01),
                TextInput::make('total_amount')
                    ->numeric()
                    ->prefix('NPR')
                    ->step(0.01),
                Select::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->required(),
                TextInput::make('payment_method')
                    ->maxLength(255),
                TextInput::make('transaction_id')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('vehicle.registration_number')
                    ->label('Vehicle')
                    ->searchable(),
                TextColumn::make('fiscalYear.year')
                    ->label('Fiscal Year'),
                TextColumn::make('total_amount')
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('payment_method')
                    ->badge(),
                TextColumn::make('payment_date')
                    ->date()
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
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}

