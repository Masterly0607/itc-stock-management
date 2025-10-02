<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Province;
use App\Models\District;
use Illuminate\Database\Seeder;

class BranchesSeeder extends Seeder
{
    public function run(): void
    {
        $pp = Province::where('name', 'Phnom Penh')->first();
        $dp = $pp ? District::where('province_id', $pp->id)->where('name', 'Daun Penh')->first() : null;
        foreach (
            [
                ['code' => 'HQ', 'name' => 'Head Office', 'province_id' => $pp?->id, 'district_id' => $dp?->id, 'address' => 'HQ Address'],
                ['code' => 'PP', 'name' => 'Phnom Penh Branch', 'province_id' => $pp?->id, 'district_id' => $dp?->id, 'address' => 'Phnom Penh Address'],
            ] as $r
        ) {
            Branch::updateOrCreate(['code' => $r['code']], $r);
        }
    }
}
