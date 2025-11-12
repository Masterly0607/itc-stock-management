<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Branch;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $me = auth()->user();

        if ($me?->hasRole('Admin')) {
            // Admin can only keep user within same province & non-HQ.
            // Since the field is disabled, this is just a safety net.
            $allowed = Branch::query()
                ->where('id', $data['branch_id'] ?? $this->record->branch_id)
                ->where('province_id', $me->branch?->province_id)
                ->where('type', '!=', 'HQ')
                ->where('is_active', true)
                ->exists();

            if (! $allowed) {
                // Force back to admin’s own branch if someone tampers
                $data['branch_id'] = $me->branch_id;
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var \App\Models\User $user */
        $user = $this->record;

        $role = $this->form->getState()['role'] ?? null;
        if ($role) {
            $user->syncRoles([$role]);
        }
    }
}
