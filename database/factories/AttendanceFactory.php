<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Factories;

use App\Enums\AttendanceMethod;
use App\Enums\AttendanceMode;
use App\Enums\MemberCategory;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Occurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state. A member scanned in on site.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'occurrence_id' => Occurrence::factory(),
            'member_id' => Member::factory(),
            'guest_name' => null,
            'mode' => AttendanceMode::Onsite,
            'method' => AttendanceMethod::Qr,
            'category' => MemberCategory::Adult,
            'recorded_by' => null,
            'recorded_at' => now(),
        ];
    }

    /**
     * Someone not registered yet, ticked by name.
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'member_id' => null,
            'guest_name' => fake()->firstName(),
            'method' => AttendanceMethod::Manual,
            'category' => null,
        ]);
    }
}
