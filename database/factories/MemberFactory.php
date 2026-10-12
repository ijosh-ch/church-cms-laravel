<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Factories;

use App\Enums\EducationLevel;
use App\Enums\MemberCategory;
use App\Enums\MemberStatus;
use App\Models\Branch;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state. An adult worker; the category is computed on save.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '8869'.fake()->unique()->numerify('########'),
            'birthday' => fake()->dateTimeBetween('-60 years', '-25 years')->format('Y-m-d'),
            'domicile_taiwan' => 'Taipei',
            'occupation' => 'worker',
            'education_level' => EducationLevel::S1,
            'school_or_company' => fake()->company(),
            'status' => MemberStatus::Active,
            'email_verified_at' => now(),
        ];
    }

    /**
     * Answered "not planning to attend again": the form ended after its first page.
     */
    public function visitor(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MemberStatus::Visitor,
            'occupation' => null,
            'education_level' => null,
            'school_or_company' => null,
        ]);
    }

    public function student(EducationLevel $education = EducationLevel::S1): static
    {
        return $this->state(fn (array $attributes) => [
            'occupation' => MemberCategory::STUDENT,
            'education_level' => $education,
            'birthday' => fake()->dateTimeBetween('-24 years', '-18 years')->format('Y-m-d'),
        ]);
    }

    /**
     * A family member added by a parent: no email, so no sign-in.
     */
    public function dependant(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'phone' => null,
            'occupation' => null,
            'education_level' => null,
            'school_or_company' => null,
            'birthday' => fake()->dateTimeBetween('-10 years', '-2 years')->format('Y-m-d'),
            'email_verified_at' => null,
        ]);
    }
}
