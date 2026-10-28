<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests\Member;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A member's own photo (owner, 2026-10-28). Optional, and only with the member's own consent
 * (STANDARDS §4.5): the photo may later be used to recognise them in iCare meeting photos.
 * The file's content is checked when it is re-encoded (AttendancePhotoStore).
 */
class StoreSelfPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'max:10240'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => __('Choose a photo of yourself.'),
            'consent.accepted' => __('Tick the box to agree, or leave without a photo.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['photo' => __('photo')];
    }
}
