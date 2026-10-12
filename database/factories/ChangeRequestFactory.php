<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Database\Factories;

use App\Models\ChangeRequest;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChangeRequest>
 */
class ChangeRequestFactory extends Factory
{
    /**
     * Define the model's default state. A pending request that expires in 7 days.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'requested_by' => Member::factory(),
            'changes' => ['domicile_taiwan' => ['old' => 'Taipei', 'new' => 'Zhongli']],
            'token_hash' => hash('sha256', Str::random(40)),
            'status' => ChangeRequest::PENDING,
            'expires_at' => now()->addDays(7),
        ];
    }
}
