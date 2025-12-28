<?php

namespace App\Filament\Resources\Vendors\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\Vendor;

class VendorForm
{
    /**
     * Generate a unique vendor ID in format: SS-VENDOR-XXXXXXXXX
     * Checks for uniqueness within vendors table only
     */
    private static function generateUniqueVendorId(): string
    {
        do {
            $uniqueId = 'SS-VENDOR-' . strtoupper(uniqid());
        } while (Vendor::where('unique_id', $uniqueId)->exists());

        return $uniqueId;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('unique_id')
                    ->label('Unique ID')
                    ->required()
                    ->default(fn () => self::generateUniqueVendorId())
                    ->helperText('Auto-generated unique ID in format SS-VENDOR-XXXXXXXXX. Must be unique among vendors. Clear the field and it will regenerate, or edit manually.')
                    ->suffixIcon('heroicon-o-sparkles')
                    ->unique(Vendor::class, 'unique_id', ignoreRecord: true),
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                // email_verified_at is automatically set when creating from admin panel
                TextInput::make('password')
                    ->password()
                    ->required()
                    ->minLength(6),
            ]);
    }
}
