<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class RolesAndUsersSeeder extends Seeder
{
    public function run(): void
    {
        $super = Role::firstOrCreate(['name' => 'Super Admin']);
        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $dist  = Role::firstOrCreate(['name' => 'Distributor']);
        $perms = [
            'products.view',
            'products.create',
            'products.update',
            'orders.view',
            'orders.create',
            'orders.deliver',
            'purchases.create',
            'purchases.receive',
            'transfers.create',
            'transfers.ship',
            'transfers.receive'
        ];
        foreach ($perms as $p) Permission::firstOrCreate(['name' => $p]);
        $super->givePermissionTo(Permission::all());

        $hq = Branch::where('code', 'HQ')->first();
        $user = User::firstOrCreate(
            ['email' => 'admin@itc.local'],
            ['name' => 'Super Admin', 'password' => Hash::make('password'), 'branch_id' => $hq?->id]
        );
        if (!$user->hasRole('Super Admin')) $user->assignRole('Super Admin');
    }
}
