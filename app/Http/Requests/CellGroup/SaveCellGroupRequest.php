<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests\CellGroup;

use App\Models\Branch;
use App\Models\CellGroup;
use App\Rules\GoogleMapsUrl;
use App\Rules\PersonName;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * New iCare and Edit iCare (PRD FR-9). The route checked who may send it. The name travels into
 * the shared birthday calendar ("iCare Name"), so it follows the one-line name rule and is
 * unique, whatever its letter case. Only the IT admin sets a group active or inactive.
 */
class SaveCellGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(['name', 'location', 'gmaps_url'])
            ->filter(fn (string $key) => is_string($this->input($key)))
            ->mapWithKeys(fn (string $key) => [$key => trim($this->input($key))])
            ->all());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $current = $this->route('cellGroup');

        return [
            'name' => ['required', 'string', 'max:60', new PersonName, function (string $attribute, mixed $value, Closure $fail) use ($current): void {
                $taken = CellGroup::query()
                    ->whereRaw('lower(name) = ?', [mb_strtolower((string) $value)])
                    ->when($current instanceof CellGroup, fn ($query) => $query->whereKeyNot($current->getKey()))
                    ->exists();

                if ($taken) {
                    $fail(__('Another iCare already has this name.'));
                }
            }],
            'branch' => ['required', 'string', Rule::exists('branches', 'code')->where('is_active', true)],
            'location' => ['nullable', 'string', 'max:120', 'not_regex:/[\r\n]/'],
            'gmaps_url' => ['nullable', 'string', 'max:2048', new GoogleMapsUrl],
            'meeting_weekday' => ['nullable', 'integer', 'between:0,6'],
            'is_active' => [Rule::prohibitedIf(! $this->user()->can('admin')), 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('iCare name'),
            'branch' => __('Branch'),
            'location' => __('Meeting place'),
            'gmaps_url' => __('Google Maps link'),
            'meeting_weekday' => __('Meeting day'),
        ];
    }

    /**
     * The columns to save. is_active is present only when the admin sent the form.
     *
     * @return array<string, mixed>
     */
    public function groupData(): array
    {
        $data = [
            'name' => $this->validated('name'),
            'branch_id' => Branch::where('code', $this->validated('branch'))->value('id'),
            'location' => $this->validated('location') ?: null,
            'gmaps_url' => $this->validated('gmaps_url') ?: null,
            'meeting_weekday' => $this->validated('meeting_weekday'),
        ];

        if ($this->user()->can('admin') && $this->has('is_active')) {
            $data['is_active'] = $this->boolean('is_active');
        }

        return $data;
    }
}
