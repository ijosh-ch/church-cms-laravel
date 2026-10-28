<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Factories;

use App\Enums\AttendeeStatus;
use App\Models\CellGroupAttendance;
use App\Models\CellGroupAttendee;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CellGroupAttendee>
 */
class CellGroupAttendeeFactory extends Factory
{
    /**
     * A member who came onsite.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_id' => CellGroupAttendance::factory(),
            'member_id' => Member::factory(),
            'guest_name' => null,
            'status' => AttendeeStatus::Onsite,
            'reason' => null,
        ];
    }
}
