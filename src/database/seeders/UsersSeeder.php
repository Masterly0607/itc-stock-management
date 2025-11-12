<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $hq = DB::table('branches')->where('code', 'BR-HQ')->value('id');
        $pp = DB::table('branches')->where('code', 'BR-PP')->value('id');
        $dp = DB::table('branches')->where('code', 'BR-DNP')->value('id');

        $super = User::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            ['name' => 'Super Admin', 'password' => bcrypt('1234'), 'branch_id' => $hq, 'status' => 'ACTIVE']
        );
        $super->syncRoles(['Super Admin']);

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin Phnom Penh', 'password' => bcrypt('1234'), 'branch_id' => $pp, 'status' => 'ACTIVE']
        );
        $admin->syncRoles(['Admin']);

        $dist = User::updateOrCreate(
            ['email' => 'dis@example.com'],
            ['name' => 'Distributor Daun Penh', 'password' => bcrypt('1234'), 'branch_id' => $dp, 'status' => 'ACTIVE']
        );
        $dist->syncRoles(['Distributor']);
    }
}
