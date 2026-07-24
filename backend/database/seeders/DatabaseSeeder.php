<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            OfficeSeeder::class,
            RolePermissionSeeder::class,
            AuditAreaSeeder::class,
            MasterListSeeder::class,
            SystemConfigurationSeeder::class,
        ]);

        if (config('demo.enabled')) {
            $this->call([
                DemoUserSeeder::class,
                CoreUserSeeder::class,
            ]);
        }
    }
}
