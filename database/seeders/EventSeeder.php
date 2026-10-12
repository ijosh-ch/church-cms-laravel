<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Seeders;

use App\Enums\EventType;
use App\Enums\Recurrence;
use App\Enums\TeamType;
use App\Models\Branch;
use App\Models\Event;
use App\Models\Team;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Sunday Service for each branch and one weekly iCare event per iCare team (PRD FR-8).
     * Safe to run again. Occurrences are not seeded — one is created the first time
     * someone records attendance for that date.
     */
    public function run(): void
    {
        foreach (Branch::all() as $branch) {
            Event::updateOrCreate(
                ['type' => EventType::SundayService, 'branch_id' => $branch->id],
                ['name' => 'Sunday Service', 'recurrence' => Recurrence::Weekly, 'weekday' => 0],
            );
        }

        foreach (Team::where('type', TeamType::Icare)->get() as $team) {
            Event::updateOrCreate(
                ['type' => EventType::Icare, 'team_id' => $team->id],
                [
                    'name' => 'iCare '.$team->name,
                    'branch_id' => $team->branch_id,
                    'recurrence' => Recurrence::Weekly,
                    'weekday' => $team->meeting_weekday,
                ],
            );
        }
    }
}
