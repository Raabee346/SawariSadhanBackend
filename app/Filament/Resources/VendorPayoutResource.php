<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorPayoutResource\Pages;
use App\Filament\Resources\Vendors\VendorResource;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Models\RenewalRequest;
use App\Services\KhaltiPaymentService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\Action;
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
            // Only show vendors whose pending payout > 0
            ->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query) {
                $perRequest = 250.0;

                $query->whereRaw("
                    (
                        (SELECT COUNT(*) 
                         FROM renewal_requests 
                         WHERE renewal_requests.vendor_id = vendors.id 
                           AND renewal_requests.status = 'completed') * ?
                    ) >
                    (
                        SELECT COALESCE(SUM(amount), 0) 
                        FROM vendor_payouts 
                        WHERE vendor_payouts.vendor_id = vendors.id 
                          AND vendor_payouts.status = 'paid'
                    )
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
            ->actions([
                Action::make('payWithKhalti')
                    ->label('Pay with Khalti')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Initiate Payout with Khalti')
                    ->modalDescription(function (Vendor $record): string {
                        $completed = RenewalRequest::where('vendor_id', $record->id)
                            ->where('status', 'completed')
                            ->count();
                        $totalEarned = $completed * 250.0;
                        $totalPaid = (float) VendorPayout::where('vendor_id', $record->id)
                            ->where('status', 'paid')
                            ->sum('amount');
                        $pending = max(0, $totalEarned - $totalPaid);
                        return "Initiate payment of NPR " . number_format($pending, 2) . " to {$record->name}?";
                    })
                    ->action(function (Vendor $record) {
                        try {
                            if (!$record || !$record->id) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Error')
                                    ->body('Invalid vendor record.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            // Compute pending amount: completed * 250 - already paid
                            $completedCount = RenewalRequest::where('vendor_id', $record->id)
                                ->where('status', 'completed')
                                ->count();

                            $perRequest = 250.0;
                            $totalEarned = $completedCount * $perRequest;

                            $totalPaid = VendorPayout::where('vendor_id', $record->id)
                                ->where('status', 'paid')
                                ->sum('amount');

                            $pending = max(0, $totalEarned - $totalPaid);

                            if ($pending <= 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('No pending payout')
                                    ->body('This vendor has no pending payout amount.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $now = now();

                            // Initialize Khalti payment in sandbox
                            /** @var KhaltiPaymentService $khalti */
                            $khalti = app(KhaltiPaymentService::class);

                            $transactionId = 'VENDOR_PAYOUT_' . $record->id . '_' . $now->timestamp;
                            $productName = 'Vendor Payout - ' . $record->name;

                            $result = $khalti->initiatePayment(
                                $pending,
                                $transactionId,
                                $productName,
                                []
                            );

                            if (!($result['success'] ?? false) || empty($result['payment_url'])) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Payment initiation failed')
                                    ->body($result['message'] ?? 'Failed to initialize Khalti payout.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            // Create payout record in processing state with Khalti pidx
                            try {
                                VendorPayout::create([
                                    'vendor_id' => $record->id,
                                    'amount' => $pending,
                                    'status' => 'processing',
                                    'month' => (int) $now->format('n'),
                                    'year' => (int) $now->format('Y'),
                                    'currency' => 'NPR',
                                    'khalti_pidx' => $result['pidx'] ?? null,
                                    'khalti_payload' => $result['data'] ?? null,
                                    'notes' => 'Payout initialized from Vendor Payouts list with Khalti sandbox.',
                                ]);
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Error creating payout record')
                                    ->body('Payment URL generated but failed to save payout record: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                                \Illuminate\Support\Facades\Log::error('Failed to create VendorPayout', [
                                    'vendor_id' => $record->id,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ]);
                                return;
                            }

                            // Show success notification with link to open Khalti payment
                            \Filament\Notifications\Notification::make()
                                ->title('Payment initiated successfully')
                                ->body('Click the button below to complete the payment with Khalti.')
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
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Unexpected error')
                                ->body('An error occurred: ' . $e->getMessage())
                                ->danger()
                                ->send();
                            \Illuminate\Support\Facades\Log::error('Payout initiation error', [
                                'vendor_id' => $record->id ?? null,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }),
            ])
            // Clicking a row opens the full Vendor view with payout relation/history
            ->recordUrl(fn (Vendor $record) => VendorResource::getUrl('view', ['record' => $record]))
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

