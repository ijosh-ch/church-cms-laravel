<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Seeders;

use App\Enums\TeamAbility;
use App\Enums\TeamType;
use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * The 27 iCare groups offered by the live registration form (GAS_INVENTORY.md §7).
     * The form shows each as "iCare <name>".
     */
    public const ICARE = [
        '3D', 'BMI', 'Fuzhong', 'Liming', 'Home', 'Hsinchu', 'Joys', 'Immanuel', 'Jinshan',
        'Keelung', 'Kuy', 'Linkou', 'Papa', 'TMS', 'U', 'Freshcare', 'Weekend', 'TY', 'NTOU',
        'Tamkang', 'Grace', 'Penari', 'Wenhua', 'Revival', 'Harmony', 'Mingshin', 'CYCU',
    ];

    /**
     * Ministry divisions (PRD FR-14) and the access each one grants, if any.
     */
    public const MINISTRY = [
        'Usher' => TeamAbility::Scan,
        'IT' => TeamAbility::Admin,
        'Praise and Worship' => null,
        'Multimedia' => null,
        'Sound' => null,
        'Publication' => null,
        'Documentation' => null,
    ];

    /**
     * Run the database seeds. Safe to run again: teams are matched by type and name.
     */
    public function run(): void
    {
        foreach (self::ICARE as $name) {
            Team::firstOrCreate(['type' => TeamType::Icare, 'name' => $name]);
        }

        foreach (self::MINISTRY as $name => $ability) {
            $team = Team::firstOrNew(['type' => TeamType::Ministry, 'name' => $name]);
            $team->ability = $ability;
            $team->save();
        }
    }
}
