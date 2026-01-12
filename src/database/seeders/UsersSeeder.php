<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Make sure these codes exist in BranchesSeeder
        $hq = DB::table('branches')->where('code', 'BR-HQ')->value('id');
        $pp = DB::table('branches')->where('code', 'BR-PP')->value('id');
        $dp = DB::table('branches')->where('code', 'BR-DNP')->value('id');

        // If any of them is missing, stop — otherwise users would get branch_id = null again
        if (! $hq || ! $pp || ! $dp) {
            throw new \RuntimeException(
                'Branches BR-HQ, BR-PP, BR-DNP must exist. Run BranchesSeeder first.'
            );
        }

        // 1) Super Admin at HQ
        $super = User::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name'      => 'Super Admin',
                'password'  => Hash::make('1234'),
                'branch_id' => $hq,
                'status'    => 'ACTIVE',
            ]
        );
        $super->syncRoles(['Super Admin']);

        // 2) Admin Phnom Penh at province branch
        $admin = User::updateOrCreate(
            ['email' => 'ppadmin@example.com'],
            [
                'name'      => 'Admin Phnom Penh',
                'password'  => Hash::make('1234'),
                'branch_id' => $pp,
                'status'    => 'ACTIVE',
            ]
        );
        $admin->syncRoles(['Admin']);

        // 3) Distributor Daun Penh at district branch
        $dist = User::updateOrCreate(
            ['email' => 'dis@example.com'],
            [
                'name'      => 'Distributor Daun Penh',
                'password'  => Hash::make('1234'),
                'branch_id' => $dp,
                'status'    => 'ACTIVE',
            ]
        );
        $dist->syncRoles(['Distributor']);
    }
}
