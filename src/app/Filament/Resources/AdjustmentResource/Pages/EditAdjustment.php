<?php

namespace App\Filament\Resources\AdjustmentResource\Pages;

use App\Filament\Resources\AdjustmentResource;
use Filament\Resources\Pages\EditRecord;

class EditAdjustment extends EditRecord
{
    protected static string $resource = AdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return []; // use table row Post action
    }
}
