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
| Fork HEAD 2026-08-11 | `9120af9` on `contrib/laravel-supported-platform`, **local only, nothing pushed for this branch** |
| Divergence | 26 ahead, 8 behind `upstream/main` after fetch on 2026-08-11; see UP-007 |
| Last merge rehearsal | **never run** — required by `build.md` WP 0A exit gate and WP 0B item 12 |
| **Pin status** | **Frozen, owner-approved 2026-08-09.** Do not merge the 8 outstanding `upstream/main` commits until the WP 0A exit gate passes. |

### Pin decision — 2026-08-09 (Session 2b, `TODO.md` Now item 3)

Owner decision, third of three approved in the same review as UP-005 and UP-006: **pin
`upstream/main` at `d12c110` now, formally, as the characterization baseline; sync only after the
WP 0A exit gate.**

This is a state change, not a new fact — `d12c110` was already recorded above as the "reviewed"
SHA before this decision. What changes here is that the 8-commit divergence is no longer just
undecided drift to revisit opportunistically; it is **explicitly frozen** until characterization
tests exist to prove a sync regresses nothing (`build.md` WORK PACKAGE 0A exit gate: "the upstream
merge rehearsal passes"). Rationale: UP-001 through UP-006 already show upstream's own commits
carry defects (desynced locks, stranded PHP-8.4-only packages, PSR-4 case mismatches) that were
only caught because this fork stopped and verified before installing — merging 8 more commits
sight-unseen, before this repo has a single characterization test for the areas those commits might
touch, would reintroduce exactly the kind of unverified-drift risk WP 0A exists to close.

**Alternatives considered**

| Option | Rejected because |
|---|---|
| Merge the 8 outstanding commits now, characterize after | Same "test-first" reasoning as UP-005: a test written after a merge can't prove the merge didn't change behavior, only that the merged result is internally consistent. Two of the eight upstream defects already found (UP-001, UP-002) were partial-`composer update` residue — nothing rules out the other six carrying similar undiscovered issues. |
| Leave the divergence formally undecided (status quo) | `TODO.md` records it under "Resolved — 2026-08-09" already; leaving `UPSTREAM.md`'s Baseline table silent on it would let a future session assume the 8 commits are just unreviewed rather than deliberately held back, and risk an accidental merge before the exit gate. |
| Cherry-pick only the commits touching files this fork doesn't modify | Not evaluated — the eight commits' full diffs haven't been individually risk-assessed yet (`build.md` STARTUP SEQUENCE item 1 lists `git diff --name-only $(git merge-base HEAD upstream/main)..upstream/main` as a startup check, not yet run against all eight). Doing that triage now would be starting WP 0A item 4a's route/migration work early, which `TODO.md` schedules for Session 3, not this entry. |

**Record formally closed at:** Session 6 (`build.md` items 9–10 — pin the reviewed upstream SHA,
record divergence, create the compatibility ledger and ownership map). This entry documents the
Session 2b freeze decision itself; Session 6 is where the full ledger requirement gets satisfied.

---

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

### UP-007 — The supported-platform series: Laravel 10 → 13, PHP 8.4, and the in-house replacement of `laracasts/presenter`

| Field | Value |
|---|---|
| **Status** | **applied 2026-08-10; NOT yet verified by characterization tests** |
| **Files** | `composer.json`, `composer.lock`, `app/Models/User.php`, `app/Models/Role.php`, `app/Models/Permission.php`, `app/Models/Userprofile.php`, `app/Models/FeedbackMessage.php`, `app/Http/Middleware/AdminOrPermission.php`, `app/Presenters/UserprofilePresenter.php`, `public/css/app.css`, `public/js/app.js`, `public/mix-manifest.json` — all upstream-owned. Plus four **new** IFGF-neutral files under `app/Support/Presenter/`, and `.github/workflows/ci.yml` (new). |
| **Commits** | `086f33d` (legacy-factories), `9a39cde` (L10→11), `32e65f9` (L11→12), `30db6c9` (L12→13 + presenter), `06b9766` (platform pin + CI), `831cf2d`, `87742dc` |
| **Work package** | 0B items 1–5, 7, 9, 10, 13, 14 |
| **Disposition** | `contribute` for the framework-compatibility commits — they are IFGF-neutral and were deliberately kept replayable. `carry` for the presenter replacement until upstream removes `laracasts/presenter` itself. |
| **Conflict risk** | **High.** Six upstream-owned model/middleware files on the authorization surface, plus `composer.json`/`composer.lock`, which upstream also edits. `public/js/app.js` and `public/css/app.css` are committed build artifacts and will conflict on any upstream frontend change. |

**Problem**

Upstream remains on Laravel 10 at the pinned baseline `d12c110`. Laravel 10 left security support in
February 2025 (PRD §13.1.18), so `build.md` TECHNICAL BASELINE 1 and PRD §13.1.13 both require this
fork to upgrade ahead of upstream and carry the delta downstream. This entry is that delta.

**`laracasts/presenter` was the sole hard blocker**

Not laratrust — an earlier reading that laratrust also blocked 13 was wrong; the solver was listing
older 8.x versions, and installed **8.5.5 declares `^13.0`** (8.5.4 is the first that does). Always
check the *installed* version's own `composer.json` before believing a solver summary.

`laracasts/presenter` 0.2.8 is its newest release and constrains `illuminate/support` to `^12.0`.
There is no 13-compatible release and no upstream activity. Upstream requires it; the fork cannot
reach Laravel 13 while it is in `composer.json`. It was therefore **removed from `composer.json`
and reimplemented in-house** at `app/Support/Presenter/` — four files, ~60 lines,
behaviour-identical: `Presenter`, `PresentableInterface`, `PresentableTrait`, `PresenterException`.
The one upstream consumer, `app/Presenters/UserprofilePresenter.php`, was repointed at the new
namespace; `app/Models/Userprofile.php`'s `$presenter` property is unchanged in value.

This is an **upstream-owned change**: the dependency is declared in upstream's `composer.json` and
the consuming presenter and model are upstream files. It is the single most likely item in this
series to conflict on a future sync, because any upstream commit touching `composer.json` will
collide with its removal.

**Laratrust 8 renamed its entire public API**

`LaratrustUserTrait` → `HasRolesAndPermissions`; `Models\LaratrustRole` → `Models\Role`;
`Models\LaratrustPermission` → `Models\Permission`; `Middleware\LaratrustPermission` →
`Middleware\Permission`. Four call sites, **all on the authorization surface**
(`User`, `Role`, `Permission`, `AdminOrPermission`). Each was aliased on import so the local class
names are unchanged — deliberately, to keep the diff minimal and the replay cheap.

**Version deltas recorded**

| Package | Was | Now |
|---|---|---|
| `laravel/framework` | 10.50.2 | **13.24.0** |
| PHP (`config.platform`) | 8.3.33 | **8.4.24** (`require` is `^8.3`) |
| `laravel/sanctum` | 3.3.3 | 4.3.3 |
| `santigarcor/laratrust` | 7.2.1 | 8.5.5 |
| `spatie/laravel-medialibrary` | 10.15.0 | 11.23.5 |
| `nunomaduro/collision` | 6.4.0 | 8.9.5 |
| `phpunit/phpunit` | 10.5.x | 11.5.56 |
| `laravel/tinker` | 2.x | 3.0.2 |
| `laravel/dusk` | 7.x | 8.6.0 |
| `laravel/legacy-factories` | 1.4.2 | **removed** (Laravel 11 hard blocker) |
| `botman/*` | 2.8.11 / 1.5.3 | **removed** (UP-006) |
| `laracasts/presenter` | 0.2.8 | **removed → `app/Support/Presenter/`** |
| MySQL | 8.4.9 | 8.4.11 LTS |

**Alternatives considered**

| Option | Rejected because |
|---|---|
| Fork `laracasts/presenter` as a path repository and patch its constraint | Adds a second orphaned path repo of exactly the kind UP-006 had just deleted, and the package is ~60 lines. Vendoring it in-house under `app/Support/` is smaller, greppable, and carries no lockfile entry to conflict on. |
| Retire the presenter pattern into Eloquent accessors instead | Larger behavioural change on upstream-owned models with no characterization tests to prove equivalence — exactly the risk this series should not take. **Still open as an owner decision** (`TODO.md`). |
| Stop at Laravel 12, where `laracasts/presenter` still resolves | Defers rather than solves; 12 is not the newest stable major and `build.md` TECHNICAL BASELINE 2 requires the newest. |
| Group the three major upgrades into one commit | `build.md` WP 0B item 2 permits grouping only while solver and regression evidence stay attributable per transition. They were kept separate precisely so a bad major can be bisected. |
| Wait for upstream to upgrade first | Upstream has not moved off Laravel 10; PRD §13.1.13 makes production security take precedence. |

**Regression tests required before this entry is closed**

- [x] `composer install` resolves cleanly at 13.24.0 / PHP 8.4.24; `package:discover` clean
- [x] `php artisan about` reports Laravel 13.24.0, PHP 8.4.24
- [x] `php artisan test` green — but **1 test only** (`MemberImportCharacterizationTest`)
- [x] `/login` and `/register` return HTTP 200 with rendered pages against the seeded test DB
- [x] `npm run production` builds (exit 0)
- [ ] **Characterization coverage for auth, roles and direct permissions, member profile, member QR / membership card, attendance session open/scan/lock/unlock, group access and exports.** WP 0A item 6 / gate 5. **This entry cannot be closed without it** — three majors were crossed on the authorization surface with no regression detection.
- [ ] **Merge rehearsal against `upstream/main`** — never run. `build.md` WP 0B item 12 requires proving this series reapplies to the latest upstream baseline; the presenter removal is the likeliest failure point.
- [ ] Fresh-database migrate + seed from a clean checkout (WP 0A item 14)

**Notes**

`/` returns HTTP 500 because the `imagick` extension is missing (`BaconQrCode`). **Pre-existing and
not part of this series** — imagick was absent from both the 8.3 and 8.4 installs and from the
project's 27-extension list, so `/` failed identically before the upgrade. `gd` is present.
Separately, `app/Models/FeedbackMessage.php` points `$presenter` at a non-existent
`App\Presenters\UserPresenter`; that model's `present()` has always thrown. Both are in `TODO.md`,
neither is a regression from this entry.

---

### UP-008 — reserved

Reserved for the QR renderer change (`format('png')` → `format('svg')`, 8 Blade call sites),
`TODO.md` Now item 2, owner decision 2026-08-10 #1. **Not yet landed** — the number is held so the
two entries do not collide. UP-009 below was written first because the timezone fix had to land
before any attendance data is written.

---

### UP-009 — Application timezone: `Asia/Kolkata` → `UTC`, and pin the MySQL session offset

| Field | Value |
|---|---|
| **Status** | **approved 2026-08-15.** `REVIEW.md` question 1 is answered: **`PRD.md` wins — UTC at rest.** An earlier draft of this entry proposed `Asia/Taipei` at rest as a deviation from `build.md` TECHNICAL BASELINE 3; that proposal was **rejected** and is retained below under "Alternatives considered" so the reasoning is not lost. `build.md` L118 now restates UTC as normative rather than pointing here for a deviation. |
| **Files** | `.env.example` (upstream-owned), `config/database.php` (upstream-owned), `build.md` (project doc, one cross-reference line). Plus the two gitignored environment files `.env` and `.env.testing`, which are not upstream-owned but carry the same variable and must not be left behind. |
| **New files** | `tests/Feature/Attendance/TimezoneCharacterizationTest.php` (IFGF-neutral) |
| **Commits** | *(pending; nothing from this entry is staged)* |
| **Work package** | Pre-WP-0C blocker. Recorded in `TESTING_PLAN.md` § "Timezone defect". |
| **Disposition** | `carry`. Upstream ChurchCMS is an Indian project and `Asia/Kolkata` is the correct default *for upstream*. The value is deployment-specific, so this never goes upstream. The `config/database.php` `'timezone'` key is arguably a generic fix — pinning the session offset is correct for any deployment — and `'+00:00'` is additionally the value upstream would want, so this key alone is a **candidate for contribution** once WP 0A's merge rehearsal passes. |
| **Conflict risk** | **Low–moderate.** `.env.example` is a file upstream edits often, but the conflict is a one-line value and always resolves in this fork's favour. `config/database.php` has been untouched by upstream across the pinned baseline; the added key sits at the end of the `mysql` array where an upstream insertion is unlikely to land. |

**Problem**

`php artisan about` reported `Timezone .................. Asia/Kolkata`. `.env` was created from
upstream's `.env.example`, which carries **two competing variables**:

| File | Line (pre-change) | Value | Effect |
|---|---|---|---|
| `.env.example` | 62 | `TIMEZONE=Asia/Kolkata` | **This one wins** |
| `.env.example` | 92 | `APP_TIMEZONE=UTC` | **Dead — never read by anything** |
| `config/app.php` | 71 | `'timezone' => env('TIMEZONE','UTC')` | Reads `TIMEZONE`, ignores `APP_TIMEZONE` |

Taipei is UTC+8, Kolkata is UTC+5:30, so **everything the application wrote was 2.5 hours behind
Taipei wall-clock**. `now()`, `scanned_at`, `locked_at`, `created_at` and `attendance_date` were all
in the wrong zone, and `attendance_date` could land on the **wrong calendar day** near midnight,
silently misfiling a service. The legacy Apps Script manifest already uses `Asia/Taipei`, so every
imported legacy timestamp would have been shifted on the way in.

**Both halves are required — the second one is the silent-corruption half**

`config/database.php` set **no** connection timezone, so the connection inherited the *server*
default. The schema mixes column types:

| Type | Count | MySQL behaviour |
|---|---:|---|
| `timestamp()` — incl. `event_attendees.scanned_at`, `event_attendance_sessions.locked_at` | 23 | **Converts** to/from UTC using the *session* timezone |
| `dateTime()` | 10 | Stores the literal string, **no conversion** |
| `date()` — incl. `event_attendance_sessions.attendance_date` | 8 | Date only; the day it lands on follows the PHP timezone |

If PHP and MySQL disagree the `timestamp` columns shift and the `dateTime` columns do not, so half
the data looks correct. Setting only `TIMEZONE=` would have produced exactly that.

This matters more in production than it looks locally. On this dev machine `@@global.time_zone` is
`SYSTEM` resolving to *Taipei Standard Time*, i.e. already `+08:00` — so the round-trip assertion
would pass here with or without the pin. A Linux VPS or CI runner defaults to UTC, where it would
not. The pin removes the dependency on the host clock entirely; the `@@session.time_zone`
assertion in the characterization test is what actually catches its absence.

**A fixed offset, not a named zone**

`'timezone' => '+00:00'`, **not** `'UTC'`. Named zones require MySQL's `mysql.time_zone*` tables to
be populated (`mysql_tzinfo_to_sql`), which a default install does not do; a named zone against an
unpopulated table fails or silently falls back. `+00:00` is exact by definition and cannot drift.

The pin is required **regardless of which zone is chosen**. Its job is to remove the dependency on
the host clock: `@@global.time_zone` is `SYSTEM` on this dev machine (resolving to Taipei) and UTC
on a typical Linux VPS or CI runner. Choosing UTC makes the two agree on most hosts *by luck*, which
is exactly the condition under which a missing pin goes unnoticed until it is deployed somewhere
else. Assert it, do not rely on it.

**Reconciled with `build.md` and `PRD.md` — no deviation remains**

TECHNICAL BASELINE 3 specifies **UTC storage**, and so do four `PRD.md` lines:

| `PRD.md` | Requirement |
|---|---|
| L859 | `ifgf_event_occurrences` keyed on event plus **UTC `starts_at`** |
| L877 | Occurrence identity is event plus **exact UTC `starts_at`** |
| L909 | "Production uses MySQL 8.4 LTS with `utf8mb4` and **UTC timestamps**. … User-facing times render in the branch timezone, default `Asia/Taipei`." |
| L1502 | NFR-02 — all production tables use InnoDB, `utf8mb4`, foreign keys, and **UTC timestamps** |

An earlier draft of this entry proposed storing `Asia/Taipei` instead, as an approved deviation.
**The owner rejected that on 2026-08-15** and affirmed the PRD: L909 — UTC at rest, branch timezone
at display — is the contract. All five normative statements now agree, and no document carries a
deviation pointer. The rejected proposal is preserved in "Alternatives considered" below because its
reasoning is still the right reasoning to weigh if the revisit condition ever fires.

**Known consequence — accepted, and characterized rather than left implicit**

L909's display-layer conversion is not free, and one case is sharp enough to name here.
`event_attendance_sessions.attendance_date` is a `date()` column: date-only, no timezone, no
conversion. Under UTC at rest, a service held between **00:00 and 08:00 Taipei** falls on the
**previous UTC calendar day** — an 06:00 morning prayer meeting on Sunday files under Saturday.
Services from 08:00 Taipei onward are unaffected, which covers every regular Sunday service.

This is not a defect to fix later; it is the direct, intended cost of the chosen contract. The rule
that follows: **any code deriving a calendar day from an instant must convert to the branch timezone
first.** The 8 `date()` columns are where that rule gets broken. A test in
`TimezoneCharacterizationTest` asserts the shift explicitly, so a future migration cannot change what
a stored `attendance_date` means without turning that test red.

**Revisit condition**

**Every branch leaves UTC+8, or the day-boundary cost of the `date()` columns proves higher than the
portability it buys.** UTC at rest is the conservative, portable choice and the one the PRD requires;
reopening it needs a written owner decision, an amendment to the four PRD lines above, and — once any
attendance data exists — a data migration, not a configuration change.

**Alternatives considered**

| Option | Rejected because |
|---|---|
| **`Asia/Taipei` at rest** (`TIMEZONE=Asia/Taipei`, connection `+08:00`) — proposed 2026-08-10, **rejected 2026-08-15** | The strongest argument against UTC here: Taiwan has no daylight saving, so UTC's principal benefit (unambiguous instants across DST transitions) buys nothing; both branches are in UTC+8, so there is no multi-zone display problem to solve; and the legacy Apps Script manifest already uses `Asia/Taipei`, so imported timestamps would map 1:1 with no conversion step — and a conversion step that does not exist cannot be got wrong. It also removes the `date()`-column day-boundary problem entirely rather than pushing it to 8 call sites. **Rejected because `PRD.md` requires UTC at four normative lines and `build.md` TECHNICAL BASELINE 3 at a fifth**, and a deviation carried across five documents is a standing cost paid every session. UTC is also the choice that does not have to be revisited if a branch outside UTC+8 is ever added. Reopening this needs the revisit condition above. |
| `'timezone' => 'UTC'` (named) on the MySQL connection | Requires `mysql.time_zone*` to be populated, which a default install lacks. Buys nothing over `+00:00`, and fails or silently falls back on hosts where the tables are empty. |
| No connection pin at all, relying on the host defaulting to UTC | Works by luck on a Linux VPS and fails on this dev machine, where `@@global.time_zone` is `SYSTEM` = Taipei. The failure is silent and half-visible: `timestamp` columns shift, `dateTime` columns do not. The pin costs one line and removes the host clock from the contract. |
| Set `APP_TIMEZONE=UTC` and change `config/app.php` to read it | Edits upstream-owned `config/app.php` for no gain, and would make the fork's variable name diverge from upstream's — a worse merge conflict than the value change. `config/app.php:71` is left exactly as upstream wrote it. |
| Leave `APP_TIMEZONE=UTC` in place, just fix `TIMEZONE` | A dead variable that reads as authoritative is a trap for the next person who "fixes" the timezone by editing the wrong line. It is replaced with a comment saying it is not read. |
| Fix `.env` only and leave `.env.example` alone to reduce conflict surface | Every fresh checkout would reintroduce the defect. `.env.example` is the file a new developer copies. |

**Data migration — not required, verified not required**

Checked before changing anything, 2026-08-11:

- `churchcms_test_disposable` — the only application database that exists — has **0 rows** in
  `users`, `userprofiles`, `events`, `event_attendees` and `event_attendance_sessions`. Only
  `migrations` (93 rows) is populated.
- The `churchcms` database named in `.env` **does not exist on disk** (`D:/MySQL/data/` holds only
  `churchcms_test_disposable`, `customer_service`, and the system schemas).

So no timestamp has ever been persisted under `Asia/Kolkata`, and this is a configuration change
only. **This is exactly the window in which it is a configuration change** — `TESTING_PLAN.md`
marks the fix as due *before WP 0C*, because once attendance is imported or written, wrong-zone
timestamps are wrong at rest and need a data migration instead.

**Regression tests required before this entry is closed**

- [x] `tests/Feature/Attendance/TimezoneCharacterizationTest.php` — `config('app.timezone')` and
      `date_default_timezone_get()` are both `UTC`
- [x] Same test — live `SELECT @@session.time_zone` returns `+00:00`, asserted against the
      connection rather than against config, so config and reality disagreeing is caught
- [x] Same test — **round-trip**: one Carbon instant written to a `timestamp` column and a
      `dateTime` column in the same request reads back as the same wall-clock from both
- [x] Same test — **documents the accepted consequence**: an 06:00 `Asia/Taipei` service resolves to
      the previous UTC calendar day. Named `test_documents_*` so a green run never reads as
      endorsement of the shift, only as proof it has not changed
- [x] `php artisan about --only=environment` reports `UTC`
- [ ] Attendance characterization suite 4 (WP 0A item 6 / gate 5) — these assertions live on the
      attendance surface but the surface itself is still uncharacterized

---

### UP-010 — Root `composer.json` and `composer.lock`: load the IFGF package seam

| Field | Value |
|---|---|
| **Status** | **approved** — WP 0A item 11 requires exactly this ("Record root composer.json and composer.lock as approved upstream-owned touchpoints"). |
| **Files** | `composer.json` (upstream-owned), `composer.lock` (upstream-owned, generated) |
| **New files** | `custompackages/ifgf/church-operations/**` (IFGF-owned, 13 files), `tests/Feature/Package/PackageProviderSmokeTest.php` (IFGF-neutral) |
| **Work package** | WP 0A items 11 and 14, gate 3 |
| **Disposition** | `carry`. Never goes upstream — it exists to keep IFGF behaviour OUT of upstream-owned files, which is the opposite of a contribution. |
| **Conflict risk** | **Moderate, and the failure mode is silent.** `composer.json` is a file upstream edits often. A merge that drops the `repositories` entry or the `require` line does not error — the package simply stops loading and IFGF behaviour disappears while the application keeps serving. `tests/Feature/Package/PackageProviderSmokeTest.php` is the alarm; the merge rehearsal (item 13) must run it. |

**The change**

Two additions to the root manifest, both minimal and both required for Composer path loading:

```json
"require": { "ifgf/church-operations": "^0.1.0" },
"repositories": [
    { "type": "path", "url": "custompackages/ifgf/church-operations",
      "options": { "symlink": true } }
]
```

The package declares its own PSR-4 (`Ifgf\ChurchOperations\` → `src/`) and
`extra.laravel.providers`, so registration happens through **Laravel package auto-discovery** — the
root `app.php` provider array is **not** touched, which keeps another upstream-owned file out of the
change set. Verified: `bootstrap/cache/packages.php` line 40 lists the provider.

**Two things that had to be fixed to make `composer validate --strict` pass**

1. A path package with **no `version`** resolves to `dev-<current-branch>`, which fails the root's
   `minimum-stability: stable`. The package now declares `"version": "0.1.0"`.
2. `"ifgf/church-operations": "*"` is an unbound constraint and warns under `--strict`. Now `^0.1.0`.

Both are worth knowing before the next path package is added; neither is obvious from the error text.

**Nothing in this entry implements product behaviour**

WP 0A item 11 is explicit that the seam is scaffolded and nothing is implemented. The package ships
a marker route, view, translation, config key, command and policy whose only purpose is to be
asserted against, and **two** tests guard the instruction — one in the package's own suite and one in
the application suite — both asserting `database/migrations/` holds no migrations. Schema belongs to
WP 0C, which needs its own approval.

**Regression tests required before this entry is closed**

- [x] `tests/Feature/Package/PackageProviderSmokeTest.php` — 11 tests covering provider registration,
      routes, migration path, views, translations, config merge, commands, policies, the package test
      runner path, and the no-product-behaviour guard
- [x] Package's own suite runs independently of the application:
      `vendor/bin/phpunit -c custompackages/ifgf/church-operations/phpunit.xml` → 3 tests, 10 assertions
- [x] `composer validate --strict` passes
- [x] `php artisan ifgf:ping` prints the merged config value
- [x] `php artisan route:list --path=_ifgf` shows the package route
- [ ] **Merge rehearsal (WP 0A item 13) has never run.** Until it does, the "moderate conflict risk"
      above is an assessment, not a measurement.

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
