<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests\Usher;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The name an usher types to find someone. Posted, then kept in the session, so it never
 * appears in a URL or a log.
 */
class SearchRosterRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }
}
