<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Taking a member out of a team. The team may be inactive by now, so any team is accepted.
 */
class DestroyMemberTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['team_id' => __('Team')];
    }
}
