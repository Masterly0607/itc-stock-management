<?php

namespace App\Filament\Resources\StockCountResource\Pages;

use App\Filament\Resources\StockCountResource;
use Filament\Resources\Pages\EditRecord;

class EditStockCount extends EditRecord
{
    protected static string $resource = StockCountResource::class;

    protected function getHeaderActions(): array
    {
        return []; // use table row actions for Post
    }
}
