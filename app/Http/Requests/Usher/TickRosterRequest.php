<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests\Usher;

use App\Enums\AttendanceMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The usher ticks someone found by name: which member (by public_ref), onsite or online.
 */
class TickRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('scan') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'member' => ['required', 'uuid', Rule::exists('members', 'public_ref')->whereNull('deleted_at')],
            'mode' => ['required', Rule::enum(AttendanceMode::class)],
        ];
    }
}
