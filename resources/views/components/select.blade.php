@aware(['name', 'hint' => null])
@props(['name' => null, 'hint' => null, 'control' => null])

@php
    // The x-field around this control gives it its name, hint and error. A second control in the
    // same field (the country code before a phone number) passes its own name as `control`.
    $field = $name;
    $name = $control ?? $field;
    $invalid = $errors->has($name);
    $describedBy = trim(($hint ? $field.'-hint ' : '').($invalid && $name === $field ? $name.'-error' : ''));
@endphp

<div class="relative">
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        @if ($invalid) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class([
            'focus-ring block h-12 w-full appearance-none rounded-control border border-line-strong bg-surface pr-10 pl-3.5 text-body text-ink lg:h-11',
            'aria-invalid:border-danger aria-invalid:ring-1 aria-invalid:ring-danger',
        ]) }}
    >
        {{ $slot }}
    </select>
    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3.5 -translate-y-1/2 text-ink-muted" />
</div>
