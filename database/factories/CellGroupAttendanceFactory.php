<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Factories;

use App\Models\CellGroup;
use App\Models\CellGroupAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CellGroupAttendance>
 */
class CellGroupAttendanceFactory extends Factory
{
    /**
     * A meeting last week, saved without a photo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cell_group_id' => CellGroup::factory(),
            'met_on' => now('Asia/Taipei')->subWeek()->toDateString(),
            'recorded_by' => null,
        ];
    }
}
