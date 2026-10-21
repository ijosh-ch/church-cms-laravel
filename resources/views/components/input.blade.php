@aware(['name', 'hint' => null])
@props(['name' => null, 'hint' => null, 'control' => null, 'type' => 'text', 'value' => ''])

@php
    // The x-field around this control gives it its name, hint and error. A second control in the
    // same field (the country code before a phone number) passes its own name as `control`.
    $field = $name;
    $name = $control ?? $field;
    $invalid = $errors->has($name);
    $describedBy = trim(($hint ? $field.'-hint ' : '').($invalid && $name === $field ? $name.'-error' : ''));
@endphp

<input
    id="{{ $name }}"
    name="{{ $name }}"
    type="{{ $type }}"
    value="{{ old($name, $value) }}"
    @if ($invalid) aria-invalid="true" @endif
    @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
    {{ $attributes->class([
        'focus-ring block h-12 w-full rounded-control border border-line-strong bg-surface px-3.5 text-body text-ink placeholder:text-ink-subtle lg:h-11',
        'read-only:bg-canvas read-only:text-ink-muted',
        'aria-invalid:border-danger aria-invalid:ring-1 aria-invalid:ring-danger',
    ]) }}
>
