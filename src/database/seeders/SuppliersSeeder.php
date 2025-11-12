<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SuppliersSeeder extends Seeder
{
    public function run(): void
    {
        // Existing ones
        Supplier::firstOrCreate(['code' => 'SUP001'], [
            'name' => 'Acme Trading',
            'email' => 'sales@acme.local',
            'phone' => '012345678',
            'tax_id' => 'T-123456',
            'contact_name' => 'Mr. Smith',
            'address' => 'Phnom Penh',
            'is_active' => true,
        ]);

        Supplier::firstOrCreate(['code' => 'SUP002'], [
            'name' => 'Best Supply',
            'email' => 'hello@best.local',
            'phone' => '098765432',
            'tax_id' => 'T-654321',
            'contact_name' => 'Ms. Dara',
            'address' => 'Siem Reap',
            'is_active' => true,
        ]);

        // 🔹 New suppliers required by ProductsSeeder
        Supplier::firstOrCreate(['code' => 'SUP-ACME'], [
            'name' => 'Acme Co',
            'email' => 'contact@acmeco.local',
            'phone' => '011223344',
            'tax_id' => 'T-789012',
            'contact_name' => 'Mr. John',
            'address' => 'Phnom Penh HQ',
            'is_active' => true,
        ]);

        Supplier::firstOrCreate(['code' => 'SUP-CARE'], [
            'name' => 'Care Supplies',
            'email' => 'info@caresupplies.local',
            'phone' => '022334455',
            'tax_id' => 'T-987654',
            'contact_name' => 'Ms. Sreyna',
            'address' => 'Battambang',
            'is_active' => true,
        ]);
    }
}
