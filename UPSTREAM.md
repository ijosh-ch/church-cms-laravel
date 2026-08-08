# UPSTREAM.md — compatibility ledger

> Every upstream-owned file this fork changes, and why. Required by `build.md`
> OPEN-SOURCE ADAPTATION STRATEGY 9 and WORK PACKAGE 0A item 10.
>
> **Upstream:** `https://github.com/church-cms/church-cms-laravel`
> **Fork:** `https://github.com/ijosh-ch/church-cms-laravel`
>
> A file is **upstream-owned** if it exists in `upstream/main`. Changing one requires an entry
> here plus a characterization test. IFGF behavior belongs in
> `custompackages/ifgf/church-operations` and never appears in a contribution branch.

## Baseline

| | |
|---|---|
| Reviewed `upstream/main` SHA | `d12c110967fadbaa97fb2a108b71dd820a42e7ad` |
| Upstream commit date | 2026-08-07 — "Added privacy policy page" |
| Fork HEAD at time of writing | `d8cfe08b617351ed18945ab4b1c56a396d6d8a46` |
| Divergence | 1 ahead, 8 behind |
| Last merge rehearsal | never run |

## Disposition vocabulary

| Value | Meaning |
|---|---|
| `contribute` | Generic. Extract to `contrib/*` off `upstream/main` and offer upstream. |
| `carry` | Generic but not yet offered. Maintained downstream patch; must replay cleanly on each sync. |
| `ifgf-only` | Church-specific. Never contributed. Should be migrated into the package if possible. |
| `retired` | Accepted upstream or otherwise no longer needed downstream. |

---

## Entries

### UP-001 — Reconcile `composer.lock` with `composer.json`

| Field | Value |
|---|---|
| **Status** | **applied and verified 2026-08-08** |
| **Files** | `composer.lock` (upstream-owned) |
| **Work package** | 0A, required item 2 |
| **Disposition** | `carry` — contribution-eligible, deferred by owner decision 2026-08-08 |
| **Conflict risk** | **High.** Lockfiles conflict on almost every upstream sync. Expect to regenerate rather than merge. |

**Problem**

`upstream/main` ships a `composer.json` / `composer.lock` pair that cannot install. `composer
install` aborts and `composer validate --strict` reports:

- `stripe/stripe-php` — required at `^20.2`, **absent from the lock**
- `kreait/laravel-firebase` — required at `^5.10`, **absent from the lock**
- `laravel-notification-channels/fcm` — required at `^4.5`, **absent from the lock**
- `lcobucci/jwt` — required at `^5.2`, **locked at 4.3.0**

Verified identical on `HEAD`, `upstream/main`, and `origin/main` — all three carry lock
content-hash `971ddb3ae6eb7b4949d433142be79d03`. **This is an upstream defect, not fork drift.**
`composer.json` gained Stripe in `ef480b8` ("Changes donation & Payment gateway") and
Firebase/FCM in `3a3c666` ("Changes push notification"); the lock was never regenerated.

**Alternatives considered**

| Option | Rejected because |
|---|---|
| Revert `composer.json` to match the lock | `app/Services/Payment/StripeService.php` and `app/Notifications/SendDeviceNotification.php` reference the missing packages. Reverting breaks working code. |
| Full `composer update` | Destroys the pinned baseline before any characterization test exists, making upgrade regressions indistinguishable from pre-existing bugs. That work is Work Package 0B. |
| `--ignore-platform-reqs` | Prohibited — `build.md` TECHNICAL BASELINE 5. Hides incompatibility rather than resolving it. |
| Leave as a documented blocker | Permitted by the 0A exit gate, but blocks all PHP work indefinitely. |

**Chosen fix**

```
composer update stripe/stripe-php kreait/laravel-firebase \
                laravel-notification-channels/fcm lcobucci/jwt --with-dependencies
```

Touches only the four desynced packages and their direct dependencies. The other 179 stay at
their locked versions, so the characterization baseline remains meaningful.

**Regression tests required before this entry is closed**

