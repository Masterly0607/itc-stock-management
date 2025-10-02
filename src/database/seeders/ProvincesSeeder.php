<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class ProvincesSeeder extends Seeder
{
    public function run(): void
    {
        Province::query()->upsert([
            ['name' => 'Phnom Penh'],
            ['name' => 'Siem Reap'],
            ['name' => 'Battambang'],
        ], ['name'], ['name']);
    }
}
