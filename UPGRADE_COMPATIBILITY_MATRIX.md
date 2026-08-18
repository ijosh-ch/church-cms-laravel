# Upgrade compatibility matrix

**Assembled:** 2026-08-18 · **Work package:** 0B (executed 2026-08-10) · **Branch:** `contrib/laravel-supported-platform`
**Satisfies:** `build.md` WP 0A exit-gate criterion 7 ("the upgrade compatibility matrix is
reviewed") and WP 0B items 1, 3, 13, 14.

> **This document is compilation, not new investigation.** Every row is sourced from evidence
> already in the repository — `composer.lock`, `UPSTREAM.md` UP-001…UP-011, `MEMORY.md` 2026-08-10,
> `DEPENDENCY_INVENTORY.md` — and version columns were read from `composer.lock` on 2026-08-18
> rather than from memory. Where a claim rests on a support-lifecycle date recorded on 2026-08-10
> and not re-checked since, it is marked **[2026-08-10]**; `build.md` WP 0B item 2 requires
> re-verifying the official release tables at execution time, so re-check those before Phase 1E.

---

## 1. Selected targets and why

| Layer | Selected | Rationale | Evidence |
|---|---|---|---|
| **Laravel** | **13.24.0** | Newest stable major at execution time. Laravel 10 left security support Feb 2025, so the source baseline was unsupported. Security fixes through **2028-03-17** **[2026-08-10]**. | UP-007 · `build.md` TECHNICAL BASELINE 1–2 · PRD §13.1.13/.18 |
| **PHP** | **8.4.24**, pinned | Laravel 13 supports 8.3–8.5 **[2026-08-10]**. 8.3 loses *active* support **2026-11-23**; 8.4 has security to **Dec 2028** **[2026-08-10]**. 8.5 rejected: newest ≠ supported by every required extension/package (`build.md` WP 0B item 14). | UP-002 · `composer.json` `config.platform.php` · `composer.lock` `platform-overrides` |
| **MySQL** | **8.4.11 LTS** | 8.0 reached EOL April 2026 and is migration-source compatibility only. **9.x are Innovation releases, not LTS — do not target** **[2026-08-10]**. | `build.md` TECHNICAL BASELINE 3 |
| **PHPUnit** | **11.5.56** | Forced by `nunomaduro/collision` — see §3. | `composer.lock` |
| **Node / npm** | **22.15.0 / 11.4.2** | Only configuration in which `npm run production` is *observed* to succeed, now including Linux CI. Not a considered choice — see §5. | CI run `32090487802` |
| **Composer** | **2.10.2** | Host toolchain; not pinned. | `composer --version` |

**Pinning mechanism.** `composer.json` declares `"require": {"php": "^8.3"}` (the *supported range*,
kept wide so the package set stays installable) while `config.platform.php = 8.4.24` pins what the
solver resolves against. `composer.lock` carries `platform-overrides: {"php": "8.4.24"}`. CI runs PHP
8.4 to match. **The range and the pin are deliberately different values; do not "align" them.**

---

## 2. The traversal — one major at a time

`build.md` WP 0B item 2 requires reviewed, attributable steps rather than a single jump.
Four commits, each a separate solver resolution:

| Step | From → To | Commit | Blocker resolved at this step |
|---|---|---|---|
| 0 | — | `086f33d` | **`laravel/legacy-factories` removed.** Hard blocker for Laravel 11+; had to go *before* the framework moved. |
| 1 | 10.50.2 → **11.55.0** | `9a39cde` | — |
| 2 | 11.55.0 → **12.65.0** | `32e65f9` | **`phpunit/phpunit` ^10.5 → ^11.0**, forced by `collision` (§3). |
| 3 | 12.65.0 → **13.24.0** | `30db6c9` | **`laracasts/presenter` replaced in-house** — the sole hard blocker for 13 (§3). |
| — | platform pin + CI | `06b9766` | PHP pinned 8.4.24; CI workflow added. |

---

## 3. Package disposition — every package that blocked, moved, or was removed

| Package | Before | After | Disposition | Evidence / why |
|---|---|---|---|---|
| **`laracasts/presenter`** | 0.2.8 | **removed** | **replaced in-house** at `app/Support/Presenter/` (~60 lines, 4 files, behaviour-identical) | **The sole hard blocker for Laravel 13.** 0.2.8 is its newest release and stops at `illuminate/support ^12.0`. UP-007; owner decision 2026-08-10 #3 keeps the in-house version. |
| **`santigarcor/laratrust`** | 8.5.5 | 8.5.5 | **not a blocker** | ⚠ **An earlier reading that laratrust also blocked 13 was WRONG** — the solver was listing older 8.x versions. Installed **8.5.5 declares `^13.0`** (8.5.4 is the first that does). **Check the *installed* version's own `composer.json` before believing a solver summary.** |
| **`laravel/legacy-factories`** | present | **removed** | removed | Hard blocker for Laravel 11+; removed first, in its own commit. |
| **`nunomaduro/collision`** | v6.4.0 → **v8.9.5** | v8.9.5 | upgraded | ≥8.6 **conflicts with PHPUnit 10**, which forced `phpunit/phpunit` ^10.5 → ^11.0 at step 2. The first attempt **failed closed** on exactly this rather than silently downgrading — the no-forced-resolution rule surfacing a real constraint. |
| **`phpunit/phpunit`** | 10.5.x | **11.5.56** | upgraded | Consequence of `collision`, not an independent choice. |
| **`phpoffice/phpspreadsheet`** | 1.30.0 | **1.30.6** | upgraded, **test-first** | UP-005: characterization test written and green *before* the bump, re-run green after. `composer audit` 49/13 → 40/12 advisories; this package fully cleared. |
| **`symfony/yaml`** | 7.4.11 | **v7.4.15** | upgraded | UP-004 — 7.4.11 was one patch short of the CVE fix. |
| **`stripe/stripe-php`** | *absent from lock* | **v20.3.1** | lock repaired | UP-001 — upstream shipped a `composer.json`/`composer.lock` pair that could not install. |
| **`kreait/laravel-firebase`** | *absent from lock* | **7.2.1** | lock repaired | UP-001 |
| **`laravel-notification-channels/fcm`** | *absent from lock* | **6.1.0** | lock repaired | UP-001 |
| **`lcobucci/jwt`** | 4.3.0 (req `^5.2`) | **5.6.0** | lock repaired | UP-001 |
| **`botman/botman`**, `botman/driver-web` | present | **removed** | removed | UP-006 — zero call sites, reconfirmed before removal. |
| **`brozot/laravel-fcm`** (path repo) | orphaned | **removed** | removed | UP-006 — 73 files; `class_exists()` proved the `LaravelFCM\*` classes were never in the generated autoloader even before removal. |
| **`ifgf/church-operations`** | — | **0.1.0** | **added** (path repo) | UP-010 — the IFGF package seam. Not part of the upgrade; recorded here because it changes the root manifest. |
| `laravel/sanctum` · `spatie/laravel-medialibrary` · `maatwebsite/excel` · `laravel/ui` · `laravel/tinker` · `spatie/laravel-activitylog` · `darkaonline/l5-swagger` · `nckg/laravel-impersonate` · `barryvdh/laravel-dompdf` | — | v4.3.3 · 11.23.5 · 3.1.69 · v4.6.3 · v3.0.2 · 4.12.3 · 8.6.5 · 4.0.1 · v3.1.2 | **carried, no intervention** | Resolved cleanly at every major. |

**Current package count:** 153 production + 36 dev.

**Two process lessons recorded at the time, both still binding:**

1. **`composer require` silently moved runtime packages into `require-dev`** twice (at 10→11 and
   11→12) — the non-interactive prompt defaults to "no" on the move question and then writes to the
   wrong section anyway. **Edit `composer.json` directly for multi-package major bumps.** The 12→13
   step did exactly that and was clean.
2. **No `--ignore-platform-reqs`, no `--no-verify`, no forced resolutions** were used at any step
   (`CLAUDE.md` hard prohibitions). The `collision`/PHPUnit conflict is the proof this was honoured:
   it failed the build instead of hiding the incompatibility.

---

## 4. MySQL 8.4 LTS compatibility review — WP 0B item 13

| Area | Finding |
|---|---|
| **Authentication plugin** | MySQL 8.4 **disables `mysql_native_password` by default** **[2026-08-10]**. Check the application user's auth plugin before any further environment is provisioned. |
| **Upgrade direction** | **8.0 → 8.4 is one-way.** Dump before upgrading. The local 8.0→8.4.11 upgrade was done with a verified backup, since deleted after byte-identical verification. |
| **Windows host quirk** | `'root'@'localhost'` is not `'root'@'127.0.0.1'` — `localhost` means named-pipe/shared-memory. The original "failed connection" was a *missing account for that host*, not a wrong password. `--skip-grant-tables` disables networking entirely on Windows. |
| **Connection timezone** | Pinned to `'+00:00'` on the `mysql` connection (UP-009). Required regardless of which zone is chosen: without it the session inherits the server default, and the schema mixes 23 `timestamp` columns (which convert) with 10 `dateTime` (which do not) and 8 `date` — a mismatch shifts exactly half the schema. |
| **Reserved words, collation, generated columns, SQL mode, indexes** | **No blocking findings recorded.** All 93 migrations replay clean on 8.4.11 in CI (`migrate:fresh --seed`). |
| **Not done** | The MySQL upgrade checker has **not** been run against an anonymized production snapshot — no such snapshot exists in this workspace. WP 0B item 13 asks for the checker *or* an equivalent review; the CI migration replay is the equivalent used here. **Re-do properly at WP 0C, when real data is available.** |

---

## 5. Frontend — explicitly NOT upgraded

| | |
|---|---|
| **Stack** | Vue 2.6 · laravel-mix 4 · webpack 4 |
| **Status** | **Deferred by approved decision.** WP 0B item 8 permits deferral; the Vite migration is a separate IFGF-neutral change and must not be mixed with a framework upgrade. |
| **Build** | `npm run production` **passes on Linux CI** as of 2026-08-18 (run `32090487802`). It had been broken on Linux the entire time — **UP-011**, a directory-case defect. The long-standing "build still works (exit 0, 290s)" note was true only on Windows. |
| **Node compatibility** | Node 22 breaks webpack 4 on OpenSSL 3. `package.json`'s `production` script already carries `NODE_OPTIONS=--openssl-legacy-provider`; do not add it a second time in CI. |
| **Known debt** | **166 npm advisories** (17 low / 78 moderate / 58 high / 13 critical) and Vue 2 EOL with an **unfixable ReDoS advisory**. Tracked in `DEPENDENCY_INVENTORY.md`. **Never run `npm audit fix`** — it dissolves the pinned baseline. |

---

## 6. Verification evidence

All from CI run **`32090487802`** (2026-08-18), clean Ubuntu checkout, both jobs green:

- `composer validate --strict` — clean
- `composer install` from the committed lockfile — no platform-requirement overrides
- `composer audit` — reported, non-blocking by policy (backlog in `DEPENDENCY_INVENTORY.md`)
- PSR-4 autoload compliance — clean (the check that exists because of UP-003)
- `migrate:fresh --seed` on MySQL 8.4 — 93 migrations
- `php artisan test` — 48 passed, 108 assertions
- package suite — 3 passed
- `npm ci` + `npm run production` — success
- **merge rehearsal** against `upstream/main` `800c29f` — clean merge, all suites green on the merged tree

---

## 7. What this matrix does NOT establish

Stated plainly, because a matrix that reads as complete when it is not is worse than no matrix:

1. **It is not behavioural proof.** WP 0B item 6 (characterization tests) was **skipped by explicit
   owner directive mid-session** on 2026-08-10. Coverage has since reached **37 characterization
   tests across 4 of 11 planned suites** against an 80–120 target. The platform is verified to
   *install, migrate, boot and build* — **not** to *behave identically to Laravel 10*. **Exit-gate
   criterion 2 remains unmet and nothing in this document changes that.**
2. **Support-lifecycle dates marked [2026-08-10] have not been re-verified.** `build.md` WP 0B
   item 2 requires re-checking the official release tables at execution time. Do that before
   Phase 1E.
3. **No anonymized production snapshot has been tested** — see §4's "Not done" row.
4. **The route count is unreconciled.** `route:list` reports 730 against the inventory's 812 static
   declarations; 12 are commented out and ~70 remain unexplained, with **no pre-upgrade baseline to
   diff against**. This is **not proven upgrade-neutral**.
5. **UP-007's own status still reads "applied 2026-08-10; NOT yet verified by characterization
   tests"** for the six upstream-owned authorization-surface files it touched. That belongs to
   exit-gate criterion 4 and is itself blocked on criterion 2.

**Reviewer's note.** The honest summary of this matrix is: *the dependency and platform story is
complete, attributable and reproducible on a clean Linux checkout; the behavioural story is not.*
Criterion 7 asks whether the compatibility work was reviewed, and it now can be. It does not ask
whether the upgrade is safe — criterion 2 asks that, and still answers no.