- [x] `lcobucci/jwt` satisfies `^5.2` — **5.6.0** (was 4.3.0)
- [x] `composer validate --strict` clean — *"./composer.json is valid", 2026-08-08*
- [x] `composer install` resolves — *194 packages, autoload generated, 21 discovered*
- [x] `php artisan about` runs — *ChurchCMS, Laravel 10.50.2, PHP 8.3.33, Composer 2.10.2*
- [x] `laravel/framework` remains 10.x — *10.50.2, unchanged from the original lock*
- [ ] Characterization coverage for the Stripe payment path *(WP 0A item 6)*
- [ ] Characterization coverage for the FCM device-notification path *(WP 0A item 6)*

**Notes**

Do not resolve a future lockfile merge conflict by hand. Take upstream's `composer.json`, then
regenerate the lock and re-verify this entry.

See **UP-002** — the first attempt at this fix surfaced a second, independent upstream defect.

---

### UP-002 — Correct three stranded PHP-8.4-only packages and pin the Composer platform PHP

| Field | Value |
|---|---|
| **Status** | **applied and verified 2026-08-08** |
| **Files** | `composer.json` (upstream-owned), `composer.lock` (upstream-owned) |
| **Work package** | 0A item 2; platform pin also satisfies 0B item 14 |
| **Disposition** | `carry` — contribution-eligible, deferred by owner decision 2026-08-08 |
| **Conflict risk** | Low for `composer.json` (additive `config.platform` block). High for `composer.lock`. |

**Problem**

`upstream/main` locks **three packages that require PHP >= 8.4**, in a project whose own
`composer.json` declares `php: ^8.2`:

| Package | Locked | Requires | Reached via |
|---|---|---|---|
| `symfony/css-selector` | v8.0.9 | `>=8.4` | `tijsverkoyen/css-to-inline-styles` v2.4.0 |
| `symfony/filesystem` | v8.0.11 | `>=8.4` | `league/flysystem-aws-s3-v3` → `aws/aws-sdk-php` |
| `lcobucci/clock` | 3.6.0 | `~8.4.0 \|\| ~8.5.0` | `lcobucci/jwt` ^5.2 |

This list is complete — every locked package was scanned for a PHP constraint excluding 8.3.

The Symfony spread in the same lock shows the incoherence plainly:

| Major | Count | Components |
|---|---:|---|
| 6.x | 13 | console, http-foundation, http-kernel, mailer, mime, routing, … |
| 7.x | 3 | event-dispatcher, string, yaml |
| **8.x** | **2** | **css-selector, filesystem** ← stranded |

Laravel 10.50.2 pairs with Symfony 6.4. Two components three majors ahead of their siblings is not
a deliberate choice — it is the residue of a partial `composer update` run on a PHP 8.4+ machine
and committed without a clean-checkout verification, the same class of defect as UP-001.

Consequence: **upstream's lock cannot install on PHP 8.3 at all.** This is distinct from UP-001 and
was masked by it — the out-of-date-lock error fires before Composer reaches the platform check.

**Alternatives considered**

| Option | Rejected because |
|---|---|
| Install PHP 8.4 to satisfy the lock as-is | Accommodates an accident instead of correcting it, and leaves the Laravel 10 baseline internally inconsistent. Defensible — 8.4 is the eventual production target — but it front-loads a Work Package 0B decision into 0A and adds PHP 8.4 deprecation noise to the characterization run. |
| Downgrade `tijsverkoyen/css-to-inline-styles` | Wrong layer. That package is fine; its dependency is the problem. |
| Leave it and pin platform only | The platform pin alone cannot resolve — css-selector v8.0.9 is *locked*, so Composer must be explicitly permitted to move it. |

**Chosen fix**

1. Add an explicit platform pin to `composer.json`, so resolution no longer depends on whichever
   PHP the developer's machine happens to run. Required independently by `build.md` WP 0B item 14.

   ```json
   "config": { "platform": { "php": "8.3.33" } }
   ```

