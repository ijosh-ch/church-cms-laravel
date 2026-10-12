<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Factories;

use App\Models\Event;
use App\Models\Occurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Occurrence>
 */
class OccurrenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'occurs_on' => fake()->unique()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ];
    }
}
