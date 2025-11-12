<?php

namespace App\Filament\Resources\InventoryLedgerResource\Pages;

use App\Filament\Resources\InventoryLedgerResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryLedgers extends ListRecords
{
    protected static string $resource = InventoryLedgerResource::class;

    protected function getHeaderActions(): array
    {
        // No create button for ledger
        return [];
    }
}