2. Add all three stranded packages to the UP-001 update set so they can settle onto versions
   consistent with Symfony 6.4 and PHP 8.3:

   ```
   composer update stripe/stripe-php kreait/laravel-firebase \
                   laravel-notification-channels/fcm lcobucci/jwt \
                   lcobucci/clock symfony/css-selector symfony/filesystem \
                   --with-dependencies
   ```

**Regression tests required before this entry is closed**

- [x] Install succeeds under PHP 8.3.33
- [x] `laravel/framework` remains 10.x — *10.50.2, unchanged*
- [x] Resolved versions recorded, 2026-08-08:

      | Package | Before | After |
      |---|---|---|
      | `symfony/css-selector` | v8.0.9 (php >=8.4) | **7.4.9** |
      | `symfony/filesystem`   | v8.0.11 (php >=8.4) | **7.4.15** |
      | `lcobucci/clock`       | 3.6.0 (php ~8.4) | **no longer installed** — see note |

- [x] `lcobucci/clock` confirmed absent — `composer why` reports *"Could not find package
      lcobucci/clock in your project"*. Expected: `lcobucci/jwt` 5.x replaced the concrete
      `lcobucci/clock` dependency with the PSR-20 `psr/clock` interface, so its disappearance is
      **evidence the jwt 4.3.0 → 5.x upgrade landed correctly**, which was UP-001's purpose.
      **Confirmed 2026-08-08:** `lcobucci/jwt` **5.6.0** and `psr/clock` **1.0.0** are installed.
- [ ] `composer install` succeeds on a **clean checkout** with no `vendor/` *(CI, WP 0A item 7)*

**Note on the resulting Symfony spread**

The two corrected packages landed on **7.4.x**, not 6.4.x, so the lock now mixes 6.4 and 7.4
components. Composer verified the constraint set and the application boots, so this is valid —
Symfony components are independently versioned and Laravel 10 accepts `^6.2 || ^7.0` for most.
Record it as a known characteristic of the baseline rather than a defect, and re-check it during
the WP 0B upgrade, where the whole set should converge.
- [ ] Characterization coverage for any mail path using inlined CSS *(WP 0A item 6)*
- [ ] Characterization coverage for S3 private-media writes via flysystem *(WP 0A item 6)*

**Notes**

Revisit the platform pin at Work Package 0B, when the production PHP moves to 8.4. The pin is a
resolution constraint, not a runtime guard — it must be updated deliberately, not drift.

---

### UP-003 — PSR-4 filename case mismatch breaks `EventGalleryFactory` autoloading

| Field | Value |
|---|---|
| **Status** | **applied and verified 2026-08-08** |
| **Files** | `database/factories/EventgalleryFactory.php` → `EventGalleryFactory.php` (upstream-owned) |
| **Work package** | 0A item 6 (characterization); blocks any factory use in 0C |
| **Disposition** | `carry` — strong contribution candidate, trivially generic |
| **Conflict risk** | Low. A rename, no content change. |

**Problem**

`composer dump-autoload` reports:

```
Class Database\Factories\EventGalleryFactory located in
./database/factories/EventgalleryFactory.php does not comply with
psr-4 autoloading standard. Skipping.
```

The file is named `Eventgallery…` (lowercase `g`); the class inside is `EventGalleryFactory`
(uppercase `G`). Under PSR-4 the class is **excluded from the autoloader**, so any reference to
`EventGalleryFactory` raises "class not found".

Verified as the only occurrence in the repository — all `app/` classes and every other factory
match their filenames.

**Why this matters more than it looks**

Windows and macOS default to case-insensitive filesystems, so some code paths appear to work
locally. `hosting.md` targets a **Linux VPS**, which is case-sensitive. This is exactly the class
of defect that passes on a developer machine and fails in production.

**Fix**

```
git mv database/factories/EventgalleryFactory.php database/factories/EventGalleryFactory.tmp
git mv database/factories/EventGalleryFactory.tmp database/factories/EventGalleryFactory.php
composer dump-autoload
```

The two-step rename is required because Git on a case-insensitive filesystem ignores a
case-only rename.

**Regression tests required before this entry is closed**

