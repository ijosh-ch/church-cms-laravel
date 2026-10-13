{{--
    Every component of design/DESIGN.md §5 that exists so far, in every state. No route shows this
    page: it is rendered to a file by hand when the components change. `$qr` is a sample QR SVG.
--}}
@extends('layouts.guest')

@section('title', 'Components — '.config('app.name'))

@section('content')
    <h1 class="text-title text-ink">Components</h1>
    <p class="mt-1 text-body text-ink-muted">design/DESIGN.md §5, light theme. Every state on one page.</p>

    <div class="mt-8 grid gap-12">
        <section class="grid gap-4">
            <h2 class="text-heading text-ink">Type</h2>
            <div class="grid gap-2">
                <p class="text-display text-ink">Display 28 / 36 — Maria Tan 陳美玲</p>
                <p class="text-title text-ink">Title 22 / 28 — Registration</p>
                <p class="text-heading text-ink">Heading 18 / 26 — About you</p>
                <p class="text-body text-ink-body">Body 16 / 24 — Welcome home. Fill this in once.</p>
                <p class="text-label text-ink">Label 14 / 20 — WhatsApp number</p>
                <p class="text-caption text-ink-muted">Caption 13 / 18 — As on your passport or ARC.</p>
            </div>
        </section>

        <section class="grid gap-4">
            <h2 class="text-heading text-ink">Colour</h2>
            <div class="grid grid-cols-3 gap-2 text-caption text-ink-muted md:grid-cols-4">
                @foreach ([
                    'canvas' => 'bg-canvas', 'surface' => 'bg-surface', 'line' => 'bg-line', 'line-strong' => 'bg-line-strong',
                    'ink' => 'bg-ink', 'ink-body' => 'bg-ink-body', 'ink-muted' => 'bg-ink-muted', 'ink-subtle' => 'bg-ink-subtle',
                    'primary' => 'bg-primary', 'primary-hover' => 'bg-primary-hover', 'primary-soft' => 'bg-primary-soft', 'danger-solid' => 'bg-danger-solid',
                    'success' => 'bg-success', 'success-soft' => 'bg-success-soft', 'warning' => 'bg-warning', 'warning-soft' => 'bg-warning-soft',
                    'danger' => 'bg-danger', 'danger-soft' => 'bg-danger-soft', 'birthday' => 'bg-birthday', 'birthday-soft' => 'bg-birthday-soft',
                ] as $token => $class)
                    <div>
                        <div class="h-12 rounded-control border border-line {{ $class }}"></div>
                        <p class="mt-1">{{ $token }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="flex flex-col gap-4 md:items-start">
            <h2 class="text-heading text-ink">x-button</h2>
            <x-button>Save attendance</x-button>
            <x-button variant="secondary">Find by name</x-button>
            <x-button variant="quiet">Back to step 1</x-button>
            <x-button variant="danger">Remove from team</x-button>
            <x-button aria-busy="true">Save attendance</x-button>
            <x-button variant="secondary" aria-busy="true">Find by name</x-button>
            <x-button disabled>Save attendance</x-button>
            <x-button variant="secondary" disabled>Find by name</x-button>
            <x-button variant="secondary" href="#">A link that looks like a button</x-button>
            <x-button variant="secondary" href="#"><x-icon name="google" /> Continue with Google</x-button>
            <x-button>Simpan kehadiran untuk ibadah hari Minggu di cabang Zhongli</x-button>
        </section>

        <section class="grid gap-5">
            <h2 class="text-heading text-ink">x-field with x-input, x-select</h2>

            <x-field name="demo_plain" label="Full name">
                <x-input autocomplete="off" />
            </x-field>

            <x-field name="demo_hint" label="Full name" hint="As on your passport or ARC.">
                <x-input value="Maria Tan" autocomplete="off" />
            </x-field>

            <x-field name="demo_optional" label="Chinese name" optional>
                <x-input autocomplete="off" />
            </x-field>

            <x-field name="demo_placeholder" label="Domicile in Taiwan" hint="District and city.">
                <x-input placeholder="Taipei - Da'an" autocomplete="off" />
            </x-field>

            <x-field name="demo_date" label="Birthday">
                <x-input type="date" value="2000-01-31" />
            </x-field>

            <x-field name="demo_error" label="Email" hint="We send your QR here.">
                <x-input type="email" value="maria@" inputmode="email" autocomplete="off" />
            </x-field>

            <x-field name="demo_locked" label="Email" hint="Confirmed by Google.">
                <x-input type="email" value="maria.tan@example.com" readonly />
            </x-field>

            <x-field name="demo_select" label="iCare">
                <x-select>
                    <option value="">Choose one</option>
                    <option>iCare Grace</option>
                    <option>iCare Unity</option>
                </x-select>
            </x-field>

            <x-field name="demo_select_error" label="iCare">
                <x-select>
                    <option value="">Choose one</option>
                    <option>iCare Grace</option>
                </x-select>
            </x-field>
        </section>

        <section class="grid gap-5">
            <h2 class="text-heading text-ink">x-radio-cards</h2>

            <x-radio-cards name="demo_radio" legend="Which branch do you attend?"
                :options="['tpe' => 'IFGF Taipei / 台北', 'zl' => 'IFGF Zhongli / 中壢']" />

            <x-radio-cards name="demo_radio_chosen" legend="Apakah Anda berencana untuk menghadiri kembali Sunday Gathering?"
                hint="A visitor skips the second page." :options="['yes' => 'Ya', 'no' => 'Tidak']" value="yes" />

            <x-radio-cards name="demo_radio_error" legend="Which branch do you attend?"
                :options="['tpe' => 'IFGF Taipei / 台北', 'zl' => 'IFGF Zhongli / 中壢']" />
        </section>

        <section class="grid gap-4">
            <h2 class="text-heading text-ink">x-alert</h2>

            <x-alert title="What we collect, and why">
                Your name, birthday and contact details, used only to record your attendance.
            </x-alert>
            <x-alert variant="success" title="You're registered">This is your attendance QR — show it to the usher on Sunday.</x-alert>
            <x-alert variant="warning" title="Email not verified yet">A role takes effect after this member signs in with Google.</x-alert>
            <x-alert variant="danger" title="Google sign-in did not finish">Try again, or register without Google.</x-alert>
            <x-alert variant="success">Saved.</x-alert>
            <x-alert variant="danger" title="Nothing was saved. Please check these fields:" summary />
        </section>

        <section class="grid gap-4">
            <h2 class="text-heading text-ink">x-card</h2>

            <x-card>
                <p>A card with no title: a surface, a 1 px line, a 14 px radius.</p>
            </x-card>

            <x-card title="Birthday calendar">
                <x-slot:action>
                    <x-badge variant="success" icon="check-circle">Synced</x-badge>
                </x-slot:action>
                <p>Last updated Sun 12 Oct 2026, 13:42.</p>
            </x-card>
        </section>

        <section class="grid gap-4">
            <h2 class="text-heading text-ink">x-badge</h2>
            <div class="flex flex-wrap gap-2">
                <x-badge>Visitor</x-badge>
                <x-badge variant="success" icon="check-circle">Recorded 13:42</x-badge>
                <x-badge variant="warning" icon="exclamation-triangle">Already recorded</x-badge>
                <x-badge variant="danger" icon="x-circle">Not found</x-badge>
                <x-badge variant="info" icon="information-circle">Online</x-badge>
                <x-badge variant="birthday">14 Oct</x-badge>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-badge variant="kids">Kids</x-badge>
                <x-badge variant="teens">Teens and Youth</x-badge>
                <x-badge variant="college">College</x-badge>
                <x-badge variant="adult">Adult</x-badge>
            </div>
        </section>

        <section class="grid gap-4">
            <h2 class="text-heading text-ink">x-list</h2>

            <x-list>
                <x-list.item title="Maria Tan 陳美玲" href="#">
                    IFGF Taipei · iCare Grace
                    <x-slot:trailing>
                        <x-badge variant="college">College</x-badge>
                    </x-slot:trailing>
                </x-list.item>
                <x-list.item title="Budi Santoso">
                    Belum mengikuti
                    <x-slot:trailing>
                        <x-badge variant="success" icon="check-circle">Recorded 13:42</x-badge>
                    </x-slot:trailing>
                </x-list.item>
                <x-list.item title="Christopher Alexander Hadiwijaya Kusumo">
                    iCare Unity
                    <x-slot:trailing>
                        <x-button variant="secondary" type="button">Onsite</x-button>
                    </x-slot:trailing>
                </x-list.item>
                <x-list.item title="A row with a title only">iCare Grace</x-list.item>
            </x-list>
        </section>

        <section class="grid gap-4">
            <h2 class="text-heading text-ink">x-empty</h2>

            <x-empty>
                Nobody has been recorded for this service yet.
                <x-slot:action>
                    <x-button variant="secondary" href="#">Start scanning</x-button>
                </x-slot:action>
            </x-empty>

            <x-empty icon="magnifying-glass">Search for a member to tick them.</x-empty>
        </section>

        <section class="grid gap-6">
            <h2 class="text-heading text-ink">x-steps</h2>
            <x-steps current="1" total="2" name="About you" />
            <x-steps current="2" total="2" name="Study and work" />
        </section>

        <section class="grid gap-4">
            <h2 class="text-heading text-ink">x-qr-card</h2>
            <x-qr-card name="Maria Tan" code="K7QM2XPD">
                {{-- A QR generated on this server: the one allowed raw output. --}}
                <div data-testid="qr">{!! $qr !!}</div>
            </x-qr-card>
        </section>

        <section class="grid gap-4">
            <h2 class="text-heading text-ink">x-icon</h2>
            <div class="flex flex-wrap gap-4 text-ink-body">
                @foreach (['information-circle', 'check-circle', 'exclamation-triangle', 'exclamation-circle', 'x-circle', 'x-mark', 'check', 'chevron-right', 'chevron-down', 'arrow-left', 'magnifying-glass', 'inbox', 'document-duplicate', 'user', 'google'] as $icon)
                    <span class="flex flex-col items-center gap-1 text-caption text-ink-muted">
                        <x-icon :name="$icon" size="24" class="text-ink-body" />
                        {{ $icon }}
                    </span>
                @endforeach
            </div>
        </section>
    </div>
@endsection
