<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Piece', 'code' => 'PC'],
            ['name' => 'Box',   'code' => 'BOX'],
            ['name' => 'Case',  'code' => 'CASE'],
            ['name' => 'Pack',  'code' => 'PACK'],
            ['name' => 'Kilogram', 'code' => 'KG'],
            ['name' => 'Litre',    'code' => 'L'],
        ];

        foreach ($rows as $r) {
            Unit::firstOrCreate(['code' => $r['code']], $r);
        }
    }
}
