<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * The two branches of the form's "Domisili Gereja IFGF" question.
     */
    public function run(): void
    {
        Branch::updateOrCreate(
            ['code' => Branch::TAIPEI],
            ['name' => 'IFGF Taipei', 'name_zh' => '台北', 'is_active' => true],
        );

        Branch::updateOrCreate(
            ['code' => Branch::ZHONGLI],
            ['name' => 'IFGF Zhongli', 'name_zh' => '中壢', 'is_active' => true],
        );
    }
}
