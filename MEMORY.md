# MEMORY

> Append-only. Newest entry at the top. One entry per session.
> Sessions read the **last 5 entries only** — never the whole file.
> Record what was learned and what failed, not what was written. `git log` already has the diff.

---

## 2026-08-08 — Session 1b — Toolchain installed, three upstream defects fixed

**Work package:** 0A items 1–2 · **Branch:** `codex-PRD` · **HEAD:** `d8cfe08` (uncommitted work)

**Done — the baseline now installs**

- PHP **8.3.33** at `C:\php\8.3`, all **27 required extensions** loading, no startup warnings.
- Composer **2.10.2**. Node 22.15.0 (wrong line — see below). MySQL present.
- `npm ci` — 1,286 packages installed.
- `composer install` — 194 packages, autoload generated, 21 packages discovered.
- `composer validate --strict` → **valid**. `php artisan about` → **Laravel 10.50.2 / PHP 8.3.33**.
- Framework confirmed **unchanged at 10.x** — WP 0B was not started by accident.
- Logged UP-001, UP-002, UP-003 in `UPSTREAM.md`; created that file.

**Learned — carry forward**

- **Upstream ships a repository that cannot install.** Three independent defects, all present
  identically on `HEAD`, `upstream/main` and `origin/main`:
  - **UP-001** — `composer.lock` desynced from `composer.json`: stripe, kreait/laravel-firebase
    and the fcm channel absent from the lock; `lcobucci/jwt` locked at 4.3.0 against `^5.2`.
  - **UP-002** — three packages locked at **PHP ≥ 8.4-only versions** in a `php: ^8.2` project:
    `symfony/css-selector` v8.0.9, `symfony/filesystem` v8.0.11, `lcobucci/clock` 3.6.0. Symfony
    was split 13 components on 6.x, 3 on 7.x, 2 on 8.x. Masked by UP-001 — the out-of-date-lock
    error fires before Composer reaches the platform check.
  - **UP-003** — `database/factories/EventgalleryFactory.php` declares `class EventGalleryFactory`.
    PSR-4 case mismatch, so the class is **excluded from the autoloader**. Only occurrence in the
    repo; `app/` is clean. Hidden by case-insensitive Windows; will bite on the Linux VPS.
- All three are the residue of partial `composer update` runs committed without a clean-checkout
  verification. **A CI job doing `composer install` from a clean checkout on Linux would have
  caught all three** — argues for pulling WP 0A item 7 much earlier than Session 13.
- **Enumerate, don't discover.** Fixing UP-002 one failed resolution at a time cost three
  round-trips. Scanning the whole lock for PHP-constraint conflicts found all three instantly.
  Prefer a whole-file scan over iterative failure whenever the search space is enumerable.
- Added `config.platform.php = 8.3.33` to `composer.json` so resolution stops depending on the
  developer's local PHP. Also satisfies WP 0B item 14. **Revisit when production moves to 8.4.**
- Fix command that worked:
  `composer update stripe/stripe-php kreait/laravel-firebase laravel-notification-channels/fcm
  lcobucci/jwt lcobucci/clock symfony/css-selector symfony/filesystem --with-dependencies`

**Windows toolchain gotchas — do not rediscover**

- winget's `PHP.PHP.8.3` manifest points at 8.3.32, which has moved to the archives and 404s.
  PHP is downloaded directly from windows.php.net instead (`tools/setup-windows.ps1` probes URLs).
- Composer's winget id is **`GetComposer.Composer`**, not `Composer.Composer`. `Laravel.Herd`
  does not exist in winget.
- `opcache` is a **zend_extension**, not `extension`. `bcmath` and friends have no `php_*.dll` on
  Windows — enumerate `ext\php_*.dll` rather than hardcoding a wishlist (`tools/fix-php-ini.ps1`).
- `Set-ExecutionPolicy -Scope Process` does not survive a new shell. Owner set `CurrentUser`.

