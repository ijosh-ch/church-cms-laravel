@aware(['name', 'hint' => null])
@props(['name' => null, 'hint' => null, 'type' => 'text', 'value' => ''])

@php
    $invalid = $errors->has($name);
    $describedBy = trim(($hint ? $name.'-hint ' : '').($invalid ? $name.'-error' : ''));
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
