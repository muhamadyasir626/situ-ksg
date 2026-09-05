<?php

namespace Database\Seeders;

use App\Models\ReportingPeriod;
use Illuminate\Database\Seeder;

class ReportingPeriodSeeder extends Seeder
{
    public function run(): void
    {
        ReportingPeriod::firstOrCreate(
            ['year' => 2026, 'label' => 'Tahun Anggaran 2026'],
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'is_active' => true,
            ],
        );
    }
}
