<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests\Usher;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * What the camera read. Anything that is not one of our 64-character tokens is simply an
 * unknown QR, so the shape is checked loosely here and the lookup decides.
 */
class RecordScanRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:512'],
        ];
    }
}
