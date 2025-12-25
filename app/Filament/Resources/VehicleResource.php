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
                    ->helperText('Enter date in Bikram Sambat format'),
                Select::make('verification_status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending')
                    ->required()
                    ->label('Verification Status'),
                Textarea::make('rejection_reason')
                    ->rows(3)
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

