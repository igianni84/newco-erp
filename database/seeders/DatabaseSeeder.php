<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * RoleSeeder runs BEFORE OperatorSeeder so the bootstrap operator's role
     * assignment resolves against existing role rows (design D6).
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            OperatorSeeder::class,
        ]);
    }
}
