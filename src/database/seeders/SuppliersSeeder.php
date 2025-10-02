<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SuppliersSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['name' => 'Unilever'], ['name' => 'P&G']] as $r) {
            Supplier::updateOrCreate(['name' => $r['name']], $r);
        }
    }
}
