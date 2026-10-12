@props(['name', 'label', 'type' => 'text', 'value' => '', 'required' => false, 'hint' => null])

<div class="mb-5">
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-stone-800">
        {{ $label }}
        @if ($required)
            <span class="text-red-700" aria-hidden="true">*</span>
        @endif
    </label>

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @required($required)
        @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->class([
            'block w-full rounded-md border bg-white px-3 py-2 text-base shadow-xs read-only:bg-stone-100 read-only:text-stone-700 focus:outline-2 focus:outline-offset-1 focus:outline-sky-700',
            'border-stone-300' => ! $errors->has($name),
            'border-red-600' => $errors->has($name),
        ]) }}
    >

    @if ($hint)
        <p class="mt-1 text-sm text-stone-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
