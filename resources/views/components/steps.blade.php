@props(['current', 'total', 'name'])

{{-- DESIGN.md §5. Registration only: "Step 1 of 2", the step's name, and a bar. --}}
<div {{ $attributes }}>
    <p class="text-label text-ink-muted">
        {{ __('Step :current of :total', ['current' => $current, 'total' => $total]) }}
        <span aria-hidden="true">·</span>
        <span class="text-ink">{{ $name }}</span>
    </p>

    <div class="mt-2 flex gap-1.5" aria-hidden="true">
        @for ($step = 1; $step <= $total; $step++)
            <span @class(['h-1.5 flex-1 rounded-full', 'bg-primary' => $step <= $current, 'bg-line' => $step > $current])></span>
        @endfor
    </div>
</div>
