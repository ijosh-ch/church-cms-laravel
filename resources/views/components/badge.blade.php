@props(['variant' => 'neutral', 'icon' => null])

@php
    // DESIGN.md §5. A status badge carries an icon; a category badge uses the colours of §3.1.
    $colours = match ($variant) {
        'success' => 'bg-success-soft text-success',
        'warning' => 'bg-warning-soft text-warning',
        'danger' => 'bg-danger-soft text-danger',
        'info' => 'bg-primary-soft text-primary-hover',
        'birthday' => 'bg-birthday-soft text-birthday',
        'kids' => 'bg-cat-kids-soft text-cat-kids',
        'teens' => 'bg-cat-teens-soft text-cat-teens',
        'college' => 'bg-cat-college-soft text-cat-college',
        'adult' => 'bg-cat-adult-soft text-cat-adult',
        default => 'bg-canvas text-ink-body ring-1 ring-line ring-inset',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-caption font-medium', $colours]) }}>
    @if ($icon)
        <x-icon :name="$icon" class="-ml-0.5 size-4" />
    @endif
    {{ $slot }}
</span>