- [x] `composer dump-autoload` emits no PSR-4 warning — *2026-08-08*
- [x] Autoloaded class count **15066 → 15067** — the one previously skipped class is now mapped
- [ ] `EventGalleryFactory` instantiates in a test *(WP 0A item 6)*
- [ ] Add a CI check that fails on any PSR-4 autoload warning *(WP 0A item 7)*

---

## Approved upstream-owned touchpoints (pre-authorized by `build.md`)

These are expected and do not each need a new entry, but every actual change does.

| File | Reason | `build.md` |
|---|---|---|
| `composer.json` | Path repository + require entry for `ifgf/church-operations` | WP 0A item 11 |
| `composer.lock` | Resolution of the package seam | WP 0A item 11 |
| `.gitignore` | Keep `graphify-out/` untracked | CONTRACT 9 |

### UP-004 — `symfony/yaml` 7.4.11 is one patch short of the CVE fix

| Field | Value |
|---|---|
| **Status** | **applied 2026-08-08** — advisory count fell 52/14 → 49/13 |
| **Files** | `composer.lock` (upstream-owned) |
| **Work package** | 0A item 3 |
| **Disposition** | `carry` — generic security patch, contribution-eligible |
| **Conflict risk** | Low. Patch-level bump within 7.4.x. |

**Problem**

The UP-002 update resolved `symfony/yaml` to **7.4.11**. Three advisories apply to
`>=7.4.0,<7.4.12`, so the installed version is affected by all of them:

| CVE | Title | Severity |
|---|---|---|
| CVE-2026-45304 | Exponential memory allocation via recursive collection-alias expansion ("billion laughs") | low |
| CVE-2026-45305 | ReDoS via catastrophic backtracking in `Parser::cleanup()` | low |
| CVE-2026-45133 | Stack exhaustion via unbounded recursion in nested blocks, sequences and mappings | low |

All three are fixed in **7.4.12**.

**Exposure assessment — completed 2026-08-08**

`composer why symfony/yaml` returns only two real consumers:

```
darkaonline/l5-swagger 8.6.5   requires  symfony/yaml (^5.0 || ^6.0 || ^7.0)
zircote/swagger-php    4.11.1  requires  symfony/yaml (>=3.3)
symfony/routing        v6.4.37 conflicts symfony/yaml (<5.4)     <- conflict, not a dependency
symfony/translation    v6.4.38 conflicts symfony/yaml (<5.4)     <- conflict, not a dependency
```

Both consumers are OpenAPI tooling. `swagger-php` **generates** YAML from PHP annotations and
`l5-swagger` serves the generated document. All three CVEs require parsing attacker-controlled
YAML, and no runtime route accepts YAML input.

**Verdict: hygiene, not live exposure.** Patched regardless, since the fix was a single
patch-level bump with no fan-out. Re-run this assessment if any future feature accepts uploaded
YAML or renders a user-supplied OpenAPI document.

**Fix**

```
composer update symfony/yaml
```

Patch-level, single package, no dependency fan-out expected.

**Regression tests required before this entry is closed**

- [x] `symfony/yaml` >= 7.4.12 — **7.4.15**
- [x] `composer audit` advisory count fell 52/14 → **49/13**, removing the three YAML CVEs
- [x] `laravel/framework` still 10.50.2
- [x] `php artisan about` still runs

---

### UP-005 — Characterize, then upgrade `phpoffice/phpspreadsheet` (test-first, owner-approved)

| Field | Value |
|---|---|
| **Status** | **applied and verified 2026-08-09** |
| **Files** | `composer.lock` (upstream-owned); `phpunit.xml` (upstream-owned, additive test-isolation env only); `tests/TestCase.php`, `tests/CreatesApplication.php`, `tests/Unit/.gitkeep`, `tests/Feature/Admin/MemberImportCharacterizationTest.php` (new, IFGF-added test scaffolding — the repo had zero tests before this entry) |
| **Work package** | 0A item 6 (characterization coverage, required regardless) + item 3 finding (`DEPENDENCY_INVENTORY.md` "Prioritize now" row) |
| **Disposition** | `carry` — generic security patch, contribution-eligible; the added test scaffolding is `contribute`-eligible once WP 0A item 7 CI exists |
| **Conflict risk** | Low for `composer.lock` (single-package, in-range bump). Low for `phpunit.xml` (three added `<env>` lines, no removals). None for the new `tests/` files — they do not exist upstream. |

