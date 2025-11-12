<?php

namespace App\Filament\Resources\LowStockResource\Pages;

use App\Filament\Resources\LowStockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLowStock extends EditRecord
{
    protected static string $resource = LowStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
