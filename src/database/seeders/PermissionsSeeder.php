<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'provinces'   => ['view', 'create', 'update', 'delete'],
            'districts'   => ['view', 'create', 'update', 'delete'],
            'branches'    => ['view', 'create', 'update', 'delete'],
            'users'       => ['view', 'create', 'update', 'delete'],

        ];

        foreach ($map as $res => $acts) {
            foreach ($acts as $a) {
                Permission::firstOrCreate(['name' => "{$res}.{$a}"]);
            }
        }

        $super = Role::firstOrCreate(['name' => 'Super Admin']);
        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $dist  = Role::firstOrCreate(['name' => 'Distributor']);

        $super->syncPermissions(Permission::all());

        $adminPerms = [
            'provinces.view',
            'districts.view',
            'branches.view',
            'users.view',
            'users.create',
            'users.update',
        ];
        $admin->syncPermissions($adminPerms);

        $distPerms = [
            'users.view', // allow seeing their own user page if needed
        ];
        $dist->syncPermissions($distPerms);
    }
}
