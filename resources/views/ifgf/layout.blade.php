<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ session('ifgf_theme', 'auto') }}">
<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover + the safe-area insets below are what stop the bottom nav
         sitting under the iPhone home indicator. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f1115" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <title>@yield('title', 'IFGF') — IFGF</title>

    <style>
    /* ─────────────────────────────────────────────────────────────────────────
       Design tokens.

       Light is defined on bare :root so a page always has a complete palette.
       Dark is layered twice: once under prefers-color-scheme for people who never
       touch the toggle, and once under [data-theme="dark"] so the toggle wins in
       both directions. No colour is ever defined ONLY inside a media query.
       ───────────────────────────────────────────────────────────────────────── */
    :root {
        --bg:        #f6f7f9;
        --surface:   #ffffff;
        --surface-2: #f0f2f5;
        --border:    #e2e5ea;
        --fg:        #12151a;
        --fg-muted:  #5c6673;
        --fg-faint:  #8b95a3;
        --accent:    #2f6feb;
        --accent-fg: #ffffff;
        --accent-soft:#e8f0fe;
        --ok:        #1a7f45;
        --ok-soft:   #e3f5ea;
        --warn:      #9a6400;
        --warn-soft: #fdf1dc;
        --danger:    #c0392b;
        --danger-soft:#fdecea;

        --radius:   14px;
        --radius-sm:10px;
        --shadow:   0 1px 2px rgba(16,24,40,.06), 0 1px 3px rgba(16,24,40,.10);
        --shadow-lg:0 8px 28px rgba(16,24,40,.12);

        --nav-h:     64px;
        --content-w: 1080px;

        --font: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans TC",
                "PingFang TC", "Microsoft JhengHei", Roboto, sans-serif;
    }

    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) {
            --bg:        #0f1115;
            --surface:   #171a21;
            --surface-2: #1e222b;
            --border:    #2a2f3a;
            --fg:        #eef1f6;
            --fg-muted:  #a3adbb;
            --fg-faint:  #6f7987;
            --accent:    #5b8def;
            --accent-fg: #0b0e13;
            --accent-soft:#1c2740;
            --ok:        #4ade80;
            --ok-soft:   #14281d;
            --warn:      #fbbf24;
            --warn-soft: #2b2312;
            --danger:    #f87171;
            --danger-soft:#2c1618;
            --shadow:    0 1px 2px rgba(0,0,0,.4);
            --shadow-lg: 0 8px 28px rgba(0,0,0,.5);
        }
    }

    :root[data-theme="dark"] {
        --bg:#0f1115; --surface:#171a21; --surface-2:#1e222b; --border:#2a2f3a;
        --fg:#eef1f6; --fg-muted:#a3adbb; --fg-faint:#6f7987;
        --accent:#5b8def; --accent-fg:#0b0e13; --accent-soft:#1c2740;
        --ok:#4ade80; --ok-soft:#14281d; --warn:#fbbf24; --warn-soft:#2b2312;
        --danger:#f87171; --danger-soft:#2c1618;
        --shadow:0 1px 2px rgba(0,0,0,.4); --shadow-lg:0 8px 28px rgba(0,0,0,.5);
    }

    * { box-sizing: border-box; }

    html { -webkit-text-size-adjust: 100%; }

    body {
        margin: 0;
        background: var(--bg);
        color: var(--fg);
        font: 15px/1.55 var(--font);
        /* The bottom nav is fixed on mobile; this keeps content clear of it. */
        padding-bottom: calc(var(--nav-h) + env(safe-area-inset-bottom));
        -webkit-font-smoothing: antialiased;
    }

    @media (min-width: 860px) { body { padding-bottom: 0; } }

    a { color: var(--accent); text-decoration: none; }
    a:hover { text-decoration: underline; }

    /* ── App shell ─────────────────────────────────────────────────────────── */

    .shell { max-width: var(--content-w); margin: 0 auto; padding: 0 16px; }

    .topbar {
        position: sticky; top: 0; z-index: 30;
        background: color-mix(in srgb, var(--surface) 88%, transparent);
        backdrop-filter: saturate(1.6) blur(10px);
        border-bottom: 1px solid var(--border);
        padding-top: env(safe-area-inset-top);
    }
    .topbar-inner {
        max-width: var(--content-w); margin: 0 auto; padding: 10px 16px;
        display: flex; align-items: center; gap: 12px; min-height: 56px;
    }
    .brand { font-weight: 700; letter-spacing: -.01em; font-size: 16px; color: var(--fg); }
    .brand span { color: var(--fg-faint); font-weight: 500; }
    .topbar-spacer { flex: 1; }

    /* ── Desktop nav ───────────────────────────────────────────────────────── */

    .nav-desktop { display: none; gap: 4px; }
    @media (min-width: 860px) { .nav-desktop { display: flex; } }

    .nav-desktop a {
        color: var(--fg-muted); padding: 8px 12px; border-radius: var(--radius-sm);
        font-weight: 500; font-size: 14px; text-decoration: none;
    }
    .nav-desktop a:hover { background: var(--surface-2); color: var(--fg); }
    .nav-desktop a[aria-current="page"] { background: var(--accent-soft); color: var(--accent); }

    /* ── Mobile bottom nav ─────────────────────────────────────────────────── */

    .nav-mobile {
        position: fixed; inset: auto 0 0 0; z-index: 40;
        display: grid; grid-auto-flow: column; grid-auto-columns: 1fr;
        background: var(--surface); border-top: 1px solid var(--border);
        padding-bottom: env(safe-area-inset-bottom);
    }
    @media (min-width: 860px) { .nav-mobile { display: none; } }

    .nav-mobile a {
        /* 44px is the minimum comfortable touch target; this clears it. */
        min-height: var(--nav-h);
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 3px; font-size: 11px; font-weight: 500;
        color: var(--fg-faint); text-decoration: none;
    }
    .nav-mobile a[aria-current="page"] { color: var(--accent); }
    .nav-mobile svg { width: 22px; height: 22px; }

    /* Topbar controls. They are compact on a laptop, where a mouse makes 36px fine, but
       must reach the 44px touch minimum on a phone — measured at 375px and they did not.
       The topbar is 56px tall, so 44 fits without changing the header's height. */
    .topbar-control { min-height: 36px; padding: 4px 8px; font-size: 13px; width: auto; }
    .topbar-icon-btn { min-height: 36px; padding: 0 10px; }

    @media (max-width: 859px) {
        .topbar-control { min-height: 44px; }

        /* .btn.topbar-icon-btn, not .topbar-icon-btn: .btn-sm is declared further down the
           sheet with the same specificity, so source order would otherwise win and keep
           this button at 36px. Measured — it did. */
        .btn.topbar-icon-btn { min-height: 44px; min-width: 44px; }
    }

    /* ── Typography ────────────────────────────────────────────────────────── */

    .page-head { padding: 22px 0 14px; }
    h1 { font-size: clamp(21px, 4vw, 27px); line-height: 1.2; margin: 0 0 4px; letter-spacing: -.02em; }
    h2 { font-size: 17px; margin: 0 0 12px; letter-spacing: -.01em; }
    .sub { color: var(--fg-muted); font-size: 14px; margin: 0; }

    /* ── Cards & grid ──────────────────────────────────────────────────────── */

    .card {
        background: var(--surface); border: 1px solid var(--border);
        border-radius: var(--radius); box-shadow: var(--shadow); padding: 18px;
    }
    .card + .card { margin-top: 14px; }

    .grid { display: grid; gap: 14px; }
    /* auto-fit + minmax is the whole responsive story: one column on a phone,
       as many as fit on a laptop, with no breakpoint to maintain. */
    .grid-stats { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
    .grid-2     { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }

    .stat .label { color: var(--fg-muted); font-size: 12px; text-transform: uppercase;
                   letter-spacing: .06em; font-weight: 600; }
    .stat .value { font-size: clamp(26px, 6vw, 34px); font-weight: 700;
                   letter-spacing: -.03em; margin-top: 4px; line-height: 1.1; }
    .stat .foot  { color: var(--fg-faint); font-size: 12px; margin-top: 2px; }

    /* ── Controls ──────────────────────────────────────────────────────────── */

    .btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 7px;
        min-height: 44px; padding: 0 16px; border-radius: var(--radius-sm);
        border: 1px solid var(--border); background: var(--surface); color: var(--fg);
        font: 500 14px/1 var(--font); cursor: pointer; text-decoration: none;
    }
    .btn:hover { background: var(--surface-2); text-decoration: none; }
    .btn-primary { background: var(--accent); border-color: var(--accent); color: var(--accent-fg); }
    .btn-primary:hover { background: var(--accent); filter: brightness(1.08); }
    .btn-sm { min-height: 36px; padding: 0 12px; font-size: 13px; }
    .btn-block { width: 100%; }

    input, select, textarea {
        width: 100%; min-height: 44px; padding: 10px 12px;
        border: 1px solid var(--border); border-radius: var(--radius-sm);
        background: var(--surface); color: var(--fg); font: inherit;
    }
    input:focus, select:focus, textarea:focus, .btn:focus-visible {
        outline: 2px solid var(--accent); outline-offset: 1px;
    }
    label { display: block; font-size: 13px; font-weight: 600; color: var(--fg-muted); margin-bottom: 6px; }
    .field + .field { margin-top: 14px; }

    .toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; }
    .toolbar .field { margin: 0; flex: 1 1 170px; }

    /* ── Badges ────────────────────────────────────────────────────────────── */

    .badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 9px; border-radius: 999px;
        font-size: 12px; font-weight: 600; white-space: nowrap;
        background: var(--surface-2); color: var(--fg-muted);
    }
    .badge-ok     { background: var(--ok-soft);     color: var(--ok); }
    .badge-warn   { background: var(--warn-soft);   color: var(--warn); }
    .badge-danger { background: var(--danger-soft); color: var(--danger); }
    .badge-accent { background: var(--accent-soft); color: var(--accent); }

    /* ── Tables that survive a phone ───────────────────────────────────────── */

    .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch;
                  border: 1px solid var(--border); border-radius: var(--radius); }
    table { border-collapse: collapse; width: 100%; font-size: 14px; }
    th, td { padding: 11px 14px; text-align: left; border-bottom: 1px solid var(--border); }
    th { background: var(--surface-2); font-size: 12px; text-transform: uppercase;
         letter-spacing: .05em; color: var(--fg-muted); font-weight: 600; white-space: nowrap; }
    tbody tr:last-child td { border-bottom: 0; }
    td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }

    /* ── Roster list ───────────────────────────────────────────────────────── */

    .person {
        display: flex; align-items: center; gap: 12px; padding: 12px 14px;
        border-bottom: 1px solid var(--border); background: var(--surface);
    }
    .person:last-child { border-bottom: 0; }
    .person.is-present { background: var(--ok-soft); }
    .avatar {
        width: 40px; height: 40px; flex: none; border-radius: 50%;
        background: var(--accent-soft); color: var(--accent);
        display: grid; place-items: center; font-weight: 700; font-size: 14px;
    }
    .person-main { flex: 1; min-width: 0; }
    .person-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .person-meta { color: var(--fg-faint); font-size: 12.5px;
                   white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .empty { text-align: center; padding: 40px 20px; color: var(--fg-faint); }

    .flash { padding: 12px 14px; border-radius: var(--radius-sm); margin-bottom: 14px; font-size: 14px; }
    .flash-ok { background: var(--ok-soft); color: var(--ok); }
    .flash-err{ background: var(--danger-soft); color: var(--danger); }

    .demo-banner {
        background: var(--warn-soft); color: var(--warn);
        font-size: 12.5px; font-weight: 600; text-align: center; padding: 7px 14px;
    }

    .site-footer {
        border-top: 1px solid var(--border);
        margin-top: 28px;
        padding: 18px 0 24px;
        text-align: center;
        color: var(--fg-faint);
        font-size: 12.5px;
        line-height: 1.7;
    }
    .site-footer a { color: var(--fg-muted); }
    .site-footer .legal { font-size: 11.5px; opacity: .85; }

    @media (prefers-reduced-motion: no-preference) {
        .btn, .nav-mobile a, .nav-desktop a { transition: background .13s ease, color .13s ease; }
    }

    @yield('styles')
    </style>
</head>
<body>

@if (config('ifgf-sites.prototype_mode'))
    <div class="demo-banner">
        {{ __('ifgf.prototype_banner') }}
    </div>
@endif

<header class="topbar">
    <div class="topbar-inner">
        <span class="brand">IFGF <span>@yield('site', 'Member')</span></span>
        <div class="topbar-spacer"></div>

        <nav class="nav-desktop">@yield('nav-desktop')</nav>

        {{-- Language: a plain GET form, so it works with JavaScript disabled and is
             bookmarkable. Three locales, per the requirement. --}}
        <form method="GET" action="{{ route('ifgf.locale') }}" style="display:flex;gap:6px">
            <input type="hidden" name="to" value="{{ url()->current() }}">
            <select name="locale" onchange="this.form.submit()"
                    class="topbar-control"
                    aria-label="{{ __('ifgf.language') }}">
                @foreach (['id' => 'Bahasa', 'en' => 'English', 'zh_TW' => '繁體中文'] as $code => $name)
                    <option value="{{ $code }}" @selected(app()->getLocale() === $code)>{{ $name }}</option>
                @endforeach
            </select>
        </form>

        <button type="button" class="btn btn-sm topbar-icon-btn" id="theme-toggle"
                aria-label="{{ __('ifgf.toggle_theme') }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
        </button>
    </div>
</header>

<main class="shell">
    @if (session('status'))  <div class="flash flash-ok">{{ session('status') }}</div>  @endif
    @if (session('error'))   <div class="flash flash-err">{{ session('error') }}</div>  @endif

    @yield('content')

    <footer class="site-footer">
        {{-- The church owns this deployment and the IFGF-authored code in it. --}}
        <div>&copy; {{ config('ifgf-sites.copyright_year') }} {{ config('ifgf-sites.copyright_holder') }}</div>

        {{-- 🔴 Required by the upstream MIT licence, which cannot be removed. The church
             management system this is built on is MIT-licensed by GegoSoft Technologies,
             and MIT binds every copy to carry its notice. --}}
        <div class="legal">{{ __('ifgf.built_on') }}</div>
    </footer>
</main>

<nav class="nav-mobile">@yield('nav-mobile')</nav>

<script>
// Theme toggle. Wrapped because localStorage throws outright in some embedded
// contexts, and a theme preference is never worth breaking a page over.
(function () {
    var root = document.documentElement;
    try {
        var saved = localStorage.getItem('ifgf-theme');
        if (saved) root.setAttribute('data-theme', saved);
    } catch (e) {}

    var btn = document.getElementById('theme-toggle');
    if (!btn) return;

    btn.addEventListener('click', function () {
        var current = root.getAttribute('data-theme');
        // From 'auto', flip to the opposite of what the OS is currently showing.
        if (current !== 'dark' && current !== 'light') {
            current = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        var next = current === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', next);
        try { localStorage.setItem('ifgf-theme', next); } catch (e) {}
    });
})();
</script>

@yield('scripts')
</body>
</html>
