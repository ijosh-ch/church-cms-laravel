# MEMORY

> Append-only. Newest entry at the top. One entry per session.
> Sessions read the **last 5 entries only** — never the whole file.
> Record what was learned and what failed, not what was written. `git log` already has the diff.

---

## 2026-08-09 — Session 2b — UP-005, UP-006, upstream pin: three owner-approved decisions actioned

**Work package:** 0A item 6 (first characterization test) + item 3 findings actioned · **Branch:**
`codex-PRD` · **HEAD:** `e9596f5` (preceded by `8c85781`, `9045ce5`)

**Done**

- **UP-005.** Stood up `tests/` from nothing — this repo had zero tests, no `TestCase.php`, no
  `CreatesApplication.php`, no suite directories. Wrote
  `tests/Feature/Admin/MemberImportCharacterizationTest.php` covering
  `ImportMemberController.php:56` → `Excel::import()`, proved it green against pinned
  `phpoffice/phpspreadsheet` 1.30.0, ran `composer update phpoffice/phpspreadsheet
  --with-dependencies` → 1.30.6, re-ran the same test green and unchanged. `composer audit`:
  49/13 → 40/12 advisories, `phpoffice/phpspreadsheet` fully cleared. `laravel/framework`
  confirmed still 10.50.2 throughout.
- **UP-006.** Removed `botman/botman` + `botman/driver-web` (zero call sites, reconfirmed) and the
  orphaned `custompackages/brozot/laravel-fcm` (73 files) plus its `composer.json` `repositories`
  entry. Did the required residual-reference grep first, found a real textual hit in
  `app/Traits/SendPushNotification.php`, then proved via `class_exists()` that the referenced
  `LaravelFCM\*` classes were never in the generated autoloader even before removal — so no
  characterization test was required under `TODO.md`'s own "if reachable" rule.
- Formally froze the upstream pin at `d12c110` in `UPSTREAM.md`'s Baseline table — no merge of the
  8 outstanding upstream commits until the WP 0A exit gate passes.
- Committed Session 2's own pending doc baseline (`CLAUDE.md`, `CONTEXT.md`, `MEMORY.md`,
  `TODO.md`, `DEPENDENCY_INVENTORY.md`), which had sat uncommitted since that session ended.

**Learned — carry forward**

- **`php artisan test` is broken, independent of anything touched this session.** Installed
  `nunomaduro/collision` v6.4.0 is incompatible with installed PHPUnit 10.5.63
  (`RequirementsException`: "Running PHPUnit 10.x or Pest 2.x requires Collision 7.x"). Worked
  around it by running `vendor/bin/phpunit` directly. Not fixed here — bumping `collision` is a
  separate package change outside UP-005's "targeted only" scope, but it blocks WP 0A item 7 (CI
  workflow) until resolved. Fix it in Session 13 or earlier if it starts costing round-trips.
- **`app/Imports/UsersImport.php`'s `collection()` method is broken for any real import.** It
  dereferences an undefined `$request` variable as soon as `count($rows) > 0` — `"Attempt to
  assign property ... on null"`, a PHP `Error`, not caught by its own `catch (Exception $e)`.
  Discovered while designing UP-005's fixture; deliberately used a **header-only** CSV (zero data
  rows) to characterize the `Excel::import()`/phpspreadsheet boundary without also characterizing
  this unrelated, preexisting crash. Member import is effectively non-functional today for any
  file with actual data. Flagged in `TODO.md` "Decisions awaiting the owner" — not fixed.
- **`app/Traits/SendPushNotification.php`'s FCM push path has been dead since before this fork's
  IFGF work started, unrelated to UP-006.** It imports and instantiates
  `LaravelFCM\Message\OptionsBuilder`/`PayloadNotificationBuilder`, but
  `grep -n "LaravelFCM" vendor/composer/autoload_psr4.php` never matched, and
  `class_exists('LaravelFCM\Message\OptionsBuilder')` returned `false` even before
  `custompackages/brozot/laravel-fcm` was deleted — `brozot/laravel-fcm` was declared as a path
  `repositories` entry but never actually `require`d, so it was never wired into the autoloader.
  Any call to `sendNotification()` has been throwing a fatal `Error` in production. Left untouched
  (fixing it is a real application-behavior change to an upstream-owned file, needs its own
  UPSTREAM.md entry + characterization test) — flagged in `TODO.md`.
