<?php

/*
 * Copyright (C) 2026 IFGF Taipei Zhongli
 *
 * This file is part of the IFGF church operations system.
 *
 * It is free software: you may redistribute it and/or modify it under the terms of
 * the GNU Affero General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 *
 * It is distributed in the hope that it will be useful to other churches and
 * ministries, but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General
 * Public License for more details: <https://www.gnu.org/licenses/>.
 *
 * See NOTICE.md for how this relates to the MIT-licensed upstream it builds on.
 */

namespace App\Http\Requests\Ifgf;

use Ifgf\ChurchOperations\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Registration is open to the public, exactly as the Google Form was. Duplicate
        // detection, not authorisation, is what protects the roster here.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:128'],
            'chinese_name' => ['nullable', 'string', 'max:64'],

            // At least one way to reach the member, and both are what duplicate detection
            // matches on — a member with neither could never be found again.
            'email' => ['required_without:phone', 'nullable', 'email:filter', 'max:190'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:32'],
            'line_id' => ['nullable', 'string', 'max:64'],

            // 'today' rather than 'now': a birthday is a date, and future-dating one is
            // always a typo. No lower bound beyond a sane one — the roster has members
            // born in 1988 and in 2018.
            'birthday' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],

            'gender' => ['nullable', Rule::in(['male', 'female'])],

            'branch_id' => ['required', 'integer', Rule::exists('ifgf_branches', 'id')->where('is_active', true)],
            'category_id' => ['nullable', 'integer', Rule::exists('ifgf_member_categories', 'id')],

            // Optional by design. No group is a real state, not a missing answer.
            'group_id' => ['nullable', 'integer', Rule::exists('ifgf_icare_groups', 'id')->where('is_active', true)],

            'domicile_taiwan' => ['nullable', 'string', 'max:128'],
            'occupation' => ['nullable', 'string', 'max:64'],
            'education_level' => ['nullable', 'string', 'max:32'],
            'school_or_company' => ['nullable', 'string', 'max:128'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required_without' => __('ifgf.contact_required'),
            'phone.required_without' => __('ifgf.contact_required'),
            'birthday.before' => __('ifgf.birthday_in_future'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => __('ifgf.name'),
            'chinese_name' => __('ifgf.chinese_name'),
            'email' => __('ifgf.email'),
            'phone' => __('ifgf.whatsapp'),
            'birthday' => __('ifgf.birthday'),
            'branch_id' => __('ifgf.branch'),
            'category_id' => __('ifgf.category'),
            'group_id' => __('ifgf.icare'),
        ];
    }

    protected function prepareForValidation(): void
    {
        // Empty selects arrive as '' and would fail the integer rule rather than being
        // treated as "not answered", which is what an optional field means here.
        $this->merge([
            'category_id' => $this->input('category_id') ?: null,
            'group_id' => $this->input('group_id') ?: null,
            'birthday' => $this->input('birthday') ?: null,
        ]);
    }

    /** Convenience for the controller: everything the registry service needs. */
    public function memberData(): array
    {
        return $this->safe()->all();
    }
}
