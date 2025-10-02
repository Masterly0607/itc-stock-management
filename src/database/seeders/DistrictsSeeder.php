<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Province;
use Illuminate\Database\Seeder;

class DistrictsSeeder extends Seeder
{
    public function run(): void
    {
        $pp = Province::where('name', 'Phnom Penh')->first();
        $sr = Province::where('name', 'Siem Reap')->first();
        $rows = [];
        if ($pp) {
            $rows[] = ['province_id' => $pp->id, 'name' => 'Chamkar Mon'];
            $rows[] = ['province_id' => $pp->id, 'name' => 'Daun Penh'];
        }
        if ($sr) {
            $rows[] = ['province_id' => $sr->id, 'name' => 'Siem Reap'];
            $rows[] = ['province_id' => $sr->id, 'name' => 'Prasat Bakong'];
        }
        District::query()->upsert($rows, ['province_id', 'name'], ['name', 'province_id']);
    }
}
