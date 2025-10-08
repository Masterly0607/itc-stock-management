<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Branch;
use App\Models\District;
use App\Models\Province;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $role       = $data['role'] ?? null;
        $provinceId = $data['province_id'] ?? null;
        $districtId = $data['district_id'] ?? null;

        if ($role === 'Admin') {
            $branch = Branch::firstOrCreate(
                ['province_id' => $provinceId, 'district_id' => null],
                [
                    'code' => 'BR-' . strtoupper(str_replace(' ', '', optional(Province::find($provinceId))->name ?? 'UNKNOWN')),
                    'name' => 'Branch - ' . (optional(Province::find($provinceId))->name ?? 'Unknown'),
                ]
            );
            $data['branch_id'] = $branch->id;
        } elseif ($role === 'Distributor') {
            $branch = Branch::firstOrCreate(
                ['province_id' => $provinceId, 'district_id' => $districtId],
                [
                    'code' => 'BR-' . strtoupper(str_replace(' ', '', optional(District::find($districtId))->name ?? 'UNKNOWN')),
                    'name' => 'Branch - ' . (optional(District::find($districtId))->name ?? 'Unknown'),
                ]
            );
            $data['branch_id'] = $branch->id;
        }

        // these are just UI helpers; don't persist to users table
        unset($data['province_id'], $data['district_id'], $data['role']);

        return $data;
    }




    protected function afterCreate(): void
    {
        // assign the selected role
        $role = $this->form->getState()['role'] ?? null;
        if ($role) {
            $this->record->syncRoles([$role]);
        }
    }
}
