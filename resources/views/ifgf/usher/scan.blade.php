@extends('ifgf.layout')
@section('title', __('ifgf.scan'))
@section('site', 'Usher')
@include('ifgf._nav-usher')

@section('styles')
.scanner { position: relative; border-radius: var(--radius); overflow: hidden;
           background: #000; aspect-ratio: 4/3; max-height: 62vh; }
.scanner video { width: 100%; height: 100%; object-fit: cover; display: block; }
.reticle { position: absolute; inset: 50% auto auto 50%; translate: -50% -50%;
           width: min(62%, 240px); aspect-ratio: 1; border-radius: 18px;
           box-shadow: 0 0 0 100vmax rgba(0,0,0,.42); border: 2px solid rgba(255,255,255,.85); }
.result-card { margin-top: 14px; min-height: 92px; display: flex; align-items: center; gap: 14px; }
.result-card.ok  { border-left: 4px solid var(--ok); }
.result-card.bad { border-left: 4px solid var(--danger); }
.result-name { font-size: 19px; font-weight: 700; letter-spacing: -.01em; }
@endsection

@section('content')
<div class="page-head">
    <h1>{{ __('ifgf.scan_title') }}</h1>
    <p class="sub">{{ __('ifgf.scan_help') }}</p>
</div>

@if ($occurrence === null)
    <div class="card empty">{{ __('ifgf.no_occurrence') }}</div>
@else
<form method="GET" class="card" style="margin-bottom:14px">
    <div class="field">
        <label for="occurrence">{{ __('ifgf.recording_for') }}</label>
        <select id="occurrence" name="occurrence" onchange="this.form.submit()">
            @foreach ($occurrences as $o)
                <option value="{{ $o->id }}" @selected($occurrence->id === $o->id)>
                    {{ $o->branch?->name }} - {{ $o->service_date->format('D, d M Y') }}
                </option>
            @endforeach
        </select>
        {{-- The branch comes from here, never from the member. --}}
        <p class="sub" style="font-size:12.5px;margin-top:6px">{{ __('ifgf.branch_from_service') }}</p>
    </div>
</form>

<div class="scanner">
    <video id="cam" playsinline muted></video>
    <div class="reticle" aria-hidden="true"></div>
</div>

<div class="card result-card" id="out" aria-live="polite">
    <span class="sub">{{ __('ifgf.scan_waiting') }}</span>
</div>

<div class="card" style="margin-top:14px">
    <h2>{{ __('ifgf.other_ways') }}</h2>
    <div class="toolbar">
        <button type="button" class="btn" id="nfc-btn" hidden>{{ __('ifgf.tap_nfc') }}</button>
        <div class="field" style="flex:1 1 150px">
            <label for="code">{{ __('ifgf.short_code') }}</label>
            <input id="code" maxlength="8" autocomplete="off" spellcheck="false"
                   placeholder="A1B2C3D4" style="text-transform:uppercase">
        </div>
        <button type="button" class="btn btn-primary" id="code-btn">{{ __('ifgf.look_up') }}</button>
    </div>
    <p class="sub" style="margin-top:10px;font-size:12.5px">{{ __('ifgf.nfc_note') }}</p>
</div>
@endif
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;
const out  = document.getElementById('out');

{{-- Strings and URLs are built in a PHP block first. The json directive parses its
     argument as one directive expression, and a multi-line array literal holding
     nested translation calls breaks that parser: it emits PHP with an unclosed
     bracket and the view dies at compile time.

     Note also that a Blade comment must not contain a directive name preceded by an
     at-sign - Blade compiles the directive INSIDE the comment and destroys it. That
     is what the first version of this comment did. --}}
@php
    $strings = [
        'not_recognised' => __('ifgf.not_recognised'),
        'network' => __('ifgf.network_error'),
        'camera' => __('ifgf.camera_unavailable'),
        'nfc_ready' => __('ifgf.nfc_ready'),
        'nfc_denied' => __('ifgf.nfc_unavailable'),
    ];
    $strings['recorded'] = __('ifgf.recorded');
    $strings['already'] = __('ifgf.already_present');
    $urlResolve = route('usher.resolve');
    $urlResolveCode = route('usher.resolve.code');
@endphp

const T = @json($strings);
const URL_RESOLVE = @json($urlResolve);
const URL_RESOLVE_CODE = @json($urlResolveCode);
const OCCURRENCE_ID = @json($occurrence?->id);
let busy = false, lastPayload = null, lastAt = 0;

function render(ok, title, sub) {
    out.className = 'card result-card ' + (ok ? 'ok' : 'bad');
    out.innerHTML = '';
    const av = document.createElement('div');
    av.className = 'avatar';
    av.textContent = ok ? (title.trim()[0] || '?').toUpperCase() : '!';
    const box = document.createElement('div');
    const n = document.createElement('div');
    n.className = 'result-name';
    n.textContent = title;
    const s = document.createElement('div');
    s.className = 'sub';
    s.textContent = sub || '';
    box.append(n, s);
    out.append(av, box);
    if (ok && navigator.vibrate) navigator.vibrate(35);
}

async function send(url, body) {
    if (busy) return;
    busy = true;
    try {
        const r = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'},
            body: JSON.stringify(body)
        });
        const d = await r.json();
        if (r.ok && (d.status === 'recorded' || d.status === 'already')) {
            const note = d.status === 'already' ? T.already : T.recorded;
            render(true, d.member.name, note + ' · ' + d.occurrence.branch + ' ' + d.occurrence.date);
        } else {
            // One message for every failure. The server does not say which, on purpose.
            render(false, d.message || T.not_recognised, '');
        }
    } catch (e) {
        render(false, T.network, '');
    } finally {
        setTimeout(function () { busy = false; }, 700);
    }
}