- **SQLite `:memory:` is sufficient, and `build.md`-permitted, for tests that don't touch
  MySQL-specific features.** Used it as a stopgap in `phpunit.xml` for UP-005's test since WP 0A
  item 5's disposable MySQL 8.4 fixture database doesn't exist yet. All 93 migrations ran clean
  against sqlite with zero MySQL-only syntax found (`DB::statement`, `->change()`, `ENGINE=`,
  `FULLTEXT` all absent from every migration file, checked before trusting sqlite at all). Item 5
  should replace this, not just add to it, once it lands (Session 12).
- **A header-only fixture (template headers, zero data rows) is a clean way to characterize an
  `Excel::import()`/phpspreadsheet upgrade in isolation** from unrelated business-logic bugs
  downstream of the parse. Worth reusing for the other 4 `Imports` classes
  (`DEPENDENCY_INVENTORY.md`: Attendance, Subscribers, Summary, plus this one) when WP 0A item 6
  reaches them.
- **`withoutMiddleware()` + `actingAs()` is the right scope for a dependency-upgrade
  characterization test**, not the full `web`/`auth`/`churchadmin`/`permission:read-members`
  middleware stack — that coverage belongs to WP 0A item 6's own "authentication, existing roles
  and direct permissions" bullet as its own future test, not bundled into UP-005.

**Open / not done**

- WP 0A item 4a (route + migration inventory) — Session 3, now the literal next action in
  `TODO.md`.
- Three items moved to `TODO.md` "Decisions awaiting the owner": the `UsersImport::collection()`
  crash, `SendPushNotification.php`'s dead FCM imports, and whether/when to fix the
  `collision`/PHPUnit mismatch ahead of Session 13's CI workflow.
- Did not touch `vendor/`, `node_modules/`, `yarn.lock`, or `package-lock.json` beyond what
  `composer remove`/`composer update phpoffice/phpspreadsheet`/`composer update --lock` touched.

---

## 2026-08-09 — Session 2 — Baseline committed, dependency inventory complete

**Work package:** 0A item 3 (dependency inventory), plus finishing item 2 (doc baseline commit) ·
**Branch:** `codex-PRD` · **HEAD:** `e394e74` (docs baseline) preceded by `4e25e04` (generic repair)

**Done**

- Committed the two Session-1-blocked commits: `4e25e04` (composer.json/composer.lock/factory
  rename) and `e394e74` (PRD.md, build.md, hosting.md, UPSTREAM.md, EXECUTION_PLAN.md, CLAUDE.md,
  CONTEXT.md, MEMORY.md, TODO.md, .gitignore, tools/). **Gate 1 (documentation baseline
  uncommitted) is now closed.** Recorded the docs-baseline SHA in `CONTEXT.md`.
