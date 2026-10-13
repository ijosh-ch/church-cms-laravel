@props(['icon' => 'inbox', 'action' => null])

{{-- DESIGN.md §5. Why it is empty, and the one action that fills it. --}}
<div {{ $attributes->class('flex flex-col items-center gap-3 rounded-card border border-dashed border-line-strong bg-surface px-4 py-8 text-center') }}>
    <x-icon :name="$icon" size="24" class="text-ink-subtle" />
    <p class="text-body text-ink-body">{{ $slot }}</p>
    {{ $action }}
</div>
