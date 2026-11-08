<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Mkhaya Sales core seed: the platform administrators.
 *
 * Roles, permissions, countries/provinces/cities and the System Administration
 * navigation are loaded with the schema (database/schema/pgsql-schema.sql + data).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SalesAccessSeeder::class);
        $this->call(AdminUserSeeder::class);
    }
}
