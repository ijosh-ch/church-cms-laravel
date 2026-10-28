<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests;

use App\Enums\PhoneCountry;
use App\Http\Controllers\RegistrationController;
use App\Models\CellGroup;
use App\Models\Member;
use App\Rules\NotRegistered;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The registration form — the basics of PRD FR-1, on one page. The rest of the profile is filled
 * in later, on the profile page.
 */
class RegisterMemberRequest extends FormRequest
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
     * The phone is checked as one international number: the chosen country code, then what was
     * typed. The request itself keeps what was typed, so a form sent back shows it unchanged.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        $data = $this->all();
        $country = PhoneCountry::tryFrom((string) $this->input('phone_country'));

        if ($country !== null && is_string($data['phone'] ?? null)) {
            $data['phone'] = $country->combine($data['phone']);
        }

        return $data;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $icareIds = CellGroup::where('is_active', true)
            ->pluck('id')
            ->map(fn (int $id) => (string) $id)
            ->all();

        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'full_name' => ['required', 'string', 'max:255', new PersonName],
            'birthday' => ['required', 'date', 'after:1900-01-01', 'before:today'],
            'phone_country' => ['nullable', Rule::enum(PhoneCountry::class)],
            'phone' => ['required', 'string', 'max:32', new PhoneNumber(strict: true), new NotRegistered('phone', birthday: $this->input('birthday'))],
            'branch' => ['required', 'string', Rule::exists('branches', 'code')->where('is_active', true)],
            'icare' => ['required', Rule::in([Member::NO_ICARE, ...$icareIds])],
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
            'birthday' => __('Tanggal Lahir'),
            'phone' => __('WhatsApp Number'),
            'phone_country' => __('Country code'),
            'branch' => __('Domisili Gereja IFGF'),
            'icare' => 'iCare',
            'attend_again' => __('Sunday Gathering'),
        ];
    }
}
