<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VehicleResource\Pages;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\FCMNotificationService;
use UnitEnum;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static UnitEnum|string|null $navigationGroup = 'Vehicle Management';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('province_id')
                    ->relationship('province', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('owner_name')
                    ->label('Owner Name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Enter the bluebook owner name (can be different from logged-in user)'),
                TextInput::make('registration_number')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('chassis_number')
                    ->label('Chassis Number')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
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
                TextInput::make('brand')
                    ->maxLength(255),
                TextInput::make('model')
                    ->maxLength(255),
                TextInput::make('engine_capacity')
                    ->numeric()
                    ->required()
                    ->label('Engine Capacity (CC/Watts)'),
                TextInput::make('manufacturing_year')
                    ->numeric()
                    ->minValue(1900)
                    ->maxValue(now()->year + 1),
                TextInput::make('registration_date')
                    ->required()
                    ->label('Registration Date (BS)')
                    ->placeholder('YYYY-MM-DD (e.g., 2080-05-15)')
                    ->helperText('Enter date in Bikram Sambat format'),
                TextInput::make('last_renewed_date')
                    ->label('Last Renewed Date (BS)')
                    ->placeholder('YYYY-MM-DD (e.g., 2081-05-15)')
                    ->helperText('Enter date in Bikram Sambat format (YYYY-MM-DD). Expiry date will be auto-calculated below.')
                    ->live() // Makes the field reactive
                    ->afterStateUpdated(function ($state, $set, $get) {
                        // Auto-calculate expiry_date when last_renewed_date changes
                        if ($state && !empty(trim($state))) {
                            $trimmedState = trim($state);
                            
                            // Validate format first
                            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmedState)) {
                                // Invalid format, clear expiry_date
                                $set('expiry_date', null);
                                return;
                            }
                            
                            try {
                                $nepaliDate = new \App\Services\NepaliDate();
                                $lastRenewedAD = $nepaliDate->convertBsToAd($trimmedState);
                                
                                if ($lastRenewedAD) {
                                    $expiryDate = \Carbon\Carbon::createFromFormat('Y-m-d', $lastRenewedAD)
                                        ->addYear()
                                        ->format('Y-m-d');
                                    $set('expiry_date', $expiryDate);
                                } else {
                                    $set('expiry_date', null);
                                }
                            } catch (\Exception $e) {
                                // If conversion fails, clear expiry_date
                                $set('expiry_date', null);
                                \Log::warning('Failed to calculate expiry date in Filament form', [
                                    'last_renewed_date' => $trimmedState,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        } else {
                            // Clear expiry_date if last_renewed_date is empty
                            $set('expiry_date', null);
                        }
                    })
                    ->rules([
                        'nullable',
                        'regex:/^\d{4}-\d{2}-\d{2}$/',
                    ])
                    ->validationMessages([
                        'regex' => 'Date must be in format YYYY-MM-DD (e.g., 2081-05-15)',
                    ]),
                TextInput::make('expiry_date')
                    ->label('Expiry Date (AD)')
                    ->placeholder('Auto-calculated (e.g., 2027-01-03)')
                    ->helperText('✅ Automatically calculated from Last Renewed Date + 1 year. Stored in AD format (Gregorian calendar).')
                    ->disabled()
                    ->dehydrated() // Important: ensures the value is saved even though disabled
                    ->visible(fn ($get) => !empty($get('last_renewed_date'))), // Only show when last_renewed_date has a value
                Select::make('verification_status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending')
                    ->required()
                    ->label('Verification Status')
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        // Clear rejection reason if status is not rejected
                        if ($state !== 'rejected') {
                            $set('rejection_reason', null);
                        }
                    }),
                Textarea::make('rejection_reason')
                    ->label('Rejection Reason')
                    ->rows(3)
                    ->placeholder('Enter the reason for rejection')
                    ->helperText('This reason will be sent to the user in the notification')
                    ->required(fn (Get $get) => $get('verification_status') === 'rejected')
                    ->visible(fn (Get $get) => $get('verification_status') === 'rejected'),
                Toggle::make('is_commercial')
                    ->default(false),
                
                // Document Upload Section
                FileUpload::make('rc_firstpage')
                    ->label('RC First Page')
                    ->image()
                    ->directory('vehicles/documents')
                    ->disk('public')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'])
                    ->helperText('Upload RC first page document (Image or PDF)'),
                
                FileUpload::make('rc_ownerdetails')
                    ->label('RC Owner Details')
                    ->image()
                    ->directory('vehicles/documents')
                    ->disk('public')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'])
                    ->helperText('Upload RC owner details page (Image or PDF)'),
                
                FileUpload::make('rc_vehicledetails')
                    ->label('RC Vehicle Details')
                    ->image()
                    ->directory('vehicles/documents')
                    ->disk('public')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'])
                    ->helperText('Upload RC vehicle details page (Image or PDF)'),
                
                FileUpload::make('lastrenewdate')
                    ->label('Last Renewal Date Document')
                    ->image()
                    ->directory('vehicles/documents')
                    ->disk('public')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'])
                    ->helperText('Upload last renewal date document (Image or PDF)'),
                
                FileUpload::make('insurance')
                    ->label('Insurance Document')
                    ->image()
                    ->directory('vehicles/documents')
                    ->disk('public')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'])
                    ->helperText('Upload insurance document (Image or PDF)'),
                
                FileUpload::make('owner_ctznship_front')
                    ->label('Owner Citizenship (Front)')
                    ->image()
                    ->directory('vehicles/documents')
                    ->disk('public')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'])
                    ->helperText('Upload owner citizenship front side (Image or PDF)'),
                
                FileUpload::make('owner_ctznship_back')
                    ->label('Owner Citizenship (Back)')
                    ->image()
                    ->directory('vehicles/documents')
                    ->disk('public')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'])
                    ->helperText('Upload owner citizenship back side (Image or PDF). Leave empty if both sides are in single photo.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Added By (User)')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user'),
                TextColumn::make('owner_name')
                    ->label('Owner Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('registration_number')
                    ->searchable()
                    ->sortable(),
                // TextColumn::make('chassis_number')
                //     ->label('Chassis Number')
                //     ->searchable()
                //     ->sortable(),
                TextColumn::make('province.name')
                    ->label('Province')
                    ->badge(),
                TextColumn::make('vehicle_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        '2W' => 'success',
                        '4W' => 'info',
                        'Commercial' => 'warning',
                        'Heavy' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('fuel_type')
                    ->badge(),
                TextColumn::make('verification_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('last_renewed_date')
                    ->label('Last Renewed (BS)')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expiry_date')
                    ->label('Expiry Date (AD)')
                    ->date('Y-m-d')
                    ->badge()
                    ->color(function ($state) {
                        if (!$state) return 'gray';
                        $expiryDate = \Carbon\Carbon::parse($state);
                        $today = \Carbon\Carbon::today();
                        if ($expiryDate->isPast() || $expiryDate->isToday()) {
                            return 'danger'; // Expired
                        } elseif ($expiryDate->diffInDays($today) <= 30) {
                            return 'warning'; // Expiring soon (within 30 days)
                        }
                        return 'success'; // Valid
                    })
                    ->sortable()
                    ->searchable(),
                // TextColumn::make('registration_date')
                //     ->label('Reg. Date (BS)')
                //     ->formatStateUsing(fn ($state) => $state ? \App\Services\NepalDateService::toBS(\Carbon\Carbon::parse($state)) : '-')
                //     ->sortable(),
                TextColumn::make('verifiedBy.name')
                    ->label('Verified By')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('verified_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Vehicle $record) {
                        $record->update([
                            'verification_status' => 'approved',
                            'verified_by' => Auth::id(),
                            'verified_at' => now(),
                            'rejection_reason' => null,
                        ]);
                        
                        // Refresh the record to ensure we have the latest data
                        $record->refresh();
                        $record->load('user'); // Ensure user relationship is loaded
                        
                        // Clear vehicle cache to ensure fresh data on next API call
                        \Illuminate\Support\Facades\Cache::forget('vehicle_' . $record->id);
                        
                        // Send notification to user and refresh vehicle list
                        try {
                            $fcmService = app(FCMNotificationService::class);
                            $fcmService->notifyVehicleVerification($record, 'approved');
                            Log::info('Vehicle approved notification sent', [
                                'vehicle_id' => $record->id,
                                'user_id' => $record->user_id,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Failed to send vehicle approval notification', [
                                'vehicle_id' => $record->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    })
                    ->visible(fn (Vehicle $record) => $record->verification_status === 'pending'),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Vehicle $record, array $data) {
                        $record->update([
                            'verification_status' => 'rejected',
                            'verified_by' => Auth::id(),
                            'verified_at' => now(),
                            'rejection_reason' => $data['rejection_reason'],
                        ]);
                        
                        // Refresh the record to ensure we have the latest data including rejection_reason
                        $record->refresh();
                        $record->load('user'); // Ensure user relationship is loaded
                        
                        // Send notification to user
                        try {
                            $fcmService = app(FCMNotificationService::class);
                            $fcmService->notifyVehicleVerification($record, 'rejected');
                            Log::info('Vehicle rejection notification sent', [
                                'vehicle_id' => $record->id,
                                'user_id' => $record->user_id,
                                'rejection_reason' => $record->rejection_reason,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Failed to send vehicle rejection notification', [
                                'vehicle_id' => $record->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    })
                    ->visible(fn (Vehicle $record) => $record->verification_status === 'pending'),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicles::route('/'),
            'create' => Pages\CreateVehicle::route('/create'),
            'view' => Pages\ViewVehicle::route('/{record}'),
            'edit' => Pages\EditVehicle::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'province', 'verifiedBy']);
    }
}

