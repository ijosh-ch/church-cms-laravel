<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Requests\CellGroup;

use App\Enums\AttendeeStatus;
use App\Models\CellGroup;
use App\Models\CellGroupAttendance;
use App\Rules\PersonName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * One iCare meeting (PRD FR-9). The route already checked that the person leads this group.
 *
 * members[<public_ref>][status|reason] must name exactly the group's active members;
 * a photo is required unless "Tanpa foto" is ticked or the date already has one.
 */
class SaveAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The page sends, per member, "came" and "online" ticks and a reason; per guest a name and an
     * "online" tick. Turn those into statuses. An empty guest row is the spare one: drop it.
     */
    protected function prepareForValidation(): void
    {
        $members = [];

        foreach ((array) $this->input('members', []) as $ref => $row) {
            $row = is_array($row) ? $row : [];
            $status = empty($row['came']) ? AttendeeStatus::Absent : (empty($row['online']) ? AttendeeStatus::Onsite : AttendeeStatus::Online);
            $reason = trim((string) ($row['reason'] ?? ''));

            $members[$ref] = [
                'status' => $row['status'] ?? $status->value,
                'reason' => $reason === '' ? null : $reason,
            ];
        }

        $guests = collect((array) $this->input('guests', []))
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['name'] ?? '')) !== '')
            ->map(fn ($row) => [
                'name' => trim((string) $row['name']),
                'status' => $row['status'] ?? (empty($row['online']) ? AttendeeStatus::Onsite->value : AttendeeStatus::Online->value),
            ])
            ->values()
            ->all();

        $this->merge(['members' => $members, 'guests' => $guests]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'met_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:'.Carbon::today('Asia/Taipei')->toDateString()],
            'photo' => ['nullable', 'file', 'max:10240'],
            'no_photo' => ['boolean'],
            'members' => ['array'],
            'members.*.status' => ['required', Rule::enum(AttendeeStatus::class)],
            'members.*.reason' => ['nullable', 'string', 'max:255', 'required_unless:members.*.status,onsite'],
            'guests' => ['array', 'max:50'],
            'guests.*.name' => ['required', 'string', 'max:100', new PersonName],
            'guests.*.status' => ['required', Rule::in([AttendeeStatus::Onsite->value, AttendeeStatus::Online->value])],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('met_on')) {
                return;
            }

            /** @var CellGroup $group */
            $group = $this->route('cellGroup');

            $active = $group->members()->where('cell_group_active', true)->pluck('public_ref')->map(fn ($ref) => (string) $ref)->sort()->values()->all();
            $sent = collect(array_keys($this->input('members', [])))->map(fn ($ref) => (string) $ref)->sort()->values()->all();

            if ($active !== $sent) {
                $validator->errors()->add('members', __('The member list has changed. Reload the page and fill it in again.'));
            }

            $hasPhoto = CellGroupAttendance::where('cell_group_id', $group->id)
                ->whereDate('met_on', $this->input('met_on'))
                ->whereNotNull('photo_path')
                ->exists();

            if (! $this->hasFile('photo') && ! $this->boolean('no_photo') && ! $hasPhoto) {
                $validator->errors()->add('photo', __('Add a photo of the meeting, or tick "No photo this time".'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'met_on' => __('meeting date'),
            'photo' => __('photo'),
            'members.*.reason' => __('reason'),
            'guests.*.name' => __("guest's name"),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'met_on.before_or_equal' => __('The meeting date cannot be in the future.'),
            'members.*.reason.required_unless' => __('Give a reason for everyone who was online or absent.'),
        ];
    }
}
