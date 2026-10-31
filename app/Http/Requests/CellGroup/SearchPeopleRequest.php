<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests\CellGroup;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Finding a church member by name on Edit iCare (PRD FR-9): for a co-leader, a member, or — the
 * IT admin only — the leader. The route checked who may send it.
 */
class SearchPeopleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $for = ['co_leader', 'member'];

        if ($this->user()->can('admin')) {
            $for[] = 'leader';
        }

        return [
            'for' => ['required', 'string', Rule::in($for)],
            'q' => ['required', 'string', 'min:2', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['q' => __('Name')];
    }
}
