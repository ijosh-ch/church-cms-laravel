@props(['name', 'legend', 'options', 'value' => null, 'hint' => null, 'optional' => false])

@php
    // DESIGN.md §5 — two to five choices. The first radio carries the group's id, so the error
    // summary can link to it.
    $invalid = $errors->has($name);
    $describedBy = trim(($hint ? $name.'-hint ' : '').($invalid ? $name.'-error' : ''));
    $chosen = (string) old($name, $value);
@endphp

<fieldset @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif {{ $attributes }}>
    <legend class="text-label text-ink">
        {{ $legend }}
        @if ($optional)
            <span class="font-normal text-ink-muted">({{ __('optional') }})</span>
        @endif
    </legend>

    @if ($hint)
        <p id="{{ $name }}-hint" class="mt-1 text-caption text-ink-muted">{{ $hint }}</p>
    @endif

    <div class="mt-2 grid gap-2">
        @foreach ($options as $option => $label)
            <label @class([
                'focus-ring-within flex min-h-13 cursor-pointer items-center gap-3 rounded-control border bg-surface px-4 py-3 text-body text-ink transition-colors duration-150 ease-out',
                'has-checked:border-primary has-checked:bg-primary-soft has-checked:ring-1 has-checked:ring-primary',
                'border-line-strong' => ! $invalid,
                'border-danger' => $invalid,
            ])>
                <input type="radio" name="{{ $name }}" value="{{ $option }}"
                    id="{{ $loop->first ? $name : $name.'-'.$option }}"
                    class="size-5 shrink-0 accent-primary outline-none"
                    @checked($chosen === (string) $option) @required(! $optional)>
                <span>{{ $label }}</span>
            </label>
        @endforeach
    </div>

    @error($name)
        <p id="{{ $name }}-error" class="mt-2 flex items-start gap-1.5 text-label text-danger">
            <x-icon name="exclamation-circle" />
            <span>{{ $message }}</span>
        </p>
    @enderror
</fieldset>
