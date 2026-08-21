# WP 0A — owner review pack for exit-gate criteria 4 and 7

**Prepared:** 2026-08-21 · **Branch:** `contrib/laravel-supported-platform` · **HEAD:** `6718a89`
**For:** the single owner reading session that closes criteria 4 and 7 together.

> **Neither criterion is marked met by this document.** Both are phrased "… is reviewed", and
> reviewed means owner-reviewed. This pack exists to make that reading possible in one sitting; the
> verdict is yours. Assembling is not reviewing — the same distinction applied to criterion 7 on
> 2026-08-18.

**What you are being asked for:** three decisions, marked ▶ below. Everything else is context.

---

## Part 1 — criterion 4: the `UPSTREAM.md` ledger, one line per entry

Twelve entries. Columns are as recorded in `UPSTREAM.md`; the "what it changed / why" column is a
compression, not a substitute for the entry.

| # | What it changed | Why | Disposition | Conflict risk | Status |
|---|---|---|---|---|---|
| **UP-001** | `composer.lock` — regenerated 4 desynced packages (`stripe-php`, `laravel-firebase`, `fcm`, `lcobucci/jwt`) | Upstream ships a `composer.json`/`.lock` pair that **cannot install**. Verified identical on `HEAD`, `upstream/main` and `origin/main` — an upstream defect, not fork drift | `carry` | **High** — lockfiles conflict on nearly every sync; regenerate, don't merge | applied + verified 2026-08-08 |
| **UP-002** | `composer.json` (`config.platform`), `composer.lock` — three PHP-8.4-only packages corrected, platform PHP pinned | The first attempt at UP-001 surfaced a second independent upstream defect | `carry` | Low for `.json` (additive block), **High** for `.lock` | applied + verified 2026-08-08 |
| **UP-003** | `database/factories/EventgalleryFactory.php` → `EventGalleryFactory.php` | PSR-4 filename case mismatch broke autoloading. First of the two Windows-passes/Linux-fails defects | `contribute` (trivially generic) | Low — a rename | applied + verified 2026-08-08 |
| **UP-004** | `composer.lock` — `symfony/yaml` patch bump | One patch short of the CVE-2026-45304/-45305/-45133 fix. Advisories fell 52/14 → 49/13 | `carry` | Low — patch-level, in-range | applied 2026-08-08 |
| **UP-005** | `composer.lock` (`phpspreadsheet`), `phpunit.xml`, plus the first `tests/` scaffolding the repo ever had | Security bump, done test-first: characterize member import, then upgrade | `carry`; scaffolding `contribute`-eligible | Low | applied + verified 2026-08-09 |
| **UP-006** | Removed `botman/botman`, `botman/driver-web`, and the orphaned `custompackages/brozot/laravel-fcm` path repo | Dead weight; nothing referenced them | `retired` | Low — pure removals | applied + verified 2026-08-09 |
| **UP-007** | **The big one.** `composer.json`/`.lock`, `User`, `Role`, `Permission`, `Userprofile`, `FeedbackMessage`, `AdminOrPermission`, `UserprofilePresenter`, built `app.js`/`app.css`/`mix-manifest.json`; new `app/Support/Presenter/`, new `ci.yml` | Laravel 10 → 13, PHP 8.4, and replacing the abandoned `laracasts/presenter` in-house | `contribute` for the framework commits; `carry` for the presenter replacement | **High** — six upstream-owned files on the authorization surface, plus committed build artifacts | **applied 2026-08-10; still reads "NOT yet verified by characterization tests"** — see ▶ D1 |
| **UP-008** | *Reserved, not landed.* QR `format('png')` → `format('svg')` | No imagick anywhere; owner decision 2026-08-10 #1 | — | — | **reserved.** Scope reduced 8 → 7 call sites by the 2026-08-15 rehearsal: upstream deletes `idcard.blade.php`'s call itself — **do not hand-edit that file** |
| **UP-009** | `.env.example`, `config/database.php` (+ the two gitignored env files) | `Asia/Kolkata` → `UTC`, and pin the MySQL session offset to `+00:00` | `carry` (the `config/database.php` key alone is contribution-eligible) | Low–moderate | approved 2026-08-15; `REVIEW.md` Q1 answered — **PRD wins, UTC at rest** |
| **UP-010** | Root `composer.json` + `composer.lock` — path repository and `require` for the IFGF package seam | Keeps IFGF behaviour **out** of upstream-owned files | `carry` — never goes upstream by design | **Moderate, and silent.** A merge dropping the entry doesn't error; the package just stops loading | approved (WP 0A item 11 requires exactly this) |
| **UP-011** | `resources/assets/js/components/Payaccount/` → `payaccount/` | `npm run production` had **always** been broken on Linux — the production target. Second Windows/Linux case defect after UP-003 | `contribute` — upstream is as broken as this fork | Low, but a case-only rename needs a two-step `git mv` | **CLOSED 2026-08-18**, verified in CI run `32090487802` |