**Problem**

`phpoffice/phpspreadsheet` 1.30.0 carries 9 advisories, 2 critical (CVE-2026-34084 SSRF/RCE via
`IOFactory::load`, CVE-2026-45034 patch bypass) plus CPU/memory-exhaustion DoS and an SSRF via
`WEBSERVICE()` formula evaluation. `DEPENDENCY_INVENTORY.md` traced a **confirmed live sink**:
`ImportMemberController.php:56` calls `Excel::import(new UsersImport, $request->file('import_file'))`
directly on an admin-uploaded file, so any authenticated user who can reach `/admin/import` can hand
PhpSpreadsheet a crafted workbook. This is the only critical finding in the whole audit with a
proven path from untrusted input to vulnerable code — everything else critical has zero call
sites — so the owner approved pulling it ahead of WP 0B, on the condition that the fix is provably
behaviour-preserving.

The repo had **zero tests** (`tests/` did not exist — no `TestCase.php`, no `CreatesApplication.php`,
no suite directories). WP 0A item 6 requires characterization coverage for member import regardless
of this upgrade, so writing it here is not extra scope, it is the prerequisite the owner's test-first
order made explicit.

**Alternatives considered**

| Option | Rejected because |
|---|---|
| Upgrade first, characterize after | Owner's explicit instruction: a test written after the upgrade can't prove the upgrade didn't change behaviour, only that the new behaviour is internally consistent. Test-first is the only order that produces evidence. |
| Full `composer update` (no package scope) | Prohibited by `TODO.md` and `MEMORY.md` — dissolves the pinned characterization baseline and conflates this fix with the other 39 remaining advisories. |
| Bump `maatwebsite/excel` instead of `phpoffice/phpspreadsheet` directly | `maatwebsite/excel` 3.1.68 is already current and not advisory-bearing; its own constraint (`phpoffice/phpspreadsheet ^1.30.0`) is what caps the reachable version at 1.30.6, not an outdated `maatwebsite/excel`. Bumping it would be a no-op for this advisory set and outside the "targeted only" instruction. |
| Characterize through the full HTTP middleware stack (`web`, `auth`, `churchadmin`, `permission:read-members`) | Disproportionate for a dependency-upgrade characterization test: it would require seeding Laratrust roles/permissions and the `churchadmin` gate, none of which `phpoffice/phpspreadsheet` touches. That coverage belongs to WP 0A item 6's own "authentication, existing roles and direct permissions" bullet, as a separate future test. Used `actingAs()` + `withoutMiddleware()` instead, scoped to the `Excel::import()` parsing boundary this upgrade actually touches. |
| Characterize the full `UsersImport::collection()` business-logic path with a non-empty data row | `UsersImport::collection()` (`app/Imports/UsersImport.php`) dereferences an undefined `$request` variable as soon as `count($rows) > 0` — `"Attempt to assign property ... on null"`, a PHP `Error`, not caught by the method's own `catch (Exception $e)`. This is a **preexisting, unrelated defect** in business logic, independent of which phpspreadsheet version parsed the file; reproducing it here would characterize that bug, not the parsing boundary the upgrade touches. Used a **header-only** CSV fixture (zero data rows) instead — this still exercises the real `Excel::import()` → PhpSpreadsheet CSV reader path, just not the broken branch beyond it. Flagged as a follow-up, not fixed. |
| Stand up the disposable MySQL 8.4 test database now (WP 0A item 5) | That is its own scheduled deliverable (`TODO.md` Session 12) with its own fixture-anonymization work. This single test's query (`User::ByRole(5)->ByChurch($church_id)->count()`) is a plain `WHERE` count with no MySQL-specific generated columns, collation, or locking — `build.md` TEST AND QUALITY COMMANDS explicitly permits SQLite for tests outside that set. Added `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` to `phpunit.xml` instead, so the suite can never fall through to `.env`'s real `churchcms` MySQL database. Revisit when item 5 lands — this is a stopgap, not the deliverable. |

