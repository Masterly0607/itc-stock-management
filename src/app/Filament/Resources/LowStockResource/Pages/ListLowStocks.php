<?php

namespace App\Filament\Resources\LowStockResource\Pages;

use App\Filament\Resources\LowStockResource;
use Filament\Resources\Pages\ListRecords;

class ListLowStocks extends ListRecords
{
    protected static string $resource = LowStockResource::class;

    protected function getHeaderActions(): array
    {
        // We’re controlling export via the table header action in the Resource itself.
        return [];
    }
}
