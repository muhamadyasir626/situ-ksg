<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UnitSeeder::class,
            IndicatorGroupSeeder::class,
            IndicatorSeeder::class,
            ReportingPeriodSeeder::class,
        ]);

        $unit = Unit::where('code', 'DKSG')->first();

        $admin = User::firstOrCreate(
            ['email' => 'admin@situksg.test'],
            [
                'name' => 'Admin Internal',
                'password' => '12345678',
                'unit_id' => $unit?->id,
                'email_verified_at' => now(),
            ],
        );

        $admin->update(['password' => '12345678', 'email_verified_at' => now()]);

        $admin->assignRole('Admin');
    }
}
