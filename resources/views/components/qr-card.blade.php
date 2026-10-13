@props(['name', 'code'])

{{-- DESIGN.md §5. The slot is the QR itself: between 220 and 280 px, black on white in every theme. --}}
<section {{ $attributes->class('rounded-card border border-line bg-surface p-4 text-center md:p-6') }}>
    <div class="mx-auto w-full max-w-70 min-w-55 [&_svg]:h-auto [&_svg]:w-full">
        {{ $slot }}
    </div>

    <p class="mt-4 text-display text-ink">{{ $name }}</p>

    <p class="mt-4 text-caption text-ink-muted">{{ __('Backup code') }}</p>
    <p class="mt-1 font-mono text-title tracking-wider text-ink tabular-nums">
        {{-- Shown in two groups of four; read out, copied and tested as the one code it is. --}}
        <span aria-hidden="true">{{ substr($code, 0, 4) }}&nbsp;{{ substr($code, 4) }}</span>
        <span class="sr-only" data-testid="short-code">{{ $code }}</span>
    </p>

    <div class="mt-3 flex justify-center">
        <x-button variant="quiet" type="button" data-copy="{{ $code }}" data-copied="{{ __('Copied') }}">
            {{ __('Copy the code') }}
        </x-button>
    </div>
</section>
