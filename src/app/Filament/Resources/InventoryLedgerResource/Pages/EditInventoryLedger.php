<?php

namespace App\Filament\Resources\InventoryLedgerResource\Pages;

use App\Filament\Resources\InventoryLedgerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInventoryLedger extends EditRecord
{
    protected static string $resource = InventoryLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
