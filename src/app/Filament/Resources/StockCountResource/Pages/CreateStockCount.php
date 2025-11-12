<?php

namespace App\Filament\Resources\StockCountResource\Pages;

use App\Filament\Resources\StockCountResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateStockCount extends CreateRecord
{
    protected static string $resource = StockCountResource::class;
}
