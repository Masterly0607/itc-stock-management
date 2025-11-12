<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Resources\BranchResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateBranch extends CreateRecord
{
    protected static string $resource = BranchResource::class;
    // app/Filament/Resources/BranchResource/Pages/CreateBranch.php
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Guard: Exactly one HQ
        if (($data['type'] ?? null) === 'HQ') {
            if (DB::table('branches')->where('type', 'HQ')->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'type' => 'There can be only one HQ.',
                ]);
            }
            $data['province_id'] = null;
            $data['district_id'] = null;
            return $data;
        }

        // Province Admin must be unique per province
        if (($data['type'] ?? null) === 'PROVINCE') {
            if (empty($data['province_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'province_id' => 'Province is required for a PROVINCE branch.',
                ]);
            }
            $exists = DB::table('branches')
                ->where('type', 'PROVINCE')
                ->where('province_id', $data['province_id'])
                ->exists();
            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'province_id' => 'This province already has its admin branch.',
                ]);
            }
            $data['district_id'] = null;
            return $data;
        }

        // District: province & district required and must match
        if (($data['type'] ?? null) === 'DISTRICT') {
            if (empty($data['province_id']) || empty($data['district_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'province_id' => 'Province is required.',
                    'district_id' => 'District is required.',
                ]);
            }
            $ok = DB::table('districts')
                ->where('id', $data['district_id'])
                ->where('province_id', $data['province_id'])
                ->exists();
            if (! $ok) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'district_id' => 'District does not belong to the selected province.',
                ]);
            }
        }

        return $data;
    }
}
