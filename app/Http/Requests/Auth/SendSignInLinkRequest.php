<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests\Auth;

use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** PRD FR-2 — the email that asks for a sign-in link. Anyone may ask; the answer never differs. */
class SendSignInLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => app(PhoneNormalizer::class)->normalizeEmail($this->input('email'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
