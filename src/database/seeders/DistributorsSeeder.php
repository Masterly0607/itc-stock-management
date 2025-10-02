<?php

namespace Database\Seeders;

use App\Models\Distributor;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class DistributorsSeeder extends Seeder
{
    public function run(): void
    {
        $pp = Branch::where('code', 'PP')->first();
        if (!$pp) return;
        foreach (
            [
                ['branch_id' => $pp->id, 'name' => 'Lucky Mart'],
                ['branch_id' => $pp->id, 'name' => 'City Mini Mart'],
            ] as $r
        ) {
            Distributor::updateOrCreate(['branch_id' => $r['branch_id'], 'name' => $r['name']], $r);
        }
    }
}
