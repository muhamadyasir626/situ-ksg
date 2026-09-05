<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Define the five roles used by SITU-KSG (§3 spec).
     */
    private const ROLES = [
        'Admin',
        'Pelapor Unit',
        'Verifikator',
        'Pimpinan',
        'Auditor',
    ];

    public function run(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        foreach (self::ROLES as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
    }
}
