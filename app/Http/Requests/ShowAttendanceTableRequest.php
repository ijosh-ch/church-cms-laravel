<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Which Sunday and which branch the attendance table shows. Neither is personal data, so
 * both may travel in the URL.
 */
class ShowAttendanceTableRequest extends FormRequest
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
            'date' => ['nullable', 'date_format:Y-m-d'],
            'branch' => ['nullable', 'string', Rule::exists('branches', 'code')],
        ];
    }
}
