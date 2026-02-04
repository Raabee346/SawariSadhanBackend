<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorPayoutResource\Pages;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Models\RenewalRequest;
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
    // Use Vendor as the base model so we can show one row per vendor
    protected static ?string $model = Vendor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static UnitEnum|string|null $navigationGroup = 'Vehicle Management';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        // This resource is read-only summary in the navigation,
        // so we don't expose a form here.
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Vendor')
                    ->searchable(),
                TextColumn::make('completed_requests')
                    ->label('Completed Tasks')
                    ->state(function (Vendor $record): int {
                        return RenewalRequest::where('vendor_id', $record->id)
                            ->where('status', 'completed')
                            ->count();
                    })
                    ->sortable(),
                TextColumn::make('total_earned')
                    ->label('Total Earned')
                    ->money('NPR')
                    ->state(function (Vendor $record): float {
                        $completed = RenewalRequest::where('vendor_id', $record->id)
                            ->where('status', 'completed')
                            ->count();
                        return $completed * 250.0;
                    })
                    ->sortable(),
                TextColumn::make('total_paid')
                    ->label('Total Paid')
                    ->money('NPR')
                    ->state(function (Vendor $record): float {
                        return (float) VendorPayout::where('vendor_id', $record->id)
                            ->where('status', 'paid')
                            ->sum('amount');
                    })
                    ->sortable(),
                TextColumn::make('payout_pending')
                    ->label('Pending Payout')
                    ->money('NPR')
                    ->state(function (Vendor $record): float {
                        $completed = RenewalRequest::where('vendor_id', $record->id)
                            ->where('status', 'completed')
                            ->count();
                        $totalEarned = $completed * 250.0;
                        $totalPaid = (float) VendorPayout::where('vendor_id', $record->id)
                            ->where('status', 'paid')
                            ->sum('amount');
                        return max(0, $totalEarned - $totalPaid);
                    })
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorPayouts::route('/'),
        ];
    }
}

