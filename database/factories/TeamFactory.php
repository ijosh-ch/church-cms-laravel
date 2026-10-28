<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Factories;

use App\Enums\TeamAbility;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state. A ministry team with no special ability.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'branch_id' => null,
            'ability' => null,
            'is_active' => true,
        ];
    }

    /**
     * A ministry team whose members may scan (ushers) or administer (IT).
     */
    public function withAbility(TeamAbility $ability): static
    {
        return $this->state(fn (array $attributes) => [
            'ability' => $ability,
        ]);
    }
}
