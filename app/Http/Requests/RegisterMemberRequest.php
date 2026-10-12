<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests;

use App\Enums\EducationLevel;
use App\Enums\TeamType;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Page two of the registration form — fields 10 to 13 of PRD FR-1, asked only of people who
 * plan to attend again.
 */
class RegisterMemberRequest extends FormRequest
{
    /** The three choices of "Profesi saat ini"; `other` takes typed text. */
    public const OCCUPATIONS = ['worker', 'student', 'homemaker', 'other'];

    /** "Belum mengikuti" — not in an iCare group yet. */
    public const NO_ICARE = 'none';

    /**
     * Registration is public.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $icareIds = Team::where('type', TeamType::Icare)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn (int $id) => (string) $id)
            ->all();

        return [
            'occupation' => ['required', Rule::in(self::OCCUPATIONS)],
            'occupation_other' => ['nullable', 'required_if:occupation,other', 'string', 'max:100'],
            'education_level' => ['nullable', Rule::enum(EducationLevel::class)],
            'school_or_company' => ['required', 'string', 'max:255'],
            'icare' => ['required', Rule::in([self::NO_ICARE, ...$icareIds])],
        ];
    }

    /**
     * The form's own labels, so a message reads "The iCare field is required."
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'occupation' => __('Profesi saat ini'),
            'occupation_other' => __('other occupation'),
            'education_level' => __('Tingkat Pendidikan'),
            'school_or_company' => __('Sekolah / Kampus / Perusahaan'),
            'icare' => 'iCare',
        ];
    }
}
