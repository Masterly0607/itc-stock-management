<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Branch;
use App\Models\Province;
use App\Models\District;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BranchesSeeder extends Seeder
{
    public function run(): void
    {
        // Optional “owner” user (not required by schema, but ok to have)
        User::firstOrCreate(
            ['email' => 'owner@example.com'],
            ['name' => 'Seed Owner', 'password' => Hash::make('password')]
        );

        // Make sure base location data exists
        $pp = Province::firstOrCreate(['name' => 'Phnom Penh']);
        $dp = District::firstOrCreate(['name' => 'Daun Penh', 'province_id' => $pp->id]);

        $rows = [
            // main province branch: district_id = null
            ['code' => 'HQ', 'name' => 'Head Office',           'province_id' => $pp->id, 'district_id' => null],
            // district branch
            ['code' => 'PP-DP', 'name' => 'Daun Penh Branch',   'province_id' => $pp->id, 'district_id' => $dp->id],
        ];

        foreach ($rows as $r) {
            Branch::updateOrCreate(
                ['code' => $r['code']], // unique key
                [
                    'name'        => $r['name'],
                    'province_id' => $r['province_id'],
                    'district_id' => $r['district_id'], // can be null for main branch
                ]
            );
        }
    }
}
