<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Optional: look up province / district IDs if you already seed them
        $ppProvinceId = DB::table('provinces')->where('name', 'Phnom Penh')->value('id');
        $dpDistrictId = DB::table('districts')->where('name', 'Daun Penh')->value('id');

        $branches = [
            // 1) HQ – used by Super Admin
            [
                'code'        => 'BR-HQ',
                'name'        => 'HQ',
                'type'        => 'HQ',        // matches Branch::type (HQ|PROVINCE|DISTRICT)
                'province_id' => null,
                'district_id' => null,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],

            // 2) Phnom Penh province branch – used by Admin Phnom Penh
            [
                'code'        => 'BR-PP',
                'name'        => 'Phnom Penh Province',
                'type'        => 'PROVINCE',
                'province_id' => $ppProvinceId,   // can be null if you don't care
                'district_id' => null,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],

            // 3) Daun Penh district branch – used by Distributor Daun Penh
            [
                'code'        => 'BR-DNP',
                'name'        => 'Daun Penh District',
                'type'        => 'DISTRICT',
                'province_id' => $ppProvinceId,
                'district_id' => $dpDistrictId,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ];

        // Upsert by code so you can run seeder multiple times safely
        foreach ($branches as $branch) {
            DB::table('branches')->updateOrInsert(
                ['code' => $branch['code']],
                $branch,
            );
        }
    }
}