function submitPayload(payload) {
    const now = Date.now();
    // A camera re-reads the same code many times a second.
    if (payload === lastPayload && now - lastAt < 3000) return;
    lastPayload = payload;
    lastAt = now;
    send(URL_RESOLVE, {payload: payload, occurrence_id: OCCURRENCE_ID});
}

const video = document.getElementById('cam');
const canvas = document.createElement('canvas');
const ctx = canvas.getContext('2d', {willReadFrequently: true});

navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}})
    .then(function (s) { video.srcObject = s; return video.play(); })
    .then(function () { requestAnimationFrame(tick); })
    .catch(function () { render(false, T.camera, ''); });

function tick() {
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const found = jsQR(img.data, img.width, img.height, {inversionAttempts: 'dontInvert'});
        if (found && found.data) submitPayload(found.data);
    }
    requestAnimationFrame(tick);
}

// Web NFC — Chrome on Android only. Reads NDEF TAGS. An Android phone in card-emulation
// mode randomises its UID on every tap, and iOS Safari has no Web NFC at all, so this can
// never read "another phone". See PG_MIGRATION_PLAN.md section 3.2.
if ('NDEFReader' in window) {
    const btn = document.getElementById('nfc-btn');
    btn.hidden = false;
    btn.addEventListener('click', async function () {
        try {
            const reader = new NDEFReader();
            await reader.scan();
            btn.textContent = T.nfc_ready;
            reader.onreading = function (e) {
                for (const rec of e.message.records) {
                    if (rec.recordType === 'text') {
                        submitPayload(new TextDecoder(rec.encoding || 'utf-8').decode(rec.data));
                        return;
                    }
                }
            };
        } catch (err) {
            render(false, T.nfc_denied, '');
        }
    });
}

document.getElementById('code-btn').addEventListener('click', function () {
    const code = document.getElementById('code').value.trim();
    if (code) send(URL_RESOLVE_CODE, {code: code, occurrence_id: OCCURRENCE_ID});
});
</script>
@endsection
