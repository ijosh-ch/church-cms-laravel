@props(['variant' => 'info', 'title' => null, 'summary' => false])

@php
    // DESIGN.md §5. With `summary`, it lists the form's errors as links to their fields, and
    // resources/js/app.js moves focus to it when the page loads.
    [$colours, $icon, $role] = match ($variant) {
        'success' => ['bg-success-soft text-success', 'check-circle', 'status'],
        'warning' => ['bg-warning-soft text-warning', 'exclamation-triangle', 'status'],
        'danger' => ['bg-danger-soft text-danger', 'x-circle', 'alert'],
        default => ['bg-primary-soft text-primary-hover', 'information-circle', null],
    };
@endphp

<div
    @if ($role) role="{{ $role }}" @endif
    @if ($summary) tabindex="-1" data-error-summary @endif
    {{ $attributes->class(['flex gap-3 rounded-card p-4 outline-none', $colours]) }}
>
    <x-icon :name="$icon" size="24" />

    <div class="min-w-0 flex-1 text-body">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif

        {{ $slot }}

        @if ($summary)
            <ul class="mt-1 grid">
                @foreach ($errors->keys() as $field)
                    <li>
                        <a href="#{{ $field }}" class="focus-ring inline-flex min-h-11 items-center rounded-sm py-2 underline underline-offset-2">
                            {{ $errors->first($field) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
