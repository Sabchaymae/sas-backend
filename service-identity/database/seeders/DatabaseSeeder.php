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
        // Add default Roles and Permissions (Required for system to work)
        if (class_exists(\App\Services\RolePermissionService::class)) {
            app(\App\Services\RolePermissionService::class)->seedDefaults();
        }

        // Test users for all Postman scenarios
        $this->call(UserSeeder::class);

    }
}
