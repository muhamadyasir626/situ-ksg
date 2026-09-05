<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Direktorat KSG', 'code' => 'DKSG'],
            ['name' => 'Subdit Konservasi Spesies', 'code' => 'SKS'],
            ['name' => 'Subdit Konservasi Genetik', 'code' => 'SKG'],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(['code' => $unit['code']], $unit);
        }
    }
}
