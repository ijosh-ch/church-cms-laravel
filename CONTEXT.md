# CONTEXT

> Current state only. Rewritten every session end. Hard cap: 1,500 tokens.
> History belongs in `MEMORY.md`. Next actions belong in `TODO.md`.

**Updated:** 2026-08-10 · **Session:** 4 complete → 5 next · **Work package:** 0B substantially
complete (Laravel 13 + PHP 8.4 landed); WP 0A gates 3 and 5 still open

## Git

| | |
|---|---|
| Branch | `contrib/laravel-supported-platform` (14 commits ahead of `f2ad3bb`) |
| HEAD | `06b9766` (C4/C5/C6 + CI) ← `30db6c9` (L13) ← `32e65f9` (L12) ← `9a39cde` (L11) |
| `codex-PRD` | `086f33d` — Session 3 docs + gates 1/2/6/7 work, all committed |
| `ifgf/main` | **created** at `aa8194e` (= `main`), local only, not pushed |
| `main` | `aa8194e` — clean subset of `upstream/main`, no local commits |
| `upstream/main` | `d12c110` — **still frozen**, 8 commits ahead. Do not merge until WP 0A exit gate |
| `deploy` | does not exist (correct) |
| Pushed? | **Nothing pushed this session.** All 14 commits are local only. |

## Environment — upgraded to latest LTS

| | Installed | Active | Target |
|---|---|---|---|
| PHP | **8.4.24 only** (8.3 deleted) | **8.4.24** | ✅ done |
| Laravel | **13.24.0** | 13.24.0 | ✅ done |
| MySQL | **8.4.11 LTS** | 8.4.11 | ✅ done |
| Composer | 2.10.2 | — | — |
| Node | 22.15.0 | — | 16.20.2 for Mix 4 (unresolved) |
| PHPUnit | 11.x | — | — |

**PHP 8.4.24 is the resolved runtime** (`C:\php\8.4` first on the Machine PATH) and is pinned in
`composer.json` as `config.platform.php`. **`C:\php\8.3` was deleted 2026-08-10** once 8.4 was
verified serving real pages — there is no longer a local PHP rollback by PATH reorder. To roll
back, reinstall 8.3 from `windows.php.net` and re-run `tools\fix-php-ini.ps1 -PhpRoot C:\php\8.3`
(about two minutes; that is exactly how 8.4 was installed this session).

**Verified serving HTTP on 13.24.0 + 8.4.24:** `/login` and `/register` return 200 with rendered
pages against the seeded test database. `/` returns 500 — **pre-existing**, not an upgrade
regression: the QR backend needs the `imagick` extension, which has never been installed on this
machine and is not in the project's 27-extension list. `gd` is present. See `TODO.md`.

## Test and database

- **Disposable DB:** `churchcms_test_disposable` on MySQL 8.4.11, owned by user `iJosh`, scoped to
  that database only. Credentials in `.env.testing` (**gitignored**).
- `phpunit.xml` points at it via `APP_ENV=testing`; the sqlite `:memory:` stopgap is gone.
- `App\Providers\DatabaseSafetyServiceProvider` prints and asserts environment/driver/host/database
  before `migrate:fresh`/`db:wipe`/`migrate:reset` and refuses anything not provably disposable
  (`build.md` L515). Both paths verified.
- **`php artisan test` works** (collision 6→7→8, PHPUnit 11). **1 test, 1 passed, 4 assertions** —
  identical at Laravel 10, 11, 12, 13 and under PHP 8.4.
- **The suite takes ~8 minutes for that single test** — `RefreshDatabase` replays 93 migrations per
  test class. Fix with `php artisan schema:dump` **before** writing more tests.

## Known debt

- **No characterization tests** for the Release 1 surface (WP 0A item 6 / gate 5). The entire
  10→13 upgrade is verified against one import test. **This is the largest open risk.**
- **166 npm vulnerabilities** (17 low, 78 moderate, 58 high, 13 critical), unchanged. Vue 2 EOL
  with an unfixable ReDoS advisory. `npm run production` **still builds** (exit 0, 290s). Never run
  `npm audit fix`.
- `route:list` = 730 vs the inventory's 812 static declarations (12 commented; ~70 unexplained).
  Not proven upgrade-neutral — no pre-upgrade baseline exists.
- `app/Models/FeedbackMessage.php` references a non-existent `App\Presenters\UserPresenter`.
- `app/Imports/UsersImport.php::collection()` and `app/Traits/SendPushNotification.php` remain
  broken (pre-existing, both documented since Session 2b).
- `mysqldump` not on PATH → `schema:dump` unavailable locally.

## Baseline shape

Laravel **13.24.0** / PHP `^8.3` (pinned 8.4.24) / Vue 2.6 / laravel-mix 4 / webpack 4.
730 registered routes · 93 migrations · 1 test file · `.github/workflows/ci.yml` (new) ·
`app/Support/Presenter/` (new, replaces `laracasts/presenter`) ·
`custompackages/ifgf/church-operations` **still not scaffolded** (WP 0A item 11, gate 3).

## Open gates

1. **WP 0A gate 3** — no `custompackages/ifgf/church-operations`. Nowhere for IFGF code to live.
2. **WP 0A gate 5** — characterization tests. Deferred by owner directive; still the real gate.
3. **C7 not done** — `UPSTREAM.md` not updated for this session's upstream-owned file changes, and
   the merge rehearsal has never run.
4. Upstream still frozen at `d12c110`; 8 commits unmerged by design.
