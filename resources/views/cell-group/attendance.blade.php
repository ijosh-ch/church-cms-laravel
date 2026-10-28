{{-- PRD FR-9, design/DESIGN.md §7 "iCare attendance" (owner's design B, 2026-10-28).
     Works without script: each member sends came / online / reason, and the server derives the
     status. resources/js/icare-attendance.js adds the photo compression, the count, hiding the
     reason under onsite rows, guest rows and opening another date. --}}
@extends('layouts.app')

@section('title', $group->label().' — '.__('Attendance'))

@section('content')
    <p class="text-caption text-ink-muted">
        {{ collect([$group->branch?->name, $group->meeting_weekday !== null ? \Illuminate\Support\Carbon::create()->startOfWeek(0)->addDays($group->meeting_weekday)->translatedFormat('l') : null])->filter()->prepend('iCare')->join(' · ') }}
    </p>
    <h1 class="text-title text-ink">{{ $group->label() }}</h1>

    @if (session('status'))
        <x-alert class="mt-4" variant="success">{{ session('status') }}</x-alert>
    @endif

    @if ($errors->any())
        <x-alert class="mt-4" variant="danger" :title="__('Please check these fields:')" summary />
    @endif

    <form method="POST" action="{{ route('icare.attendance.update', $group) }}" enctype="multipart/form-data" novalidate
        class="mt-4 grid grid-cols-1 gap-6" data-icare-attendance
        data-open-url="{{ route('icare.attendance.edit', $group) }}">
        @csrf
        @method('PUT')

        {{-- 1 · Photo --}}
        <section aria-labelledby="photo-heading">
            <h2 id="photo-heading" class="text-label text-ink-muted">1 · {{ __('Photo of the meeting') }}</h2>

            @if ($hasPhoto)
                <img src="{{ route('icare.photo.show', [$group, $metOn]) }}" alt="{{ __('Photo of the meeting on :date', ['date' => $metOn]) }}"
                    class="mt-2 max-h-64 w-full rounded-card border border-line object-cover" loading="lazy">
            @endif

            <label for="photo" @class([
                'focus-ring mt-2 flex min-h-28 cursor-pointer flex-col items-center justify-center gap-1 rounded-card border-2 border-dashed border-line-strong bg-surface p-4 text-center text-body text-ink-muted',
                'border-danger' => $errors->has('photo'),
            ])>
                <x-icon name="camera" size="24" />
                <span data-photo-label>{{ $hasPhoto ? __('Replace the photo') : __('Take or upload a photo') }}</span>
                <span class="text-caption" data-photo-note>{{ __('Any photo works; it is made smaller before sending.') }}</span>
            </label>
            <input id="photo" name="photo" type="file" accept="image/*" class="sr-only" data-photo
                @error('photo') aria-invalid="true" aria-describedby="photo-error" @enderror>

            @unless ($hasPhoto)
                <label class="mt-3 flex min-h-11 items-center gap-3 text-body text-ink">
                    <input type="checkbox" name="no_photo" value="1" class="size-5 accent-primary" @checked(old('no_photo')) data-no-photo>
                    {{ __('No photo this time') }}
                </label>
            @endunless

            @error('photo')
                <p id="photo-error" class="mt-2 flex items-start gap-1.5 text-label text-danger"><x-icon name="exclamation-circle" /><span>{{ $message }}</span></p>
            @enderror
        </section>

        {{-- 2 · Date --}}
        <section aria-labelledby="date-heading">
            <h2 id="date-heading" class="text-label text-ink-muted">2 · {{ __('Meeting date') }}</h2>
            <div class="relative mt-2">
                <input id="met_on" name="met_on" type="date" value="{{ old('met_on', $metOn) }}" max="{{ $today }}" min="2020-01-01" required
                    class="focus-ring block min-h-12 w-full rounded-control border border-line-strong bg-surface px-3 text-body text-ink" data-date>
            </div>
            <p class="mt-2 text-caption text-ink-muted">
                {{ $saved ? __('Already saved — saving again updates this date.') : __('Not saved yet for this date.') }}
            </p>
            @error('met_on')
                <p class="mt-2 flex items-start gap-1.5 text-label text-danger"><x-icon name="exclamation-circle" /><span>{{ $message }}</span></p>
            @enderror
        </section>

        {{-- 3 · Members --}}
        <section aria-labelledby="members-heading">
            <h2 id="members-heading" class="text-label text-ink-muted">3 · {{ __('Members — untick who did not come') }}</h2>

            @php
                $came = $members->where('status', '!=', 'absent')->count();
                $online = $members->where('status', 'online')->count();
            @endphp
            <p class="mt-2 rounded-control bg-canvas px-3 py-2 text-body text-ink-muted" aria-live="polite" data-count
                data-template-came="{{ __(':came of :total came', ['came' => '__CAME__', 'total' => '__TOTAL__']) }}"
                data-template-split="{{ __(':onsite onsite, :online online', ['onsite' => '__ONSITE__', 'online' => '__ONLINE__']) }}">
                <span class="text-heading text-ink" data-count-came>{{ __(':came of :total came', ['came' => $came, 'total' => $members->count()]) }}</span>
                · <span data-count-split>{{ __(':onsite onsite, :online online', ['onsite' => $came - $online, 'online' => $online]) }}</span>
            </p>

            @error('members')
                <p class="mt-2 flex items-start gap-1.5 text-label text-danger"><x-icon name="exclamation-circle" /><span>{{ $message }}</span></p>
            @enderror

            @if ($members->isEmpty())
                <p class="mt-3 text-body text-ink-muted">{{ __('Nobody is in this iCare yet.') }}</p>
            @else
                <ul class="mt-2 divide-y divide-line rounded-card border border-line bg-surface">
                    @foreach ($members as $member)
                        @php
                            $key = 'members.'.$member['ref'];
                            // After a failed save the request has already turned the ticks into a status.
                            $status = old($key.'.status', $member['status']);
                            $reason = old($key.'.reason', $member['reason']);
                        @endphp
                        <li class="px-4 py-3" data-member data-status="{{ $status }}">
                            <input type="hidden" name="members[{{ $member['ref'] }}][seen]" value="1">
                            <div class="flex min-h-11 items-center gap-3">
                                <input id="came-{{ $member['ref'] }}" type="checkbox" name="members[{{ $member['ref'] }}][came]" value="1"
                                    class="size-6 shrink-0 accent-primary" @checked($status !== 'absent') data-came>
                                <label for="came-{{ $member['ref'] }}" class="min-w-0 flex-1 text-body text-ink">{{ $member['name'] }}</label>
                                <label class="flex min-h-11 items-center gap-2 rounded-full border border-line-strong px-3 text-caption text-ink-muted has-checked:border-primary has-checked:bg-primary-soft has-checked:text-primary-hover" data-online-wrap>
                                    <input type="checkbox" name="members[{{ $member['ref'] }}][online]" value="1" class="size-4 accent-primary" @checked($status === 'online') data-online>
                                    {{ __('Online') }}
                                </label>
                                <span class="hidden text-caption text-warning" data-absent-label>{{ __('Absent') }}</span>
                            </div>
                            <div data-reason-wrap>
                                <label for="members.{{ $member['ref'] }}.reason" class="sr-only">{{ __('Reason for :name', ['name' => $member['name']]) }}</label>
                                <input id="members.{{ $member['ref'] }}.reason" name="members[{{ $member['ref'] }}][reason]" type="text" maxlength="255"
                                    value="{{ $reason }}" placeholder="{{ __('Why online or absent? (not needed for onsite)') }}"
                                    data-placeholder-online="{{ __('Why online?') }}" data-placeholder-absent="{{ __('What happened?') }}"
                                    @class(['focus-ring mt-2 block min-h-11 w-full rounded-control border bg-surface px-3 text-label text-ink', 'border-danger' => $errors->has($key.'.reason'), 'border-line-strong' => ! $errors->has($key.'.reason')])>
                                @error($key.'.reason')
                                    <p class="mt-1 text-caption text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- 4 · Guests --}}
        <section aria-labelledby="guests-heading">
            <h2 id="guests-heading" class="text-label text-ink-muted">4 · {{ __('Guests') }}</h2>
            @php
                $guestRows = collect(old('guests', $guests->all()))
                    ->map(fn ($g) => ['name' => $g['name'] ?? '', 'online' => isset($g['online']) ? (bool) $g['online'] : (($g['status'] ?? 'onsite') === 'online')])
                    ->push(['name' => '', 'online' => false]);
            @endphp
            <ul class="mt-2 grid grid-cols-1 gap-2" data-guests>
                @foreach ($guestRows as $i => $guest)
                    <li class="flex min-w-0 items-center gap-2" data-guest>
                        <label for="guests.{{ $i }}.name" class="sr-only">{{ __("Guest's name") }}</label>
                        <input id="guests.{{ $i }}.name" name="guests[{{ $i }}][name]" type="text" maxlength="100" value="{{ $guest['name'] }}"
                            placeholder="{{ __("Guest's name") }}"
                            class="focus-ring block min-h-11 min-w-0 flex-1 rounded-control border border-line-strong bg-surface px-3 text-body text-ink" data-guest-name>
                        <label class="flex min-h-11 items-center gap-2 rounded-full border border-line-strong px-3 text-caption text-ink-muted has-checked:border-primary has-checked:bg-primary-soft has-checked:text-primary-hover">
                            <input type="checkbox" name="guests[{{ $i }}][online]" value="1" class="size-4 accent-primary" @checked($guest['online'])>
                            {{ __('Online') }}
                        </label>
                        <button type="button" class="focus-ring hidden size-11 items-center justify-center rounded-control text-ink-muted hover:bg-canvas" aria-label="{{ __('Remove guest') }}" data-guest-remove>
                            <x-icon name="x-mark" />
                        </button>
                    </li>
                    @error('guests.'.$i.'.name')
                        <li class="text-caption text-danger">{{ $message }}</li>
                    @enderror
                @endforeach
            </ul>
            <button type="button" class="focus-ring mt-2 hidden min-h-11 items-center gap-2 rounded-control px-2 text-label text-primary hover:bg-primary-soft" data-guest-add>
                <x-icon name="plus" /> {{ __('Add another guest') }}
            </button>
        </section>

        <div class="border-t border-line pt-4">
            <x-button>{{ __('Save attendance') }}</x-button>
        </div>
    </form>

    @if ($hasPhoto)
        <form method="POST" action="{{ route('icare.photo.destroy', [$group, $metOn]) }}" class="mt-3"
            data-confirm="{{ __('Delete the photo of this meeting?') }}">
            @csrf
            @method('DELETE')
            <x-button variant="quiet">{{ __('Delete the photo') }}</x-button>
        </form>
    @endif
@endsection
