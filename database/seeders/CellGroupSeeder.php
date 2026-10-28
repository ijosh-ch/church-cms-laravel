<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Seeders;

use App\Models\CellGroup;
use Illuminate\Database\Seeder;

class CellGroupSeeder extends Seeder
{
    /**
     * The 27 iCare groups offered by the live registration form (GAS_INVENTORY.md §7).
     * The form shows each as "iCare <name>". "U" is called "NTUST" since 2026-10-28 (owner).
     */
    public const NAMES = [
        '3D', 'BMI', 'Fuzhong', 'Liming', 'Home', 'Hsinchu', 'Joys', 'Immanuel', 'Jinshan',
        'Keelung', 'Kuy', 'Linkou', 'Papa', 'TMS', 'NTUST', 'Freshcare', 'Weekend', 'TY', 'NTOU',
        'Tamkang', 'Grace', 'Penari', 'Wenhua', 'Revival', 'Harmony', 'Mingshin', 'CYCU',
    ];

    /**
     * Run the database seeds. Safe to run again: groups are matched by name. Branch, place,
     * meeting day and leaders are set by the IT admin, not seeded.
     */
    public function run(): void
    {
        foreach (self::NAMES as $name) {
            CellGroup::firstOrCreate(['name' => $name]);
        }
    }
}
