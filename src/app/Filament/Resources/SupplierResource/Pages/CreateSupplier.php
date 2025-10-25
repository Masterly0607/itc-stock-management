<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['code'] ?? null)) {
            $next = \App\Models\Supplier::max('id') + 1;
            $data['code'] = 'SUP-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        }
        return $data;
    }
}
