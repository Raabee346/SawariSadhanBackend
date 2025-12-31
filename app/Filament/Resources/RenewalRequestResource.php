<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RenewalRequestResource\Pages;
use App\Models\RenewalRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use App\Models\FiscalYear;
use App\Models\Payment;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use BackedEnum;
use UnitEnum;

class RenewalRequestResource extends Resource
{
    protected static ?string $model = RenewalRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static UnitEnum|string|null $navigationGroup = 'Vehicle Management';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                    ]),
                Select::make('vehicle_id')
                    ->label('Vehicle')
                    ->relationship('vehicle', 'registration_number')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('payment_id')
                    ->label('Payment')
                    ->relationship('payment', 'transaction_id')
                    ->searchable()
                    ->preload(),
                Select::make('vendor_id')
                    ->label('Vendor (Rider)')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('fiscal_year_id')
                    ->label('Fiscal Year')
                    ->relationship('fiscalYear', 'year')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'assigned' => 'Assigned',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required()
                    ->default('pending'),
                TextInput::make('pickup_address')
                    ->label('Pickup Address')
                    ->maxLength(500)
                    ->columnSpanFull(),
                TextInput::make('pickup_latitude')
                    ->label('Pickup Latitude')
                    ->numeric()
                    ->step(0.00000001),
                TextInput::make('pickup_longitude')
                    ->label('Pickup Longitude')
                    ->numeric()
                    ->step(0.00000001),
                TextInput::make('dropoff_address')
                    ->label('Drop-off Address')
                    ->maxLength(500)
                    ->columnSpanFull(),
                TextInput::make('dropoff_latitude')
                    ->label('Drop-off Latitude')
                    ->numeric()
                    ->step(0.00000001),
                TextInput::make('dropoff_longitude')
                    ->label('Drop-off Longitude')
                    ->numeric()
                    ->step(0.00000001),
                DatePicker::make('pickup_date')
                    ->label('Pickup Date')
                    ->required(),
                TextInput::make('pickup_time_slot')
                    ->label('Pickup Time Slot')
                    ->maxLength(255),
                Toggle::make('has_insurance')
                    ->label('Has Insurance')
                    ->default(false),
                TextInput::make('tax_amount')
                    ->label('Tax Amount')
                    ->numeric()
                    ->prefix('NPR')
                    ->step(0.01),
                TextInput::make('renewal_fee')
                    ->label('Renewal Fee')
                    ->numeric()
                    ->prefix('NPR')
                    ->step(0.01),
                TextInput::make('penalty_amount')
                    ->label('Penalty Amount')
                    ->numeric()
                    ->prefix('NPR')
                    ->step(0.01),
                TextInput::make('insurance_amount')
                    ->label('Insurance Amount')
                    ->numeric()
                    ->prefix('NPR')
                    ->step(0.01),
                TextInput::make('service_fee')
                    ->label('Service Fee')
                    ->numeric()
                    ->prefix('NPR')
                    ->step(0.01),
                TextInput::make('vat_amount')
                    ->label('VAT Amount')
                    ->numeric()
                    ->prefix('NPR')
                    ->step(0.01),
                TextInput::make('total_amount')
                    ->label('Total Amount')
                    ->numeric()
                    ->prefix('NPR')
                    ->step(0.01)
                    ->required(),
                Select::make('payment_method')
                    ->options([
                        'cash_on_delivery' => 'Cash on Delivery',
                        'khalti' => 'Khalti',
                        'esewa' => 'eSewa',
                    ]),
                Select::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
                DateTimePicker::make('assigned_at')
                    ->label('Assigned At'),
                DateTimePicker::make('started_at')
                    ->label('Started At'),
                DateTimePicker::make('completed_at')
                    ->label('Completed At'),
                DateTimePicker::make('cancelled_at')
                    ->label('Cancelled At'),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->columnSpanFull(),
                Textarea::make('cancellation_reason')
                    ->label('Cancellation Reason')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vehicle.registration_number')
                    ->label('Vehicle')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vendor.name')
                    ->label('Vendor (Rider)')
                    ->searchable()
                    ->default('—')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'assigned' => 'info',
                        'in_progress' => 'primary',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pending',
                        'assigned' => 'Assigned',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('pickup_address')
                    ->label('Pickup Address')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->pickup_address)
                    ->searchable(),
                TextColumn::make('pickup_date')
                    ->label('Pickup Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cash_on_delivery' => 'success',
                        'khalti' => 'info',
                        'esewa' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash_on_delivery' => 'Cash on Delivery',
                        'khalti' => 'Khalti',
                        'esewa' => 'eSewa',
                        default => $state,
                    }),
                TextColumn::make('assigned_at')
                    ->label('Assigned At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'assigned' => 'Assigned',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRenewalRequests::route('/'),
            'create' => Pages\CreateRenewalRequest::route('/create'),
            'view' => Pages\ViewRenewalRequest::route('/{record}'),
            'edit' => Pages\EditRenewalRequest::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
}

