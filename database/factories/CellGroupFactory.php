<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Factories;

use App\Models\CellGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CellGroup>
 */
class CellGroupFactory extends Factory
{
    /**
     * An active iCare group meeting on Thursdays, with no branch, place or leader yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'branch_id' => null,
            'location' => null,
            'gmaps_url' => null,
            'meeting_weekday' => 4,
            'is_active' => true,
        ];
    }
}
