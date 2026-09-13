<?php

namespace Database\Seeders;

use App\Models\IndicatorGroup;
use Illuminate\Database\Seeder;

class IndicatorGroupSeeder extends Seeder
{
    private const GROUPS = [
        ['code' => 'IKU', 'name' => 'Indikator Kinerja Utama', 'sort_order' => 1],
        ['code' => 'IKP', 'name' => 'Indikator Kinerja Program', 'sort_order' => 2],
        ['code' => 'IKK', 'name' => 'Indikator Kinerja Kegiatan', 'sort_order' => 3],
        ['code' => 'RO', 'name' => 'Rincian Output', 'sort_order' => 4],
    ];

    public function run(): void
    {
        foreach (self::GROUPS as $group) {
            IndicatorGroup::firstOrCreate(['code' => $group['code']], $group);
        }
    }
}
