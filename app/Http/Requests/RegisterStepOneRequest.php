<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests;

use App\Http\Controllers\RegistrationController;
use App\Support\PhoneNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Page one of the registration form — fields 1 to 9 of PRD FR-1.
 */
class RegisterStepOneRequest extends FormRequest
{
    /**
     * Registration is public.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // After "Continue with Google" the email is the one Google vouched for, whatever is posted.
        $google = $this->hasSession() ? $this->session()->get(RegistrationController::GOOGLE) : null;

        $this->merge([
            'email' => is_array($google)
                ? $google['email']
                : mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'full_name' => ['required', 'string', 'max:255'],
            'chinese_name' => ['nullable', 'string', 'max:255'],
            'birthday' => ['required', 'date', 'after:1900-01-01', 'before:today'],
            'domicile_taiwan' => ['required', 'string', 'max:255'],
            'line_id' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32', $this->aPhoneNumber()],
            'branch' => ['required', 'string', Rule::exists('branches', 'code')->where('is_active', true)],
            'attend_again' => ['required', Rule::in(['yes', 'no'])],
        ];
    }

    /**
     * The form's own labels, so a message reads "The WhatsApp Number field is required."
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => __('Full Name'),
            'chinese_name' => __('Chinese Name'),
            'birthday' => __('Tanggal Lahir'),
            'domicile_taiwan' => __('Domisili Taiwan'),
            'line_id' => __('Line ID'),
            'phone' => __('WhatsApp Number'),
            'branch' => __('Domisili Gereja IFGF'),
            'attend_again' => __('Sunday Gathering'),
        ];
    }

    /**
     * A number that still has between 10 and 15 digits once written internationally.
     */
    private function aPhoneNumber(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $digits = strlen((new PhoneNormalizer)->normalize((string) $value));

            if ($digits < 10 || $digits > 15) {
                $fail(__('Enter a valid WhatsApp number, for example 0912 345 678.'));
            }
        };
    }
}
