<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\Vendors\VendorResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Vendor;

class CreateVendor extends CreateRecord
{
    protected static string $resource = VendorResource::class;

    /**
     * Generate a unique vendor ID in format: SS-VENDOR-XXXXXXXXX
     * Checks for uniqueness within vendors table only
     */
    private function generateUniqueVendorId(): string
    {
        do {
            $uniqueId = 'SS-VENDOR-' . strtoupper(uniqid());
        } while (Vendor::where('unique_id', $uniqueId)->exists());

        return $uniqueId;
    }

    /**
     * Automatically set email_verified_at and ensure unique_id is set when creating vendor from admin panel
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set email as verified by default when admin creates vendor account
        $data['email_verified_at'] = now();

        // Ensure unique_id is set and unique within vendors table
        // Generate if not provided or if it already exists
        if (empty($data['unique_id']) || Vendor::where('unique_id', $data['unique_id'])->exists()) {
            $data['unique_id'] = $this->generateUniqueVendorId();
        }

        return $data;
    }
}
