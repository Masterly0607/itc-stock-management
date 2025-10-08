<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\District;
use Illuminate\Database\Seeder;

class GeoSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Phnom Penh' => ['Chamkarmon', 'Daun Penh', 'Toul Kork'],
            'Battambang' => ['Banan', 'Thma Koul', 'Rotanak Mondol'],
            'Siem Reap'  => ['Angkor Thom', 'Banteay Srei', 'Puok'],
        ];

        foreach ($data as $pName => $districts) {
            $p = Province::firstOrCreate(['name' => $pName]);
            foreach ($districts as $d) {
                District::firstOrCreate(['province_id' => $p->id, 'name' => $d]);
            }
        }
    }
}
