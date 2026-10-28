<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Seeders;

use App\Enums\TeamAbility;
use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
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
     * Run the database seeds. Safe to run again: teams are matched by name. iCare groups are seeded by CellGroupSeeder.
     */
    public function run(): void
    {
        foreach (self::MINISTRY as $name => $ability) {
            $team = Team::firstOrNew(['name' => $name]);
            $team->ability = $ability;
            $team->save();
        }
    }
}