- Produced `DEPENDENCY_INVENTORY.md` — all 194 Composer packages and 51 npm direct packages
  classified keep/upgrade/replace/remove, and the 49 Composer advisories (13 packages) triaged
  by real exposure (grep'd actual call sites in `app/`), not severity label alone.
- Verified the exact Laravel 13 artisan generator flags against a real
  `composer create-project laravel/laravel "^13.0"` skeleton (13.24.0) and recorded them in
  `CLAUDE.md` — not guessed from memory.

**Learned — carry forward**

- **UP-003's rename never actually reached git's index.** The working-tree file was correctly
  cased (`EventGalleryFactory.php`) but `git status` showed no change at all, because this
  checkout has `core.ignorecase=true`: Windows silently absorbed the case-only rename from
  Session 1b without git noticing. `UPSTREAM.md` had every regression checkbox ticked, but the
  commit would have shipped the broken lowercase filename to the Linux VPS. Fixed by `git mv`
  through a **distinct intermediate filename** (not a case-only intermediate) —
  `EventgalleryFactory.php` → `EventGalleryFactory_tmp_rename.php` → `EventGalleryFactory.php` —
  which forces git to register two real path changes even under `core.ignorecase`. **Any future
  case-only rename on this checkout needs the same two-hop trick with a non-case-only
  intermediate name**, not the same-case-different-case two-step `build.md`/`UPSTREAM.md`
  describe (that version silently no-ops here).
- **This tool is not sandboxed the way Session 1/1b's was.** `php`, `composer`, and `curl` to
  `packagist.org`/`repo.packagist.org` all worked directly with no host relay. `CLAUDE.md`'s "PHP
  does not run in the sandbox" section and `EXECUTION_PLAN.md` §3.4's round-trip-cost model are
  written for whatever ran Session 1/1b (probably a network-sandboxed container) and do not apply
  to Claude Code running directly on this Windows host. Flagged inline in `CLAUDE.md`; did not
  rewrite the cost model itself — re-verify at the start of whichever session reads this.
- **Two findings beyond `EXECUTION_PLAN.md` §1.4's own predictions**, both now in
  `DEPENDENCY_INVENTORY.md`: (1) `botman/botman`+`botman/driver-web` have **zero** call sites
  anywhere in `app/`/`config/`/`routes/` — not just "high risk," dead code, remove outright.
  (2) `custompackages/brozot/laravel-fcm` is an **orphaned path repository** — the directory and
  the `composer.json` `repositories` entry both still exist, but nothing actually requires the
  package anymore (superseded by `laravel-notification-channels/fcm`, already installed). It is
  not one of the 194 resolved packages, so `composer show` won't surface it — only a
  `composer.json`/filesystem cross-check catches it.
- **Real-exposure triage flipped priority away from severity label** in two directions:
  `phpoffice/phpspreadsheet` (9 advisories, 2 critical) has a **confirmed live sink** —
  `ImportMemberController.php:56` calls `Excel::import()` on an admin-uploaded file — so it
  should be fixed before WP 0B, not deferred with the rest of the compatibility work. Conversely
  `dompdf/dompdf` (6 advisories) and `spatie/browsershot` (6 advisories) have **zero** call sites
  anywhere in `app/` or `resources/views/` — present, patchable, but not urgent.
- **npm's 166 vulnerabilities are almost entirely one root cause.** Nearly all critical/high
  entries live inside the `laravel-mix`/`webpack@4` toolchain's own transitive dependencies —
  patching them individually is wasted effort; they leave with the WP 0B Vite migration. The
  npm-specific exceptions worth fixing *now*, independent of that timeline: `axios` (0.18→1.x,
  high, cheap) and `lodash` (high, cheap). `vue-image-lightbox-carousel` is critical **and**
  confirmed still in active use — replace, don't just delete the feature.
- `@dymantic/vue-trix-editor` (high severity, unmaintained) and `@tiptap/*` (current, Vue-3-ready)
  are both actively referenced in the Vue components right now — the app is mid-migration from
  Trix to TipTap. Finish it and drop the Trix wrapper rather than patching it.

**Open / not done**

- WP 0A item 3 exit criteria met for this session's scope; **owner should review
  `DEPENDENCY_INVENTORY.md`'s "Prioritize now" row (phpoffice/phpspreadsheet) before WP 0A
  Session 3 starts**, since it argues for pulling that one upgrade earlier than WP 0B.
- Confirm during WP 0A item 4 (route inventory, Session 3) whether QR/attendance check-in uses
  `temporarySignedRoute` — the one `signedRoute` call site found needs to be checked against the
  `laravel/framework` signed-URL-confusion advisory.
- `custompackages/brozot/laravel-fcm` orphan removal and `botman/*` removal are recommended but
  **not executed this session** — they are dependency changes, out of scope for an inventory-only
  session, and need an explicit commit of their own with the owner's sign-off.
- Did not touch `vendor/`, `node_modules/`, `composer.lock`, or `package-lock.json` — this session
  was inventory and classification only, no upgrades applied.

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
