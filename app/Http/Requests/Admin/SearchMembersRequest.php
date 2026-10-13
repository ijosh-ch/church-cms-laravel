<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests\Admin;

use App\Enums\MemberStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The member list's search and filters. Posted, then kept in the session, so a typed name
 * never appears in a URL or a log.
 */
class SearchMembersRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(MemberStatus::class)],
            'branch' => ['nullable', 'string', Rule::exists('branches', 'code')],
            'icare' => ['nullable', 'string', 'max:20'],
        ];
    }
}
