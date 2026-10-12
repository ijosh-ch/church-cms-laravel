<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests\Usher;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChooseBranchRequest extends FormRequest
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
            'branch' => ['required', 'string', Rule::exists('branches', 'code')->where('is_active', true)],
        ];
    }
}
