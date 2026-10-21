<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests\Admin;

use App\Enums\EducationLevel;
use App\Enums\MemberStatus;
use App\Enums\TeamType;
use App\Models\Member;
use App\Models\Team;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An IT admin edits any member. Only the name, branch and status are required: a record that
 * came from an import or a family row may be incomplete. The email is not editable.
 */
class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $icareIds = Team::where('type', TeamType::Icare)
            ->pluck('id')
            ->map(fn (int $id) => (string) $id)
            ->all();

        return [
            'full_name' => ['required', 'string', 'max:255', new PersonName],
            'chinese_name' => ['nullable', 'string', 'max:255', new PersonName],
            'birthday' => ['nullable', 'date', 'after:1900-01-01', 'before:today'],
            'domicile_taiwan' => ['nullable', 'string', 'max:255'],
            'line_id' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', new PhoneNumber],
            'branch' => ['required', 'string', Rule::exists('branches', 'code')],
            'occupation' => ['nullable', Rule::in(Member::OCCUPATIONS)],
            'occupation_other' => ['nullable', 'required_if:occupation,other', 'string', 'max:100'],
            'education_level' => ['nullable', Rule::enum(EducationLevel::class)],
            'school_or_company' => ['nullable', 'string', 'max:255'],
            'icare' => ['nullable', Rule::in([Member::NO_ICARE, ...$icareIds])],
            'status' => ['required', Rule::enum(MemberStatus::class)],
        ];
    }

    /**
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
            'status' => __('Status'),
        ];
    }
}
