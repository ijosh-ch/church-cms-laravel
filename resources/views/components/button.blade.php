@props(['variant' => 'primary', 'href' => null, 'type' => 'submit'])

@php
    // DESIGN.md §5. Full width on phones, natural width from the tablet up; 48 px high, 44 on desktop.
    $classes = [
        'group focus-ring inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-control px-5 py-2.5 text-center text-body font-medium transition-colors duration-150 ease-out',
        'disabled:cursor-not-allowed disabled:opacity-60 aria-busy:pointer-events-none',
        'md:w-auto lg:min-h-11 lg:text-label',
        match ($variant) {
            'secondary' => 'border border-line-strong bg-surface text-ink hover:bg-canvas active:bg-canvas',
            'quiet' => 'text-primary hover:bg-primary-soft hover:text-primary-hover active:bg-primary-soft',
            'danger' => 'bg-danger-solid text-on-primary hover:bg-danger active:bg-danger',
            default => 'bg-primary text-on-primary hover:bg-primary-hover active:bg-primary-hover',
        },
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    {{-- resources/js/app.js sets aria-busy while the form is being sent: the spinner shows, the label stays. --}}
    <button type="{{ $type }}" data-button {{ $attributes->class($classes) }}>
        <svg class="hidden size-5 shrink-0 animate-spin group-aria-busy:block" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
        <span>{{ $slot }}</span>
    </button>
@endif
