@props(['title', 'href' => null, 'trailing' => null])

@php
    $row = 'flex min-h-14 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3';
@endphp

<li {{ $attributes }}>
    @if ($href)
        <a href="{{ $href }}" class="{{ $row }} transition-colors duration-150 ease-out hover:bg-canvas focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary">
            <span class="min-w-0">
                <span class="block text-body font-medium text-ink">{{ $title }}</span>
                <span class="block text-label font-normal text-ink-muted">{{ $slot }}</span>
            </span>
            <span class="flex items-center gap-2">
                {{ $trailing }}
                <x-icon name="chevron-right" class="text-ink-subtle" />
            </span>
        </a>
    @else
        <div class="{{ $row }}">
            <span class="min-w-0">
                <span class="block text-body font-medium text-ink">{{ $title }}</span>
                <span class="block text-label font-normal text-ink-muted">{{ $slot }}</span>
            </span>
            @if ($trailing)
                <span class="flex items-center gap-2">{{ $trailing }}</span>
            @endif
        </div>
    @endif
</li>
