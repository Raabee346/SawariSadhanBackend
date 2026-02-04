<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorPayoutResource\Pages;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\RenewalRequest;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Services\KhaltiPaymentService;
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
                Action::make('payWithKhalti')
                    ->label('Pay with Khalti')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Initiate Payout with Khalti')
                    ->modalDescription(function (Vendor $record): string {
                        $c = RenewalRequest::where('vendor_id', $record->id)->where('status', 'completed')->count();
                        $earned = $c * 250.0;
                        $paid = (float) VendorPayout::where('vendor_id', $record->id)->where('status', 'paid')->sum('amount');
                        $pending = max(0, $earned - $paid);
                        return 'Initiate payment of NPR ' . number_format($pending, 2) . ' to ' . $record->name . '?';
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

                        $khalti = app(KhaltiPaymentService::class);
                        $now = now();
                        $transactionId = 'VENDOR_PAYOUT_' . $record->id . '_' . $now->timestamp;
                        $productName = 'Vendor Payout - ' . $record->name;

                        $result = $khalti->initiatePayment($pending, $transactionId, $productName, []);

                        if (!($result['success'] ?? false) || empty($result['payment_url'])) {
                            \Filament\Notifications\Notification::make()
                                ->title('Payment initiation failed')
                                ->body($result['message'] ?? 'Failed to initialize Khalti payout.')
                                ->danger()
                                ->send();
                            return;
                        }

                        VendorPayout::create([
                            'vendor_id' => $record->id,
                            'amount' => $pending,
                            'status' => 'processing',
                            'month' => (int) $now->format('n'),
                            'year' => (int) $now->format('Y'),
                            'currency' => 'NPR',
                            'khalti_pidx' => $result['pidx'] ?? null,
                            'khalti_payload' => $result['data'] ?? null,
                            'notes' => 'Payout from admin Vendor Payouts list (Khalti sandbox).',
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Payment initiated')
                            ->body('Click the button to open Khalti payment page.')
                            ->success()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('openKhalti')
                                    ->label('Open Khalti Payment')
                                    ->url($result['payment_url'])
                                    ->openUrlInNewTab()
                                    ->button()
                                    ->color('primary'),
                            ])
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
