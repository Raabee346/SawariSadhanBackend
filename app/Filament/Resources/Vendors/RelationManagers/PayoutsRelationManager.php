<?php

namespace App\Filament\Resources\Vendors\RelationManagers;

use App\Models\VendorPayout;
use App\Models\RenewalRequest;
use App\Services\KhaltiPaymentService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'payouts';

    protected static ?string $title = 'Payouts';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('amount')
                    ->money('NPR')
                    ->label('Amount')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'processing' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('month')
                    ->label('Month'),
                TextColumn::make('year')
                    ->label('Year'),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('payoutWithKhalti')
                    ->label('Payout with Khalti (Sandbox)')
                    ->icon('heroicon-o-banknotes')
                    ->requiresConfirmation()
                    ->action(function () {
                        try {
                            /** @var \App\Models\Vendor $vendor */
                            $vendor = $this->getOwnerRecord();

                            if (!$vendor) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Error')
                                    ->body('Vendor record not found.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            // Compute pending amount: completed * 250 - already paid
                            $completedCount = RenewalRequest::where('vendor_id', $vendor->id)
                                ->where('status', 'completed')
                                ->count();

                            $perRequest = 250.0;
                            $totalEarned = $completedCount * $perRequest;

                            $totalPaid = VendorPayout::where('vendor_id', $vendor->id)
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

                            $transactionId = 'VENDOR_PAYOUT_' . $vendor->id . '_' . $now->timestamp;
                            $productName = 'Vendor Payout - ' . $vendor->name;

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

                            try {
                                // Create payout record in processing state with Khalti pidx
                                VendorPayout::create([
                                    'vendor_id' => $vendor->id,
                                    'amount' => $pending,
                                    'status' => 'processing',
                                    'month' => (int) $now->format('n'),
                                    'year' => (int) $now->format('Y'),
                                    'currency' => 'NPR',
                                    'khalti_pidx' => $result['pidx'] ?? null,
                                    'khalti_payload' => $result['data'] ?? null,
                                    'notes' => 'Payout initialized from Filament vendor detail page with Khalti sandbox.',
                                ]);

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
                                    ->title('Error creating payout record')
                                    ->body('Payment URL generated but failed to save payout record: ' . $e->getMessage())
                                    ->danger()
                                    ->send();
                                \Illuminate\Support\Facades\Log::error('Failed to create VendorPayout', [
                                    'vendor_id' => $vendor->id,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ]);
                            }
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Unexpected error')
                                ->body('An error occurred: ' . $e->getMessage())
                                ->danger()
                                ->send();
                            \Illuminate\Support\Facades\Log::error('Payout initiation error', [
                                'vendor_id' => $vendor->id ?? null,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }),
            ])
            ->actions([
                Action::make('markPaid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (VendorPayout $record) => $record->status !== 'paid')
                    ->requiresConfirmation()
                    ->action(function (VendorPayout $record) {
                        $record->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                        ]);

                        $this->notify('success', 'Payout marked as paid.');
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

