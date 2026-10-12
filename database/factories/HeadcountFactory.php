<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Factories;

use App\Models\Headcount;
use App\Models\Occurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Headcount>
 */
class HeadcountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $adult = fake()->numberBetween(20, 80);
        $college = fake()->numberBetween(5, 40);
        $teensYouth = fake()->numberBetween(0, 15);
        $kids = fake()->numberBetween(0, 15);

        return [
            'occurrence_id' => Occurrence::factory(),
            'adult' => $adult,
            'college' => $college,
            'teens_youth' => $teensYouth,
            'kids' => $kids,
            'online' => fake()->numberBetween(0, 10),
            'total' => $adult + $college + $teensYouth + $kids,
            'reporter' => null,
            'source' => 'manual',
        ];
    }
}
