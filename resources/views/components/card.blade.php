@props(['title' => null, 'action' => null])

{{-- DESIGN.md §5. A 1 px line, no shadow. Never a card inside a card. --}}
<section {{ $attributes->class('rounded-card border border-line bg-surface p-4 md:p-6') }}>
    @if ($title)
        <div class="mb-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <h2 class="text-heading text-ink">{{ $title }}</h2>
            {{ $action }}
        </div>
    @endif

    {{ $slot }}
</section>
