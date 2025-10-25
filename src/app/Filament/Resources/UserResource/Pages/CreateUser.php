<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $me = auth()->user();
        if ($me?->hasRole('Admin')) {
            $data['branch_id'] = $me->branch_id; // force admin's own branch
        }
        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var \App\Models\User $user */
        $user = $this->record;

        // ✅ Read the virtual 'role' from $this->data
        $role = data_get($this->data, 'role');
        if ($role) {
            $user->syncRoles([$role]);    // role name (e.g., 'Admin', 'Distributor')
        }

        // refresh relation so the list shows it immediately
        $user->load('roles');
    }
}
