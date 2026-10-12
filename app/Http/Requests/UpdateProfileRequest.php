<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests;

use App\Enums\EducationLevel;
use App\Enums\MemberStatus;
use App\Enums\TeamType;
use App\Models\Team;
use App\Rules\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PRD FR-2 — a member edits their own profile: every registration field except the email.
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * The route is behind `auth`, and the profile edited is always the signed-in member's own.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // A visitor never answered page two of the form, so those fields stay optional for them.
        $pageTwo = $this->user()->status === MemberStatus::Visitor ? 'nullable' : 'required';

        $icareIds = Team::where('type', TeamType::Icare)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn (int $id) => (string) $id)
            ->all();

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'chinese_name' => ['nullable', 'string', 'max:255'],
            'birthday' => ['required', 'date', 'after:1900-01-01', 'before:today'],
            'domicile_taiwan' => ['required', 'string', 'max:255'],
            'line_id' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32', new PhoneNumber],
            'branch' => ['required', 'string', Rule::exists('branches', 'code')->where('is_active', true)],
            'occupation' => [$pageTwo, Rule::in(RegisterMemberRequest::OCCUPATIONS)],
            'occupation_other' => ['nullable', 'required_if:occupation,other', 'string', 'max:100'],
            'education_level' => ['nullable', Rule::enum(EducationLevel::class)],
            'school_or_company' => [$pageTwo, 'string', 'max:255'],
            'icare' => [$pageTwo, Rule::in([RegisterMemberRequest::NO_ICARE, ...$icareIds])],
        ];
    }

    /**
     * The form's own labels.
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
            'occupation' => __('Profesi saat ini'),
            'occupation_other' => __('other occupation'),
            'education_level' => __('Tingkat Pendidikan'),
            'school_or_company' => __('Sekolah / Kampus / Perusahaan'),
            'icare' => 'iCare',
        ];
    }
}
