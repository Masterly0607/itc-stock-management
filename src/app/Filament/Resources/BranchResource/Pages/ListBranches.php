<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Resources\BranchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
// use Filament\Support\Enums\Alignment; 

class ListBranches extends ListRecords
{
    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        $create = Actions\CreateAction::make()
            ->modalHeading('Create Branch')
            // OPTIONAL: align the modal footer buttons (Filament v3)
            // ->modalFooterActionsAlignment(Alignment::Start)
            // If you were conditionally disabling the form, keep this:
            ->disabledForm(fn($livewire) => data_get($livewire, 'data.type') === 'HQ');

        return [$create];
    }
}
