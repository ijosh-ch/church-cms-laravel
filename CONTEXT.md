# CONTEXT

> Current state only. Rewritten every session end. Hard cap: 1,500 tokens.
> History belongs in `MEMORY.md`. Next actions belong in `TODO.md`.

**Updated:** 2026-08-08 · **Session:** 1 · **Work package:** 0A (not yet started)

## Git

| | |
|---|---|
| Branch | `codex-PRD` |
| HEAD | `d8cfe08b617351ed18945ab4b1c56a396d6d8a46` |
| Documentation baseline SHA | **not yet committed — blocks WP 0A** |
| `origin/main` | `aa8194ec3c66a6a00514e476d1ff4b47f03064d4` |
| `upstream/main` (reviewed) | `d12c110967fadbaa97fb2a108b71dd820a42e7ad` (2026-08-07, "Added privacy policy page") |
| Divergence `HEAD...upstream/main` | 1 ahead, 8 behind |
| Merge rehearsal | never run |
| `ifgf/main` | does not exist yet |
| `deploy` | does not exist yet |

Working tree: `PRD.md`, `.gitignore`, `composer.json`, `composer.lock` modified;
`build.md`, `hosting.md`, `EXECUTION_PLAN.md`, `CONTEXT.md`, `MEMORY.md`, `TODO.md`,
`CLAUDE.md`, `UPSTREAM.md`, `tools/` untracked. **Nothing committed.**

## Environment — **installed and booting**

| | Installed | Target |
|---|---|---|
| PHP | **8.3.33** (`C:\php\8.3`), 27/27 extensions | 8.4 at WP 0B |
| Composer | **2.10.2** | — |
| Laravel | **10.50.2** (verified unchanged) | 13.x at WP 0B |
| Node | **22.15.0** ← wrong line, `nvm use` did not stick | 16.20.2 for Mix 4 |
| MySQL | client present | 8.4 LTS, not yet configured |
| `vendor/` | 194 packages | — |
| `node_modules/` | 1,286 packages | — |
| `.env` | created; **`APP_KEY` not generated**, no DB configured | — |

`composer validate --strict` clean · `php artisan about` runs · `composer.json` now pins
`config.platform.php = 8.3.33`.

The assistant sandbox cannot run PHP: no root, and packagist/php.net/getcomposer are outside the
network allowlist. **Every PHP command runs on the Windows host and its output is pasted back.**
Bootstrap: `tools\setup-windows.ps1`, `tools\fix-php-ini.ps1`.

## Known debt

- **52 Composer advisories / 14 packages** (symfony/yaml ×3 low, doctrine/annotations abandoned).
- **166 npm vulnerabilities, 13 critical** (axios 0.18.1, Vue 2 EOL, Bootstrap 4.6 EOL).
- **UP-003 unfixed** — `EventgalleryFactory.php` PSR-4 case mismatch.
- Never run `npm audit fix` or unargumented `composer update` — both dissolve the baseline.

## Baseline shape

668 PHP files · 172 controllers · 79 models · 93 migrations · 317 Blade views · **0 tests** ·
183 Composer packages · 5 route files (`web`, `admin`, `api`, `guestapi`, `console`) ·
1 existing custom package (`custompackages/brozot/laravel-fcm`, path repo).

Laravel 10.50.2 / PHP `^8.2` / Vue 2.6 / laravel-mix 4 / webpack 4.

## Graphify

`graphify-out/` present but **stale and noisy** — it indexes compiled `public/js/app.js`.
`.graphifyignore` does not exist. Scheduled for WP 0A Session 5. Until then, treat graph query
results as unreliable and prefer targeted reads. `graphify-out/` stays untracked.

## Missing project files

`AGENTS.md` · `.graphifyignore` · `custompackages/ifgf/church-operations` · `tests/**`
— all remaining WP 0A deliverables.

## Open gates

1. **Documentation baseline not committed** — `build.md` OPERATING CONTRACT 6 blocks all coding.
2. **WP 0A scope not confirmed** by owner.
3. UP-002 exact resolved versions not yet recorded; UP-003 not yet fixed.
