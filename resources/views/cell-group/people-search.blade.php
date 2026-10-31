{{-- PRD FR-9 — find a church member by name on Edit iCare, then act on one result.
     $for: leader | co_leader | member · $action: the route the pick goes to · $method: its verb ·
     $pick: the button text. The typed name goes in the form body and comes back through the
     session, never through the URL. A result shows the name, the branch and the main iCare. --}}
@php
    $mine = ($search['for'] ?? null) === $for;
    $failed = old('for') === $for && $errors->has('q');
@endphp

<form method="POST" action="{{ route('icare.search', $group) }}" novalidate class="mt-3" role="search">
    @csrf
    <input type="hidden" name="for" value="{{ $for }}">
    <label for="q-{{ $for }}" class="block text-label text-ink">{{ $label }}</label>
    <div class="mt-2 flex gap-2">
        <input id="q-{{ $for }}" name="q" type="search" value="{{ $mine ? $search['q'] : ($failed ? old('q') : '') }}"
            minlength="2" maxlength="60" autocomplete="off" required
            @if ($failed) aria-invalid="true" aria-describedby="q-{{ $for }}-error" @endif
            class="focus-ring block h-12 min-w-0 flex-1 rounded-control border border-line-strong bg-surface px-3.5 text-body text-ink placeholder:text-ink-subtle aria-invalid:border-danger lg:h-11"
            placeholder="{{ __('Name or Chinese name') }}">
        <button type="submit" data-button
            class="focus-ring inline-flex h-12 shrink-0 items-center gap-2 rounded-control border border-line-strong bg-surface px-4 text-body font-medium text-ink hover:bg-canvas lg:h-11 lg:text-label">
            <x-icon name="magnifying-glass" /> <span class="sr-only md:not-sr-only">{{ __('Search') }}</span>
        </button>
    </div>
    @if ($failed)
        <p id="q-{{ $for }}-error" class="mt-2 flex items-start gap-1.5 text-label text-danger"><x-icon name="exclamation-circle" /><span>{{ $errors->first('q') }}</span></p>
    @endif
</form>

@if ($mine)
    @if ($results->isEmpty())
        <p class="mt-3 text-body text-ink-muted" role="status">{{ __('Nobody found for “:q”.', ['q' => $search['q']]) }}</p>
    @else
        <ul class="mt-3 divide-y divide-line rounded-card border border-line" aria-label="{{ __('Search results') }}">
            @foreach ($results->take(10) as $person)
                <li class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 px-4 py-3">
                    <span class="min-w-0">
                        <span class="block text-body font-medium text-ink">{{ $person->full_name }}@if ($person->chinese_name) <span class="font-normal text-ink-muted">· {{ $person->chinese_name }}</span>@endif</span>
                        <span class="block text-label text-ink-muted">
                            {{ collect([$person->branch?->name, $person->cellGroup ? $person->cellGroup->label() : __('Belum join iCare')])->filter()->join(' · ') }}
                        </span>
                        @if ($for !== 'member' && $person->email_verified_at === null)
                            <x-badge class="mt-1" variant="warning" icon="exclamation-triangle">{{ __('Can open iCare pages after signing in with Google') }}</x-badge>
                        @endif
                    </span>
                    <form method="POST" action="{{ $action }}" class="w-full md:w-auto">
                        @csrf
                        @method($method)
                        <input type="hidden" name="member" value="{{ $person->public_ref }}">
                        <x-button variant="secondary">{{ $pick }}</x-button>
                    </form>
                </li>
            @endforeach
        </ul>
        @if ($results->count() > 10)
            <p class="mt-2 text-caption text-ink-muted">{{ __('Only the first 10 are shown. Type more of the name.') }}</p>
        @endif
    @endif
@endif
