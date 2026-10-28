<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests\CellGroup;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Active or inactive in the iCare group (PRD FR-9). The route checked the leader. */
class UpdateMemberStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
        ];
    }
}
