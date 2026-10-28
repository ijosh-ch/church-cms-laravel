<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Factories;

use App\Enums\EventType;
use App\Enums\Recurrence;
use App\Models\Branch;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state. A branch's weekly Sunday Service.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => EventType::SundayService,
            'name' => 'Sunday Service',
            'branch_id' => Branch::factory(),
            'recurrence' => Recurrence::Weekly,
            'weekday' => 0,
            'is_active' => true,
        ];
    }
}
