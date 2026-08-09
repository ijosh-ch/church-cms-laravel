# CONTEXT

> Current state only. Rewritten every session end. Hard cap: 1,500 tokens.
> History belongs in `MEMORY.md`. Next actions belong in `TODO.md`.

**Updated:** 2026-08-09 · **Session:** 2b complete → 3 next · **Work package:** 0A (item 6 first
test landed, item 3 findings actioned; item 4a next)

## Git

| | |
|---|---|
| Branch | `codex-PRD` |
| HEAD | `e9596f5` (upstream pin freeze) ← `8c85781` (UP-006) ← `9045ce5` (UP-005) ← `e394e742` (docs baseline) |
| Documentation baseline SHA | `e394e742783083070c5b618c4d6e9c2deb767aea` — committed 2026-08-09 |
| `origin/codex-PRD` | tracked; this session's 3 commits plus this doc-reconciliation commit not yet pushed at write time — see session-end report |
| `upstream/main` (reviewed) | `d12c110967fadbaa97fb2a108b71dd820a42e7ad` (2026-08-07, "Added privacy policy page") — **formally frozen**, see `UPSTREAM.md` Baseline table |
| Divergence `HEAD...upstream/main` | 1 ahead, 8 behind (unchanged; no upstream sync performed) |
| Merge rehearsal | never run |
| `ifgf/main` | does not exist yet |
| `deploy` | does not exist yet |

Working tree at write time: this doc-reconciliation commit (`CLAUDE.md`, `CONTEXT.md`,
`MEMORY.md`, `TODO.md`, `DEPENDENCY_INVENTORY.md`) is the only pending change — all Session 2b
code/dependency work is already committed (`9045ce5`, `8c85781`, `e9596f5`).

## Environment — installed and booting, unchanged from Session 2

| | Installed | Target |
|---|---|---|
| PHP | **8.3.33** (`C:\php\8.3`), 27/27 extensions | 8.4 at WP 0B |
| Composer | **2.10.2** | — |
| Laravel | **10.50.2** (reverified after both composer changes this session) | 13.x at WP 0B |
| Node | **22.15.0** ← wrong line, `nvm use` did not stick | 16.20.2 for Mix 4 |
| MySQL | client present | 8.4 LTS, not yet configured |
| `vendor/` | 183 packages (`composer show`, measured) — down from 194 at Session 2 end; UP-006 removed `botman/botman`, `botman/driver-web`, and 6 now-unused transitive dependents (`react/promise`, `react/event-loop`, `react/dns`, `react/cache`, `mpociot/pipeline`, `evenement/evenement`); `phpoffice/phpspreadsheet`'s UP-005 fan-out was version bumps only, no new packages | — |
| `.env` | created; `APP_KEY` generated, no DB configured | — |

`composer validate --strict` clean · `php artisan about` runs · `php artisan test` **broken**
(`nunomaduro/collision` v6.4.0 vs PHPUnit 10.5.63 — see `MEMORY.md` Session 2b) — use
`vendor/bin/phpunit` directly.

## Known debt

- **40 Composer advisories / 12 packages** (was 49/13 at Session 2 end; UP-005 cleared
  `phpoffice/phpspreadsheet`'s 9). `doctrine/annotations` still abandoned, no replacement.
- **166 npm vulnerabilities, 13 critical** (axios 0.18.1, Vue 2 EOL, Bootstrap 4.6 EOL) —
  untouched this session.
- `nunomaduro/collision`/PHPUnit 10 mismatch blocks `php artisan test` and, transitively, WP 0A
  item 7's CI workflow. Not yet scheduled — flagged in `TODO.md`.
- `app/Imports/UsersImport.php::collection()` fatal-errors on any nonempty import (undefined
  `$request`). `app/Traits/SendPushNotification.php` fatal-errors on any real call (dead
  `LaravelFCM\*` imports, never autoloaded). Both preexisting, both flagged in `TODO.md`, neither
  fixed — out of scope for the sessions that found them.
- Never run `npm audit fix` or unargumented `composer update` — both dissolve the baseline.

## Baseline shape

668 PHP files · 172 controllers · 79 models · 93 migrations · 317 Blade views · **1 test file, 1
test** (`tests/Feature/Admin/MemberImportCharacterizationTest.php`, Session 2b — first ever) ·
183 Composer packages · 52 npm packages · 5 route files (`web`, `admin`, `api`, `guestapi`,
`console`) · 0 remaining custom packages in `custompackages/` (brozot removed; `ifgf/` not yet
scaffolded — WP 0A item 11, Session 7).

Laravel 10.50.2 / PHP `^8.2` / Vue 2.6 / laravel-mix 4 / webpack 4.

## Graphify

`.graphifyignore` added and verified (WP 0A item 15, pulled forward from Session 5 at owner
request — done out of the planned order, Session 3's route/migration inventory has not started).
`graph.json` 15MB → 7.7MB (6919 nodes, down from an unrecorded higher count); `public/js/app.js`
and other compiled/vendored assets (`public/css/`, `public/audio/*.min.js`, `public/uploads/`,
`storage/{api-docs,app,debugbar,framework,logs}/`, `bootstrap/cache/`, `public/installer/`) no
longer indexed. Verified via `graphify query "member import controller Excel spreadsheet"` — every
result is real source (`ImportMemberController.php`, `config/excel.php`, `UPSTREAM.md`) or
first-party glue code kept by explicit negation (`public/js/custom.js`, `public/audio/app.js`).
Graph queries are now reliable — use them per `CLAUDE.md` search order. One open, non-blocking
oddity: `graphify update .` reports a stable `fail-closed: kept 3 node(s) from 1 file(s)` warning
across repeat runs; traced as far as confirming it isn't any `public/` file, not resolved further
— low priority, `graphify diagnose` or a `--force` rebuild is the next step if anyone picks it up.
`graphify-out/` stays untracked.

## Missing project files

`AGENTS.md` · `.graphifyignore` · `custompackages/ifgf/church-operations` · the other ~10 planned
characterization tests (auth/roles, groups/events, QR/attendance/exports/media — WP 0A item 6,
Sessions 9–11) · disposable MySQL 8.4 fixture DB (item 5, Session 12, replaces the sqlite
`:memory:` stopgap in `phpunit.xml`).

## Open gates

1. **WP 0A scope not confirmed** by owner (unchanged from Session 2).
2. `DEPENDENCY_INVENTORY.md`'s "Prioritize now" (`phpoffice/phpspreadsheet`) and both "remove"
   verdicts (`botman/*`, orphaned `brozot/laravel-fcm`) are **now actioned** (UP-005, UP-006) —
   this gate is closed. Remaining `DEPENDENCY_INVENTORY.md` upgrade/replace verdicts stay deferred
   to WP 0B as originally planned.
3. Route + migration inventory (WP 0A item 4a, Session 3) not yet started — see `TODO.md`.
4. **New:** three items moved to `TODO.md` "Decisions awaiting the owner" this session — the
   `UsersImport` crash, `SendPushNotification.php`'s dead FCM path, and collision/PHPUnit timing.
