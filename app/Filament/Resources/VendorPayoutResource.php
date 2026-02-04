<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorPayoutResource\Pages;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\RenewalRequest;
use App\Models\Vendor;
use App\Models\VendorPayout;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class VendorPayoutResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static ?string $navigationLabel = 'Vendor Payouts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static UnitEnum|string|null $navigationGroup = 'Vehicle Management';

    protected static ?int $navigationSort = 8;

    public static function getModelLabel(): string
    {
        return 'Vendor Payout';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Vendor Payouts';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        $perRequest = 250.0;

        return $table
            ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) use ($perRequest) {
                $query->whereRaw("
                    (
                        SELECT COUNT(*) FROM renewal_requests
                        WHERE renewal_requests.vendor_id = vendors.id
                          AND renewal_requests.status = 'completed'
                    ) * ? > COALESCE((
                        SELECT SUM(amount) FROM vendor_payouts
                        WHERE vendor_payouts.vendor_id = vendors.id
                          AND vendor_payouts.status = 'paid'
                    ), 0)
                ", [$perRequest]);
            })
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
                    }),
                TextColumn::make('total_earned')
                    ->label('Total Earned')
                    ->money('NPR')
                    ->state(function (Vendor $record): float {
                        $c = RenewalRequest::where('vendor_id', $record->id)->where('status', 'completed')->count();
                        return $c * 250.0;
                    }),
                TextColumn::make('total_paid')
                    ->label('Total Paid')
                    ->money('NPR')
                    ->state(function (Vendor $record): float {
                        return (float) VendorPayout::where('vendor_id', $record->id)
                            ->where('status', 'paid')
                            ->sum('amount');
                    }),
                TextColumn::make('payout_pending')
                    ->label('Pending Payout')
                    ->money('NPR')
                    ->state(function (Vendor $record): float {
                        $c = RenewalRequest::where('vendor_id', $record->id)->where('status', 'completed')->count();
                        $earned = $c * 250.0;
                        $paid = (float) VendorPayout::where('vendor_id', $record->id)->where('status', 'paid')->sum('amount');
                        return max(0, $earned - $paid);
                    }),
            ])
            ->recordActions([
                Action::make('createPayout')
                    ->label('Create Payout')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Create Vendor Payout')
                    ->modalDescription(function (Vendor $record): string {
                        $c = RenewalRequest::where('vendor_id', $record->id)->where('status', 'completed')->count();
                        $earned = $c * 250.0;
                        $paid = (float) VendorPayout::where('vendor_id', $record->id)->where('status', 'paid')->sum('amount');
                        $pending = max(0, $earned - $paid);
                        return 'Create a payout record of NPR ' . number_format($pending, 2) . ' for ' . $record->name . '? This will create a pending payout record. Process the payment externally (bank transfer, Khalti transfer, cash, etc.) and then mark it as paid.';
                    })
                    ->action(function (Vendor $record) {
                        $completedCount = RenewalRequest::where('vendor_id', $record->id)->where('status', 'completed')->count();
                        $totalEarned = $completedCount * 250.0;
                        $totalPaid = (float) VendorPayout::where('vendor_id', $record->id)->where('status', 'paid')->sum('amount');
                        $pending = max(0, $totalEarned - $totalPaid);

                        if ($pending <= 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('No pending payout')
                                ->warning()
                                ->send();
                            return;
                        }

                        $now = now();
                        VendorPayout::create([
                            'vendor_id' => $record->id,
                            'amount' => $pending,
                            'status' => 'pending',
                            'month' => (int) $now->format('n'),
                            'year' => (int) $now->format('Y'),
                            'currency' => 'NPR',
                            'notes' => 'Payout created by admin from Vendor Payouts list. Process payment externally (bank transfer, Khalti transfer, cash, etc.) and mark as paid when completed.',
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Payout created')
                            ->body('Payout record created with status "pending". Process the payment externally and use "Mark as Paid" action when done.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordUrl(fn (Vendor $record) => VendorResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('No vendors with pending payouts')
            ->emptyStateDescription('Vendors with pending payouts will appear here.')
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorPayouts::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
