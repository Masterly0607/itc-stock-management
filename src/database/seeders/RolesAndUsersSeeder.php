<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Branch;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesAndUsersSeeder extends Seeder
{
    public function run(): void
    {
        $roles = collect(['Super Admin', 'Admin', 'Distributor'])
            ->each(fn($r) => Role::firstOrCreate(['name' => $r]));

        $branch = Branch::first();
        $super = User::firstOrCreate(
            ['email' => 'super@admin.com'],
            ['name' => 'Super Admin', 'password' => bcrypt('password'), 'branch_id' => $branch?->id,]
        );
        $super->syncRoles('Super Admin');
    }
}
