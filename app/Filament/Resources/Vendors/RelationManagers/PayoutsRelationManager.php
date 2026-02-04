<?php

namespace App\Filament\Resources\Vendors\RelationManagers;

use App\Models\RenewalRequest;
use App\Models\Vendor;
use App\Models\VendorPayout;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
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
                    ->money('NPR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'processing' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('month')
                    ->label('Month')
                    ->formatStateUsing(fn (int $state): string => date('F', mktime(0, 0, 0, $state, 1))),
                TextColumn::make('year')
                    ->label('Year'),
                TextColumn::make('currency'),
                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('notes')
                    ->limit(40)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
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
                    ->visible(fn (): bool => $this->getPendingPayoutAmount($this->getOwnerRecord()) > 0),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark payout as paid')
                    ->modalDescription(fn (VendorPayout $record): string => 'Mark payout of NPR ' . number_format((float) $record->amount, 2) . ' as paid?')
                    ->action(function (VendorPayout $record): void {
                        if ($record->status === 'paid') {
                            \Filament\Notifications\Notification::make()
                                ->title('Already paid')
                                ->warning()
                                ->send();
                            return;
                        }
                        $record->status = 'paid';
                        $record->paid_at = now();
                        $record->save();
                        \Filament\Notifications\Notification::make()
                            ->title('Payout marked as paid')
                            ->success()
                            ->send();
                        $this->resetTable();
                    })
                    ->visible(fn (VendorPayout $record): bool => $record->status !== 'paid'),
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