### ▶ D1 — UP-007's two stale statements

Both are factual corrections, not judgement calls, but they change what the ledger asserts:

1. **Conflict risk reads "High" as a prediction.** The 2026-08-15 rehearsal **measured** it clean
   against `800c29f`, and the `merge-rehearsal` CI job has been green since 2026-08-18. Proposed
   restatement: *"Predicted High. **Measured clean** against `800c29f` (2026-08-15, and green in CI
   since 2026-08-18) — for the current upstream head only. The prediction stands for future heads:
   the six authorization files and the committed build artifacts are still the exposure."*
2. **Status reads "NOT yet verified by characterization tests."** Partly resolved as of today.
   `AdminOrPermission` and `User` now carry direct coverage (`RolePermissionCharacterizationTest`,
   and this session's export suite). `Role`, `Permission`, `Userprofile`, `FeedbackMessage` and
   `UserprofilePresenter` still do not. Proposed restatement: *"partially verified — see
   `RolePermissionCharacterizationTest`, `ExportCharacterizationTest`. Four files remain
   uncovered."*

**Approve both restatements?**

---

## Part 2 — criterion 4: the two unclassified files

`UPSTREAM.md` ends with a "Not yet classified" section. It names one file; the exit gate names two.
Both were measured today rather than assumed, and **one of the exit gate's premises turned out to be
wrong.**

### ▶ D2 — `app/Providers/RouteServiceProvider.php`

**Measured 2026-08-21:**

```
git diff --quiet d12c110 HEAD -- app/Providers/RouteServiceProvider.php   -> IDENTICAL
git diff --quiet 800c29f HEAD -- app/Providers/RouteServiceProvider.php   -> IDENTICAL
```

**This fork has never modified it.** It is byte-identical to both the pinned upstream SHA and the
current upstream head. The exit gate's implied action — "classify it, add an entry" — rests on a
premise that does not hold: `UPSTREAM.md` entries record *fork changes to upstream-owned files*, and
there is no change here to record.

But it is unquestionably load-bearing. Line 83:

```php
->middleware(['web','auth','churchadmin'])
->group(base_path('routes/admin.php'));
```

That single line is what puts **every** `/admin/*` route behind both legacy gates. It is the reason
denial is 401 rather than 403, the reason a usergroup-1 fixture is redirected to `/portal` before any
permission check runs, and therefore the reason three characterization suites are written the way
they are.

**Proposed classification: `monitor` — upstream-owned, unmodified, authorization-critical.** A new
disposition, recorded in a new "Monitored upstream files (unmodified)" section rather than as a
UP-nnn entry. The obligation it creates is not "get approval before editing" but **"re-read this file
after every upstream sync"**: if upstream changes line 83, the fork's entire authorization posture
changes silently and every auth test's baseline moves with it.

Alternatives considered and rejected:

| Option | Rejected because |
|---|---|
| Give it a UP-nnn entry | Entries mean "this fork changed an upstream file". Recording an unmodified file that way makes the ledger's central claim untrue and every future reader will look for a diff that is not there. |
| Leave it in "Not yet classified" | It has been read and understood; leaving it unclassified implies outstanding investigation that is in fact complete. |
| Move the admin routes into the package | That is a real change to a central integration point, needs its own entry, and belongs to FR-11 — not to a classification decision. |

**Approve `monitor`, or prefer a UP-nnn entry anyway?**

### ▶ D3 — `phpunit.xml`

**Measured 2026-08-21:** modified by two fork commits — `9045ce5` (UP-005) and `f60f1a4` ("Wire the
suite to a disposable MySQL 8.4 database", WP 0A item 5). Against the pinned upstream SHA the file is
**29 insertions / 31 deletions** — most of the file.

It is **not** unrecorded: UP-005's Files row lists it. But that row describes it as *"upstream-owned,
additive test-isolation env only"*, and after `f60f1a4` that description is **no longer accurate** —
the testsuite paths, the source-coverage block and the DB wiring all changed.

Two ways to fix the inaccuracy:

| Option | For | Against |
|---|---|---|
| **(a) Extend UP-005's Files row** and restate the conflict risk | Keeps one entry per *reason*; the file changed twice for the same reason (making the suite runnable) | Buries a WP 0A item-5 change inside a UP-005 entry that is about `phpspreadsheet` |
| **(b) Give it UP-013 of its own** | The second change has a different cause (item 5, disposable DB) and a different risk profile — it is now a near-total rewrite of a file upstream also edits | Twelfth entry for a test-harness file |

**Recommendation: (b), UP-013.** The conflict risk genuinely changed — "three added `<env>` lines, no
removals" is Low; "31 lines removed" is not, and a reader deciding how to resolve a future conflict
needs the accurate figure. **Approve (b), or prefer (a)?**

---

## Part 3 — criterion 7: the compatibility matrix, as-is

`UPGRADE_COMPATIBILITY_MATRIX.md`, assembled 2026-08-18, 1,777 words. **Presented unchanged.** It has
not been edited for this review and nothing in it has been re-verified today.

**Read §7 first.** It is the section stating what the matrix deliberately does **not** establish, and
it is the part that determines whether the criterion can honestly be marked met:

1. **It is not behavioural proof.** WP 0B item 6 was skipped by owner directive. The platform is
   verified to install, migrate, boot and build — not to behave identically.
2. Support-lifecycle dates marked `[2026-08-10]` have **not** been re-verified.
3. **No anonymized production snapshot** has been tested against the MySQL upgrade checker; the CI
   migration replay stands in for it.
4. The **730-vs-812 route count** is unreconciled and not proven upgrade-neutral. (Now 731 — one
   route was added since. Still unreconciled.)
5. UP-007's "NOT yet verified" status — partly addressed by ▶ D1 above.

**Today's findings make §7 item 1 sharper, not softer.** This session found a behavioural regression
the traversal introduced and nobody had noticed: `protected $dates` was removed in Laravel 10, and 21
date columns across 9 models silently stopped casting (Part 4). The matrix's own caveat — "verified to
install, boot and build, not to behave identically" — has now been demonstrated rather than
hypothesised. That is an argument for the matrix's honesty, and against marking criterion 7 met on the
strength of the artifact alone.

---

## Part 4 — what changed under both criteria since the last review

Relevant because both criteria's status depends on it.

**Characterization: 37 → 62 tests** (73 total including the 11 package smoke tests). Full `Feature`
suite green: **73 passed, 250 assertions, 0 failed, 0 skipped**, 2m41s. Package suite 3/10.

Two suites closed, both PII-bearing and both previously uncharacterized:

- **Suite 9, exports** — 11 tests. Found: `/admin/export` is a dead route (missing `index()`); two
  routes collide on that URI and the subscriber export loses silently; an empty result set emits the
  CSV **and then** 500s on an unset variable; the CSV never travels in the Laravel response at all.
- **Suite 11, private media** — 9 tests. Found: **there is no private media.** Every disk is public,
  the `uploads` disk is rooted at `public_path()`, no signed or temporary URL exists anywhere, and
  member-photo privacy rests entirely on filename entropy.

**Three new findings. SEC-003 was fixed the same day by owner direction (UP-012); the other two are
characterized, not fixed** — fixing and characterizing in one pass destroys the baseline.

| ID | Finding | Why it matters here |
|---|---|---|
| **SEC-003** — **FIXED same day, UP-012** | `/admin/changeavatar` (and the Preacher twin) accepted **any** file type, unvalidated. **First rated RCE — that was wrong.** Real PHP content stores extensionless; the genuine vector was **stored XSS** via `.svg`/`.html` served from the app's own origin | Fixed by owner direction 2026-08-21: type-hint the FormRequest that already existed. The severity correction is the durable lesson — `UploadedFile::fake()` derives MIME from the filename and cannot settle a file-type question |
| **REG-001** | `protected $dates` (removed in Laravel 10) leaves **21 columns across 9 models** returning strings | An upgrade regression introduced by WP 0B. Directly relevant to criterion 7 §7 item 1 and to UP-007's status |
| **SEC-002 extension** | The attendance **export** never consults `event_managers` either — assigned and unassigned leaders get identical outcomes | Widens a known finding onto the PII egress surface |

---

## The three decisions, together

- **▶ D1** — approve the two UP-007 restatements (measured conflict risk; partial verification)?
- **▶ D2** — classify `RouteServiceProvider.php` as `monitor` (unmodified, authorization-critical),
  or insist on a UP-nnn entry?
- **▶ D3** — `phpunit.xml`: new **UP-013** (recommended), or extend UP-005? *(Renumbered from UP-012, which was taken on 2026-08-21 by the SEC-003 fix.)*

**And then the criterion verdicts, which are yours alone:**

- **Criterion 4** — is the ledger reviewed? Note it cannot be fully closed while UP-007 still names
  four files with no characterization coverage, unless you accept that as a known gap.
- **Criterion 7** — is the matrix reviewed? Note §7 items 2, 3 and 4 remain open by the matrix's own
  admission.

Marking either met is a decision this document deliberately does not make.
