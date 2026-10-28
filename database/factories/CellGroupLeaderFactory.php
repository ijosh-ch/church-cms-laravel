<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Factories;

use App\Enums\CellGroupRole;
use App\Models\CellGroup;
use App\Models\CellGroupLeader;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CellGroupLeader>
 */
class CellGroupLeaderFactory extends Factory
{
    /**
     * The current leader of a new group.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cell_group_id' => CellGroup::factory(),
            'member_id' => Member::factory(),
            'role' => CellGroupRole::Leader,
            'left_at' => null,
        ];
    }

    public function coLeader(): static
    {
        return $this->state(fn (array $attributes) => ['role' => CellGroupRole::CoLeader]);
    }
}
