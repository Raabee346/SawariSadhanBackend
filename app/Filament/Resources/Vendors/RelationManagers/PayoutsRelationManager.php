<?php

namespace App\Filament\Resources\Vendors\RelationManagers;

use App\Models\RenewalRequest;
use App\Models\Vendor;
use App\Models\VendorPayout;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;

class PayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'payouts';

    protected static ?string $title = 'Payouts';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedBanknotes;

    private const PER_REQUEST_EARNING = 250.0;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('NPR')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'processing' => 'warning',
                        'pending' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('paid_at')
                    ->label('Paid Date')
                    ->date('Y-m-d')
                    ->sortable()
                    ->placeholder('Not paid yet')
                    ->color(fn ($state): string => $state ? 'success' : 'gray'),
                TextColumn::make('created_at')
                    ->label('Created Date')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('month')
                    ->label('Month')
                    ->formatStateUsing(fn (?int $state): string => $state ? date('F', mktime(0, 0, 0, $state, 1)) : '—'),
                TextColumn::make('year')
                    ->label('Year'),
                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->headerActions([
                Action::make('createPayout')
                    ->label('Create Payout')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Create Vendor Payout')
                    ->modalDescription(function (): string {
                        $vendor = $this->getOwnerRecord();
                        $pending = $this->getPendingPayoutAmount($vendor);
                        return 'Create a payout record of NPR ' . number_format($pending, 2) . ' for ' . $vendor->name . '? This will create a pending payout record. Process the payment externally (bank transfer, Khalti transfer, cash, etc.) and then mark it as paid.';
                    })
                    ->action(function (): void {
                        $vendor = $this->getOwnerRecord();
                        $pending = $this->getPendingPayoutAmount($vendor);

                        if ($pending <= 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('No pending payout')
                                ->warning()
                                ->send();
                            return;
                        }

                        $now = now();
                        VendorPayout::create([
                            'vendor_id' => $vendor->id,
                            'amount' => $pending,
                            'status' => 'pending',
                            'month' => (int) $now->format('n'),
                            'year' => (int) $now->format('Y'),
                            'currency' => 'NPR',
                            'notes' => 'Payout created by admin. Process payment externally (bank transfer, Khalti transfer, cash, etc.) and mark as paid when completed.',
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Payout created')
                            ->body('Payout record created with status "pending". Process the payment externally and use "Mark as Paid" action when done.')
                            ->success()
                            ->send();

                        $this->resetTable();
                    })
                    ->visible(function (): bool {
                        $vendor = $this->getOwnerRecord();
                        $pending = $this->getPendingPayoutAmount($vendor);
                        
                        // Hide if no pending amount OR if there's already a pending/processing payout
                        if ($pending <= 0) {
                            return false;
                        }
                        
                        // Check if there's already a pending or processing payout
                        $hasPendingPayout = VendorPayout::where('vendor_id', $vendor->id)
                            ->whereIn('status', ['pending', 'processing'])
                            ->exists();
                        
                        return !$hasPendingPayout;
                    }),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark payout as paid')
                    ->modalDescription(fn (VendorPayout $record): string => 'Mark payout of NPR ' . number_format((float) $record->amount, 2) . ' as paid? This will update the status to "paid" and set the paid date.')
                    ->action(function (VendorPayout $record): void {
                        if ($record->status === 'paid') {
                            \Filament\Notifications\Notification::make()
                                ->title('Already paid')
                                ->body('This payout is already marked as paid on ' . ($record->paid_at ? $record->paid_at->format('Y-m-d H:i') : 'N/A'))
                                ->warning()
                                ->send();
                            return;
                        }
                        $record->status = 'paid';
                        $record->paid_at = now();
                        $record->save();
                        \Filament\Notifications\Notification::make()
                            ->title('Payout marked as paid')
                            ->body('Payout of NPR ' . number_format((float) $record->amount, 2) . ' has been marked as paid.')
                            ->success()
                            ->send();
                        $this->resetTable();
                    })
                    ->visible(fn (VendorPayout $record): bool => $record->status !== 'paid'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'paid' => 'Paid',
                    ])
                    ->label('Filter by Status'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No payouts yet')
            ->emptyStateDescription('Create a payout record and process payment externally (bank transfer, Khalti transfer, cash, etc.), then mark it as paid.');
    }

    private function getPendingPayoutAmount(Vendor $vendor): float
    {
        $completedCount = RenewalRequest::where('vendor_id', $vendor->id)
            ->where('status', 'completed')
            ->count();
        $totalEarned = $completedCount * self::PER_REQUEST_EARNING;
        $totalPaid = (float) VendorPayout::where('vendor_id', $vendor->id)
            ->where('status', 'paid')
            ->sum('amount');
        return max(0, $totalEarned - $totalPaid);
    }
}
