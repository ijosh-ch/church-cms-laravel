@props(['name', 'label', 'hint' => null, 'optional' => false])

{{-- DESIGN.md §5. Wraps one control; the control inside reads `name` and `hint` from here. --}}
<div {{ $attributes }}>
    <label for="{{ $name }}" class="block text-label text-ink">
        {{ $label }}
        @if ($optional)
            <span class="font-normal text-ink-muted">({{ __('optional') }})</span>
        @endif
    </label>

    @if ($hint)
        <p id="{{ $name }}-hint" class="mt-1 text-caption text-ink-muted">{{ $hint }}</p>
    @endif

    <div class="mt-2">{{ $slot }}</div>

    @error($name)
        <p id="{{ $name }}-error" class="mt-2 flex items-start gap-1.5 text-label text-danger">
            <x-icon name="exclamation-circle" />
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>
