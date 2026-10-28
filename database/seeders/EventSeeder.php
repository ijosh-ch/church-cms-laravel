<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Seeders;

use App\Enums\EventType;
use App\Enums\Recurrence;
use App\Models\Branch;
use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Sunday Service for each branch (PRD FR-8). iCare meetings are not events (FR-9).
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
    }
}
