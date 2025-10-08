<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Branch;
use App\Models\Province;
use App\Models\District;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;


class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $roleName = null;
    protected ?int $provinceId = null;
    protected ?int $districtId = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->roleName   = $data['role'] ?? null;
        $this->provinceId = $data['province_id'] ?? null;
        $this->districtId = $data['district_id'] ?? null;

        unset($data['role'], $data['province_id'], $data['district_id']);
        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            $record->update($data);

            if ($this->roleName) {
                $record->syncRoles([$this->roleName]);
            }

            if ($this->roleName === 'Admin') {
                $province = Province::findOrFail($this->provinceId);

                $branch = Branch::firstOrCreate(
                    ['province_id' => $province->id, 'district_id' => null],
                    [
                        'name' => 'Branch - ' . $province->name,
                        'code' => 'BR-' . Str::upper(Str::slug($province->name)),
                    ]
                );

                $record->update(['branch_id' => $branch->id]);
            }

            if ($this->roleName === 'Distributor') {
                $district = District::where('province_id', $this->provinceId)->findOrFail($this->districtId);

                $branch = Branch::firstOrCreate(
                    ['province_id' => $district->province_id, 'district_id' => $district->id],
                    [
                        'name' => $district->name . ' Branch',
                        'code' => 'BR-' . Str::upper(Str::slug($district->name)),
                    ]
                );

                $record->update(['branch_id' => $branch->id]);
            }

            return $record;
        });
    }
}