**Open / not done**

- **UP-003 not yet fixed** — the factory rename still pending.
- **Node is 22.15.0**, not 16.20.2; `nvm use` did not stick. `npm ci` worked anyway, but
  `npm run production` is where webpack 4 will break.
- **52 Composer advisories / 14 packages**; **166 npm vulnerabilities, 13 critical**. Triage in
  Session 2. Do **not** run `npm audit fix` or unargumented `composer update`.
- `.env` was created from `.env.example` but **`APP_KEY` has not been generated** and no database
  has been configured. Nothing has been committed.

---

## 2026-08-08 — Session 1 — Requirements audit and execution planning

**Work package:** pre-0A · **Branch:** `codex-PRD` · **HEAD:** `d8cfe08`

**Done**

- Audited installed requirements against `composer.json` and `package.json`. Verdict: nothing
  installed — no PHP, Composer, MySQL, `vendor/`, `node_modules/`, or `.env`.
- Confirmed Laravel 13 is the newest stable major on 2026-08-08 and requires PHP ≥ 8.3, as
  `build.md` TECHNICAL BASELINE 2 requires be rechecked at execution time.
- Created `EXECUTION_PLAN.md`, `CONTEXT.md`, `TODO.md`, `MEMORY.md`, `CLAUDE.md`,
  `tools/setup-windows.ps1`.
- Added `/graphify-out` to `.gitignore` per OPERATING CONTRACT 9.

**Learned — carry forward**

- **The assistant sandbox cannot run PHP at all.** No root; `packagist.org`, `php.net`,
  `getcomposer.org`, `api.github.com`, `codeload.github.com` are all outside the network
  allowlist. Only `github.com` and `registry.npmjs.org` resolve. Every PHP command must run on the
  Windows host with its output pasted back. Do not spend tokens re-testing this.
- **`tests/` contains zero files.** The characterization suite required by WP 0A item 6 is being
  written from nothing, not extended. This is the single largest driver of the schedule estimate.
- **Reading `PRD.md` + `build.md` + `hosting.md` whole costs 53,786 tokens** — 36% of a 150k
  session. Measured, not estimated. Use the line index in `EXECUTION_PLAN.md` Appendix A.
- **PHP 8.3 is the only version spanning both ends of the upgrade.** Laravel 10 supports 8.1–8.3;
  Laravel 13 requires ≥8.3. Installing 8.3 now avoids a second PHP install at WP 0B.
- **`laravel/legacy-factories` v1.4.2 is a hard Laravel 13 blocker** — Laravel 10 max. Factories
  must be rewritten. Also flagged: `botman/botman` 2.8.11, `spatie/laravel-medialibrary` 10.15,
  `laravel/sanctum` 3.3.3, `nunomaduro/collision` 6.4, `santigarcor/laratrust` 7.2.1,
  `doctrine/annotations` 2.0.2 (abandoned upstream).
- **Node 22.22.3 is installed but wrong** for laravel-mix 4 / webpack 4. Pin 16.20.2 via
  nvm-windows until the Vite migration lands in WP 0B.
- **Graphify output is stale and noisy** — it indexes compiled `public/js/app.js`, so architecture
  queries return generated bundles. Do not trust graph results until `.graphifyignore` lands
  (WP 0A Session 5).
- Upstream is 8 commits ahead of `HEAD`; newest reviewed upstream commit is `d12c110`
  (2026-08-07, "Added privacy policy page"). No merge rehearsal has ever been run.

**Blocked**

- Documentation baseline is uncommitted, so `build.md` OPERATING CONTRACT 6 blocks all coding.
- No toolchain, so no `composer install`, no `artisan`, no tests.

**Not done — deliberately**

- No dependency install, no upgrade, no schema change, no commit. WP 0A item 2 installs from
  lockfiles without updating; all upgrades belong to WP 0B behind its own approval gate.
