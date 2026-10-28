<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database. Reference data only — no members.
     */
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            TeamSeeder::class,
            CellGroupSeeder::class,
            EventSeeder::class,
        ]);
    }
}
