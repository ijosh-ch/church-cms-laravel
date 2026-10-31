<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests\CellGroup;

use App\Models\Member;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A church member picked from the search on Edit iCare (PRD FR-9): sent by public_ref in the
 * form body, never by id. The route checked who may send it.
 */
class PickMemberRequest extends FormRequest
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
            'member' => ['required', 'uuid', Rule::exists('members', 'public_ref')->whereNull('deleted_at')],
        ];
    }

    public function member(): Member
    {
        return Member::where('public_ref', $this->validated('member'))->sole();
    }
}
