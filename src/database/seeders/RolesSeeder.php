<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Super Admin', 'Admin', 'Distributor'] as $name) {
            Role::findOrCreate($name, 'web'); // idempotent & DB-agnostic
        }
    }
}