**Chosen fix**

```
composer update phpoffice/phpspreadsheet --with-dependencies
```

Targeted, single package plus its own dependency fan-out (`composer/pcre`, `nikic/php-parser`,
four `symfony/*` polyfill/string/console packages — all transitive to `phpoffice/phpspreadsheet`
itself, not new top-level surface). `laravel/framework` and the other 192 locked packages
untouched.

**Regression tests required before this entry is closed**

- [x] `tests/Feature/Admin/MemberImportCharacterizationTest.php` written and run **before** the
      upgrade, against pinned `phpoffice/phpspreadsheet` 1.30.0 — **passed** (1 test, 4 assertions)
- [x] `composer update phpoffice/phpspreadsheet --with-dependencies` — resolved to **1.30.6**
- [x] Same test re-run **after** the upgrade — **passed unchanged** (1 test, 4 assertions)
- [x] `laravel/framework` still **10.50.2**
- [x] `composer validate --strict` clean
- [x] `composer audit` advisory count fell 49/13 → **40/12**, removing all 9 `phpoffice/phpspreadsheet`
      advisories (package fully cleared, no longer in the audit output)

**Notes**

`php artisan test` cannot run this or any future suite yet — installed `nunomaduro/collision`
v6.4.0 is incompatible with installed PHPUnit 10.5.63 (`RequirementsException`: "Running PHPUnit
10.x or Pest 2.x requires Collision 7.x"). Pre-existing, unrelated to this entry. Ran via
`vendor/bin/phpunit` directly instead. Logged in `MEMORY.md` as a WP 0A item 7 (CI workflow)
blocker, not fixed here — fixing it means bumping `nunomaduro/collision`, a separate package
change outside this entry's "targeted only" scope.

Remaining 4 `Imports` classes flagged by `DEPENDENCY_INVENTORY.md` (Attendance, Subscribers,
Summary, and this one's sibling in `UsersImport`) share the same `Excel::import()` boundary and
are now covered by the same upgraded `phpoffice/phpspreadsheet` version, but do not yet have their
own characterization tests — WP 0A item 6 backlog, not required for this entry.

---

### UP-006 — Remove `botman/botman` + `botman/driver-web`, and the orphaned `custompackages/brozot/laravel-fcm` path repository

| Field | Value |
|---|---|
| **Status** | **applied and verified 2026-08-09** |
| **Files** | `composer.json` (upstream-owned, `require` + `repositories`), `composer.lock` (upstream-owned); `custompackages/brozot/laravel-fcm/**` deleted (upstream-owned directory, present since before this fork's IFGF work) |
| **Work package** | 0A item 3 finding (`DEPENDENCY_INVENTORY.md` "remove" verdicts) |
| **Disposition** | `retired` — both are dead weight removals, nothing to offer upstream |
| **Conflict risk** | Low. Pure removals; nothing downstream adds behavior on top of either package. |

**Problem**

`DEPENDENCY_INVENTORY.md` (Session 2) classified two independent pieces of dead weight, owner
reviewed and approved removing both in the same pass:

1. **`botman/botman` 2.8.11 + `botman/driver-web` 1.5.3.** `grep -rl "BotMan" app/ config/
   routes/` returns zero matches — nothing in this application ever constructs a BotMan bot. Also a
   known Laravel 13 blocker (unmaintained since 2021), so removing it now shrinks the WP 0B
   compatibility surface for free.
2. **`custompackages/brozot/laravel-fcm`.** `composer.json`'s `repositories` array still declared
   the path repository and the directory still existed on disk with its own `composer.json`, `src/`,
   and `tests/` — but `composer show brozot/laravel-fcm` returned "not found": nothing in root
   `composer.json`'s `require` section ever pulled it in. Superseded by
   `laravel-notification-channels/fcm` 4.5.0 (already installed, part of the UP-001 fix set).

**Residual-reference check — required by `TODO.md` before removing brozot**

`grep -rli "brozot\|laravelfcm\|botman" .` (excluding the package's own directory and this
session's docs) found one real hit: **`app/Traits/SendPushNotification.php`** imports
`LaravelFCM\Message\OptionsBuilder`, `PayloadDataBuilder`, and `PayloadNotificationBuilder`, and
its `sendNotification()` method actively instantiates `OptionsBuilder` and
`PayloadNotificationBuilder` (only the actual `FCM::sendTo(...)` send call is commented out).

Traced whether this makes the FCM path reachable, since a live path would require characterizing
it first, the same rule `TODO.md` set for UP-005:

- `grep -n "LaravelFCM" vendor/composer/autoload_psr4.php` — **no match**. The `LaravelFCM\`
  namespace was never registered in the generated autoloader, because `brozot/laravel-fcm` was
  never in `require` — only in `repositories`, which is inert without a matching `require` entry.
- `php -r "require 'vendor/autoload.php'; var_dump(class_exists('LaravelFCM\Message\OptionsBuilder'));"`
  — **`bool(false)`**, confirmed **before** any file in this entry was touched.
- No service provider, config file, or manual `require_once` references the path anywhere in
  `app/`, `config/`, or `bootstrap/` (grepped both).

**Verdict: not reachable.** `App\Traits\SendPushNotification::sendNotification()` already throws
`Error: Class "LaravelFCM\Message\OptionsBuilder" not found` the instant it is called, on the
baseline that existed **before** this entry — independent of whether the orphaned
`custompackages/brozot/laravel-fcm` directory is present, since it was never wired into the
autoloader either way. Removing the directory and the inert `repositories` entry is therefore a
no-op for runtime behavior: nothing that worked before still works, and nothing that was already
broken becomes more broken. No characterization test required under `TODO.md`'s own conditional
("if the FCM notification path is reachable") — it is not.

Left `app/Traits/SendPushNotification.php` itself untouched: fixing its dead `LaravelFCM\*` imports
to route through the already-installed `laravel-notification-channels/fcm` instead is an
application-behavior change to an upstream-owned file, out of this entry's "remove dead weight"
scope, and would need its own UPSTREAM.md entry and characterization test. Flagged in `MEMORY.md`
as a follow-up.

**Alternatives considered**

| Option | Rejected because |
|---|---|
| Isolate `botman/*` instead of removing | `EXECUTION_PLAN.md` §1.4's original call, superseded once Session 2's grep found zero call sites — there is nothing to isolate, only to delete. |
| Leave `custompackages/brozot/laravel-fcm` on disk, only drop the `repositories` entry | Leaves 60+ dead files (`src/`, `tests/`, `doc/`) silently carried in the tree with no reference anywhere; the whole point of the finding was that it's orphaned weight. Removed the directory too. |
| Fix `App\Traits\SendPushNotification.php`'s dead imports in this same commit, since they're clearly broken | Scope creep past "remove dead weight": rewriting a trait to call `laravel-notification-channels/fcm` is new application behavior on an upstream-owned file, needs its own characterization test and UPSTREAM.md entry, and touches push-notification call sites (`EventsController`, `BirthdayPushEventListener`, and others) this entry never inventoried. Left broken exactly as found; flagged in `MEMORY.md`. |
| Treat the `SendPushNotification.php` references as "reachable" out of caution, and write a characterization test anyway | Rejected once `class_exists()` proved the call already fatal-errors today, pre-removal — a test asserting "calling this throws a class-not-found Error" would characterize a bug already documented here in prose, not a behavior this removal changes. `TODO.md`'s conditional is about reachability, and reachability was disproven, not assumed. |
| `composer update` (unscoped) to regenerate the lock after the `composer.json` edits | Prohibited by `TODO.md`/`MEMORY.md` — dissolves the pinned baseline. Used `composer remove botman/botman botman/driver-web` (which updates lock + json together, touching only those two packages and their now-unused dependents) followed by `composer update --lock` (hash-only refresh, no version resolution) for the manual `repositories` edit. |

**Chosen fix**

```
composer remove botman/botman botman/driver-web
rm -rf custompackages/brozot
# composer.json: "repositories": [] (removed the brozot path entry)
composer update --lock
```

**Regression tests required before this entry is closed**

- [x] `grep -rn "botman\|brozot" composer.json composer.lock` — no matches
- [x] `class_exists('BotMan\BotMan\BotMan')` — **false** (was already the only way BotMan could be
      reached; never wired into any controller/route)
- [x] `class_exists('LaravelFCM\Message\OptionsBuilder')` — **false**, both before and after this
      entry (proves the removal changed nothing observable)
- [x] `composer validate --strict` clean
- [x] `composer install` resolves cleanly, `package:discover` runs with no missing-provider errors
- [x] `php artisan about` runs — Laravel 10.50.2, PHP 8.3.33, unchanged
- [x] `tests/Feature/Admin/MemberImportCharacterizationTest.php` (UP-005) still green — proves this
      removal didn't disturb the adjacent `Excel::import()` path
- [x] `composer audit` — still 40/12 (neither `botman/*` nor the never-installed `brozot/laravel-fcm`
      carried an advisory; this entry is a dead-code removal, not a security fix)

**Notes**

`react/promise`, `react/event-loop`, `react/dns`, `react/cache`, `mpociot/pipeline`, and
`evenement/evenement` were removed as `botman/botman`'s now-unused transitive dependents —
confirmed by `composer remove`'s own dependency-tree resolution, not guessed.

---

## Security findings — deferred, not yet entries

Recorded at first successful `composer audit`, 2026-08-08. **52 advisories across 14 packages.**
Do not clear these with an unargumented `composer update` — that dissolves the pinned baseline.
They are inputs to the WP 0A exit gate and the WP 0B replacement list.

| Finding | Severity | Disposition |
|---|---|---|
| `symfony/yaml` 7.4.11 — CVE-2026-45304, -45305, -45133 | low ×3 | **Promoted to UP-004.** |
| `doctrine/annotations` 2.0.2 | abandoned, no replacement offered | Trace which package pulls it; likely removable during WP 0B. |
| Remaining advisories across 12 other packages | not yet triaged | Triage in WP 0A Session 2 (dependency inventory). |
| **Two competing frontend lockfiles** — `yarn.lock` (345 KB) and `package-lock.json` (630 KB) are both committed for the same 52 dependencies | reproducibility | **Decide one in Session 2.** See note below. |

### Dual frontend lockfiles — no reproducible frontend build

`upstream/main` commits both `yarn.lock` and `package-lock.json`. Whichever tool a developer or CI
runs produces a different `node_modules` tree, so the frontend build is not reproducible — which
the WP 0A exit gate explicitly requires.

Observed 2026-08-08: `yarn.lock` appeared as modified with a **15,128-line whole-file reformat**
(committed form uses quoted alphabetised keys, `"integrity"` / `"resolved"` / `"version"`; working
copy uses Yarn v1 canonical unquoted `version` / `resolved` / `integrity`). Identical packages,
versions and integrity hashes — formatting only. Reverted with `git checkout -- yarn.lock` rather
than committed, since a 345 KB reformat of an unused lockfile is noise in the baseline and a
guaranteed conflict at the next upstream sync.

**Decision needed in Session 2:** this project used `npm ci` against `package-lock.json`
successfully (1,286 packages). Recommend standardising on npm, deleting `yarn.lock`, and recording
the removal as an upstream-owned change. Defer until the WP 0B Vite decision is in view, since a
Vite migration replaces the dependency set anyway and the two decisions should not be made twice.

Until then: **use `npm ci` only. Never run `yarn` in this repository.**

npm side, same date: **166 vulnerabilities (13 critical, 58 high)** across 1,286 packages, including
`axios` 0.18.1, Vue 2 (EOL), Bootstrap 4.6 (EOL). Do not run `npm audit fix`. This is the
evidence base for the Vite migration decision, WP 0B item 8.

## Not yet classified

`app/Providers/RouteServiceProvider.php` — Graphify flags it as a central integration point.
Verify before changing; prefer package route registration. *(OPEN-SOURCE ADAPTATION 7)*
