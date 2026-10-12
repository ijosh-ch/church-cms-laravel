<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests\Admin;

use App\Enums\TeamRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An IT admin puts a member into a team, or changes their role in it (PRD FR-14).
 */
class StoreMemberTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->where('is_active', true)],
            'role' => ['required', Rule::enum(TeamRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'team_id' => __('Team'),
            'role' => __('Role'),
        ];
    }
}
