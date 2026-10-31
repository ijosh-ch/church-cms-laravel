{{-- PRD FR-9 — the iCare's own information, shared by New iCare and Edit iCare.
     Needs $group, $branches, $weekdays; $showStatus adds the IT admin's active switch. --}}
<div class="grid grid-cols-1 gap-5">
    <x-field name="name" :label="__('iCare name')" :hint="__('Shown as “iCare :name”, also in the birthday calendar.', ['name' => old('name', $group->name) ?: 'Linkou'])">
        <x-input :value="$group->name ?? ''" required maxlength="60" autocomplete="off" />
    </x-field>

    <x-field name="branch" :label="__('Branch')">
        <x-select required>
            <option value="" disabled @selected(old('branch', $group->branch?->code) === null)>{{ __('Choose a branch') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->code }}" @selected(old('branch', $group->branch?->code) === $branch->code)>{{ $branch->name }}</option>
            @endforeach
        </x-select>
    </x-field>

    <x-field name="meeting_weekday" :label="__('Meeting day')" optional>
        <x-select>
            <option value="" @selected((string) old('meeting_weekday', $group->meeting_weekday) === '')>{{ __('Not fixed') }}</option>
            @foreach ($weekdays as $day => $dayName)
                <option value="{{ $day }}" @selected((string) old('meeting_weekday', $group->meeting_weekday) === (string) $day)>{{ $dayName }}</option>
            @endforeach
        </x-select>
    </x-field>

    <x-field name="location" :label="__('Meeting place')" :hint="__('For example a district, a campus or “at Budi’s home”.')" optional>
        <x-input :value="$group->location ?? ''" maxlength="120" autocomplete="off" />
    </x-field>

    <x-field name="gmaps_url" :label="__('Google Maps link')" :hint="__('In Google Maps tap Share, then Copy link.')" optional>
        <x-input type="url" :value="$group->gmaps_url ?? ''" maxlength="2048" inputmode="url" autocomplete="off" placeholder="https://maps.app.goo.gl/…" />
    </x-field>

    @if ($showStatus ?? false)
        <div>
            <input type="hidden" name="is_active" value="0">
            <label class="flex min-h-11 items-center gap-3 text-body text-ink">
                <input type="checkbox" name="is_active" value="1" class="size-5 accent-primary" @checked(old('is_active', $group->is_active))>
                {{ __('This iCare is active') }}
            </label>
            <p class="mt-1 text-caption text-ink-muted">{{ __('An inactive iCare is hidden from registration and from the attendance list.') }}</p>
        </div>
    @endif
</div>
