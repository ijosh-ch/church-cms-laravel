# WP 0A exit gate review

**Date:** 2026-08-17 · **Re-scored:** 2026-08-18 (CI green), 2026-08-21 (suites 9 and 11), **2026-08-21 (suite 6)**
**Branch:** `contrib/laravel-supported-platform`
**Reviewed against:** `build.md` L225–228 (Work Package 0A exit gate)

---

## Verdict: ❌ **NOT PASSED**

**Re-scored 2026-08-21: 4 of 7 criteria met, 0 partial, 3 not met — UNCHANGED from 2026-08-18.**

Criterion 2 has cleared its **numeric** target without crossing the line that matters
(37 → 62 → 79 → **94** characterization tests against a target of 80–120; **5 of 11** suites complete,
corrected from 6 on 2026-08-22 — see §2) and criterion 7's owner-review pack now exists, but **no
criterion changed verdict**.
Criteria 1, 5 and 6 remain met from the 2026-08-18 CI run `32090487802`.

The honest summary of 2026-08-21: the blocking criterion is closer and better understood, and it is
still the blocking criterion.

`build.md` OPERATING CONTRACT 10 and the Step 7 instruction are both explicit: the gate is not
marked passed if any criterion fails. **It still fails three** — criteria 2, 4 and 7. Criteria 4 and
7 both have their artifacts and both close in one owner reading session
(`WP0A_OWNER_REVIEW.md`, 2026-08-21).

The single blocking criterion that costs *work* is **characterization coverage**. The other two cost
a sitting.

---

## The gate text

> The current source installs reproducibly or has a documented blocker, critical existing behavior
> has characterization coverage, the package seam loads without changing product behavior,
> `UPSTREAM.md` and the ownership map are reviewed, CI runs from a clean checkout, the upstream
> merge rehearsal passes, and the upgrade compatibility matrix is reviewed.

Seven criteria, assessed individually.

---

## 1. Source installs reproducibly, or has a documented blocker — ✅ **MET** *(2026-08-18)*

**Demonstrated on a clean Ubuntu runner**, not asserted: `composer validate --strict`,
`composer install` from the committed lockfile (including the IFGF path package), `npm ci`, and
`npm run production` all succeed — CI run `32090487802`.

Until 2026-08-18 this was only ever true on one Windows machine with a warm Composer cache, and
`npm run production` was in fact **broken on Linux the whole time** (UP-011). That is the difference
between a clean checkout proving reproducibility and a developer machine implying it.

The 166 npm vulnerabilities (17 low, 78 moderate, 58 high, 13 critical) and the Vue 2 EOL ReDoS
advisory remain **documented** in `DEPENDENCY_INVENTORY.md`, satisfying the "documented blocker"
half.

---

## 2. Critical existing behavior has characterization coverage — ❌ **NOT MET**

**This is the real blocker. The count no longer says so; the coverage still does.**

| | 2026-08-18 | 2026-08-21 (suites 9/11) | **2026-08-21 (suite 6)** |
|---|---|---|---|
| Characterization tests | 37 | 79 | **94 — numeric target MET** |
| Full `Feature` suite | 48 passed / 109 assertions | 90 passed / 301 assertions | **105 passed / 345 assertions / 0 failed / 0 skipped**, 234s |
| Target (`TESTING_PLAN.md` Part 1) | 80–120 | 80–120 | **80–120** |
| Suites complete | 2 of 11 | 4 of 11 *(recorded as 5 — inflated)* | **5 of 11** (roles+permissions, membership card/QR, **groups / `GroupLink`**, exports, private media) |
| Suites partial | 2 | 3 | **3** (auth, member profile, attendance) |
| Suites not started | 7 | 4 | **3** (event management, birthday routes, queues and notifications) |

> ⚠ **Suite arithmetic, corrected 2026-08-22 — the figure was 6 of 11 and it was wrong.**
> Resolved against `TESTING_PLAN.md` and the 13 test files on disk, not by picking a reading:
> **complete are suites 2, 5, 6, 9 and 11 — five**; partial 1, 3, 4; not started 7, 8, 10.
> **5 + 3 + 3 = 11.** The previous figure gave 6 + 3 + 3 = 12 against a denominator of 11.
>
> Four files on disk sit **outside the eleven altogether**, which is where the extra 1 came from:
> `Regression/DateCastRegressionCharacterizationTest` (cross-cutting),
> `Attendance/TimezoneCharacterizationTest` (session 6, the UP-009 pin),
> `Admin/MemberImportCharacterizationTest` (session 2b), and `Package/PackageProviderSmokeTest`,
> which is not characterization at all.
>
> **The numerator was inflated by one and had been since session 9** (4 complete, recorded as 5).
> **Note the direction: it overstated progress on the single criterion blocking this gate.** The
> verdict does not move — criterion 2 is unmet at 5 or at 6 — but *a criterion-2 number that drifts
> upward is exactly how a gate eventually gets scored met on a proxy that has run out.* The
> denominator was always right.

Still not started: **event management, birthday routes, queues and notifications.** Partial gaps: auth owes password reset, email verification, session lifetime and
throttling; member profile owes create/edit/delete/export behaviour beyond render and authorization;
attendance owes `searchMember` and `removeAttendee`.

**Closed 2026-08-21 — the two PII-bearing surfaces.** Suite 9 (exports, 11 tests) and suite 11
(private media, 9 tests) were the two named as *wholly uncharacterized and both touching member PII*,
which is why they were taken first. A cross-cutting regression file (5 tests) was added alongside
them.

**Closed 2026-08-21, later the same day — suite 6 (groups / `GroupLink`), 15 tests, 44 assertions.**
It was the largest unwritten suite and it is the one that carried the count past 80. What it found is
in the counterweight below; the short version is that the granular group permission model the routes
advertise **does not resolve at all**.

**Why the count is met and the criterion is not — read this before scoring criterion 2.** WP 0B
crossed three Laravel majors with no behavioural baseline, by explicit owner directive mid-session
(`MEMORY.md` 2026-08-10). The entire purpose of this criterion is to retire that risk.

Until 2026-08-21 this section argued that **79 against a target of 80–120 is not coverage, it is more
coverage.** *That premise expired at 94, and the conclusion did not move.* The gate text asks that
*critical existing behavior* have characterization coverage. It does not ask for a number;
`TESTING_PLAN.md`'s 80–120 is a **proxy** for that, and a proxy stops informing the moment it is
satisfied. **Three suites are unwritten — event management, birthday routes, queues and
notifications — and three more are partial. Scoring criterion 2 met on 94 would be scoring it on a
proxy that has run out.**

**At 94 tests the WP 0B risk is reduced, not retired**, and that is measured rather than cautious:
REG-001 below is a behavioural regression the traversal introduced, found by two of the six suites
that exist. Suite 6 then found three more defects plus the second half of SEC-001, on a surface
nobody had flagged. **Every suite written so far has found something no code reading had found.
Three have not been written.**

**A second, harder caveat, measured 2026-08-21: until now the suite was not deterministic.**
Laratrust caches permissions to the file cache, `DatabaseTransactions` cannot roll that back, and a
stale entry inverted three assertions in a file that session never touched. A clean CI checkout
never accumulates the cache, so CI has been green regardless. Every count recorded before this was
partly luck. `cache:clear` is the interim; `CACHE_STORE=array` in `phpunit.xml` is the fix and is
owner-gated with D3.

**Honest counterweight:** the coverage that *does* exist has already found **twelve** real things —
SEC-001 (`usergroup_id` bypasses all permissions), SEC-002 (attendance has no per-leader scope),
AUTH-001 (registration live although disabled), WP 0C item 3 (`userprofiles` permits duplicate
rows), and as of 2026-08-21 **SEC-003**, **REG-001** and the **SEC-002 export extension** below,
plus suite 6's five: **GRP-001** (deleting a group hard-deletes every member's permission rows,
unscoped, with nothing recording what they were), **GRP-002** (`GroupLinkController::store()` takes
`church_id` from the request body with no `Gate` check — measured writing into another church's
group), **GRP-003** (`group_links` permits duplicate membership), the **dead granular group
permission model** (`routes/web.php`'s `create-/update-/delete-groups` never resolve; `admin.php`
registers last, and `read-groups` alone deletes a group), and the **`Gate::before` half of SEC-001**,
which is the only church scope `GroupsController::show/edit/destroy` have. None were visible from
reading the code. The method is working; there simply is not enough of it yet.

**The three findings of 2026-08-21. SEC-003 was FIXED the same day by owner direction; the other
two are characterized and NOT fixed:**

- **SEC-003 — `/admin/changeavatar` was an unrestricted file upload. FIXED 2026-08-21 (UP-012).**
  No `validate()`, no FormRequest; every file type uploaded with a 200, onto a disk symlinked into
  the webroot, reachable by every church admin and via SEC-001 by any `usergroup_id == 3` account.
  **It was first rated a deployment-dependent RCE and that rating was WRONG** — it came from
  `UploadedFile::fake()`, which derives MIME from the filename. A real upload of PHP source is
  detected `text/x-php`, gets **no extension**, and could never match a `\.php$` handler. The genuine
  vector was **stored XSS**: `.svg` and `.html` are stored under their real extensions and served
  from `/storage/…` on the application's own origin. Fixed by owner direction by type-hinting
  `EditUserProfileImgRequest`, which already existed and was already wired to the API twin.
- **REG-001 — `protected $dates` was removed in Laravel 10 and this application declares it on 37
  models.** 21 date columns across 9 models silently return strings, including
  `EventAttendanceSession::attendance_date`, `EventAttendee::scanned_at` and
  `Userprofile::date_of_birth`. No error is raised. **This is a regression WP 0B introduced**, and it
  is the first demonstrated instance of the traversal breaking working behaviour — precisely the risk
  this criterion exists to retire. The attendance CSV export has been dead since the upgrade as a
  direct consequence.
- **SEC-002 extends to the export surface.** The attendance export never consults `event_managers`
  either; assigned and unassigned leaders reach identical outcomes.

**Why REG-001 matters to the scoring.** It is evidence that the remaining 3 suites are not a
formality. Two suites found a systemic upgrade regression that four earlier suites had not touched;
the birthday suite in particular now has a known dependency on it via `Userprofile::date_of_birth`.
Suite 6 then repeated the pattern on a surface nobody had flagged.

**To close:** 3 suites plus 3 partials. Realistically 1–2 sessions — **not** more tests toward
80–120, which is already met at 94.

---

## 3. Package seam loads without changing product behavior — ✅ **MET**

`custompackages/ifgf/church-operations` scaffolded, loading via `extra.laravel.providers`
auto-discovery (verified in `bootstrap/cache/packages.php`, not assumed). Root `config/app.php`
untouched. Recorded as **UP-010**.

**Proof:** 11 smoke tests in `tests/Feature/Package/PackageProviderSmokeTest.php` covering provider
registration, routes, migration path, views, translations, config merge, commands, policies, the
package test-runner path and the no-product-behaviour guard — each registration kind asserted
*separately*, because a single "the package loads" test would pass on a provider that registered
nothing. Plus 3 framework-free tests in the package's own suite.

**"Without changing product behavior" is guarded, not just claimed:** two tests, one in each suite,
assert the package's `database/migrations/` is empty. The marker policy denies and is asserted to.

---

## 4. `UPSTREAM.md` and the ownership map are reviewed — ❌ **NOT MET**

**Form is good; review has not happened.** UP-001 … UP-011 exist with baseline SHA, files, reason,
alternatives, conflict risk and disposition. But:

- **UP-007 still reads "applied 2026-08-10; NOT yet verified by characterization tests"** — **now
  only partly true, as of 2026-08-21.** `AdminOrPermission` and `User` carry direct coverage
  (`RolePermissionCharacterizationTest`, `ExportCharacterizationTest`). `Role`, `Permission`,
  `Userprofile`, `FeedbackMessage` and `UserprofilePresenter` still do not. Criterion 2 is its blocker
  too. Restatement proposed as ▶ D1 in `WP0A_OWNER_REVIEW.md`.
- **UP-008 is reserved, not landed** — and its scope changed on 2026-08-15 (8 call sites → 7).
- **`phpunit.xml`** — **this review's premise was wrong, corrected 2026-08-21.** It does NOT "appear
  in no entry": UP-005's Files row lists it. The real problem is that the row describes it as
  *"additive test-isolation env only"*, which stopped being true at `f60f1a4` (WP 0A item 5,
  disposable MySQL). Measured against the pin: **29 insertions / 31 deletions**. A stale
  accurate-sounding conflict-risk figure is worse than a missing row.
- **`app/Providers/RouteServiceProvider.php`** — **also corrected 2026-08-21.** It applies
  `['web','auth','churchadmin']` to all of `routes/admin.php`, so it is load-bearing for
  authorization. But `git diff` against **both** `d12c110` (the pin) and `800c29f` (current upstream
  head) reports **IDENTICAL** — this fork has never modified it. "Classify it, add an entry" rests on
  a premise that does not hold, because entries record fork *changes* to upstream-owned files and
  there is no change here. Proposed instead: a `monitor` classification — unmodified,
  authorization-critical, **re-read after every upstream sync**.
- **"Reviewed" means owner-reviewed.** That has not occurred for any entry.

**2026-08-21 — the review pack now exists.** `WP0A_OWNER_REVIEW.md` compresses all eleven entries to
one line each, presents both unclassified files with a measured proposed classification, and carries
the compatibility matrix for criterion 7 in the same sitting. It asks three explicit decisions (D1
UP-007 restatements, D2 `RouteServiceProvider`, D3 `phpunit.xml`) and **deliberately marks neither
criterion met** — same treatment the matrix got on 2026-08-18. The artifact existing is the
deliverable; the owner reading it is the criterion.

**One correction the rehearsal produced:** UP-007 rates its conflict risk **High**. The 2026-08-15
rehearsal *measured* it as clean against `800c29f`. The assessment should be updated to reflect a
measurement rather than a prediction — for the current upstream head only.

**To close:** owner review. The two classification questions are prepared with measured answers in
`WP0A_OWNER_REVIEW.md` (D2 `RouteServiceProvider` → `monitor`; D3 `phpunit.xml` → new UP-012),
plus D1's two UP-007 restatements. Three decisions, one sitting.

---

## 5. CI runs from a clean checkout — ✅ **MET** *(2026-08-18)*

**Branch pushed and CI fully green** — run `32090487802`, both jobs, every step. It now covers all
five things WP 0A item 7 names, including the **frontend production build** that was missing when
this review was first written.

**It took four runs, and three of them failed for real reasons** — which is the argument for this
criterion rather than a footnote to it:

| Run | Failure | What it was |
|---|---|---|
| 1 | 22 × `MissingAppKeyException` | CI wrote a DB-only `.env.testing`; it *replaces* `.env`, not merges. `MEMORY.md` had recorded that exact trap on 2026-08-10 and the workflow still had it — because CI had never run. |
| 2 | 1 × imagick assertion | A characterization test was pinning the dev machine's extension list. GitHub runners ship `imagick`; this one does not. |
| 3 | frontend build | **UP-011.** A real production defect, broken on Linux since before the C5 audit. |
| 4 | — | green |

A CI file that has never run is a hypothesis. This one was wrong in three separate ways.

---

## 6. Upstream merge rehearsal passes — ✅ **MET** *(2026-08-18)*

**Ran for the first time in the project's history on 2026-08-15**, against `upstream/main` =
`800c29f` (40 ahead / 9 behind).

**The merge is clean** — `git merge-tree --write-tree` exits 0 with no conflict output, and the real
merge on a throwaway branch applied cleanly across 8 files with no `composer.json`, migration or
config changes. UP-007's long-standing prediction that `laracasts/presenter` would be the likeliest
conflict **did not materialise**.

**Closed 2026-08-18:** the `merge-rehearsal` job is **green in CI** — clean merge, plus the full
characterization suite *and* the package suite passing on the merged tree. The one test that was red
below has been fixed (it was pinning the dev machine, not the application).

**Historical note — why this was PARTIAL when first reviewed:** the characterization suite on the
merged tree was **47 of 48**. The failure was
`test_documents_defect_member_show_fails_on_missing_imagick`, red **because upstream fixed that
defect** — `800c29f` rewrites `idcard.blade.php` and deletes the `format('png')` QR call. That is a
documenting test behaving exactly as designed, but "the rehearsal passes" cannot be claimed over a
red suite. It needs a deliberate decision, not a silent edit.

*(Both sentences that followed here — "the rehearsal has only ever run locally" and "To close:
resolve the one documenting test, then let the CI job run" — were superseded on 2026-08-18 and are
removed as of 2026-08-21. The job has executed and is green; the documenting test was rewritten
environment-aware. Nothing remains to close on this criterion.)*

---

## 7. Upgrade compatibility matrix is reviewed — ❌ **NOT MET** *(artifact assembled 2026-08-18; review pack 2026-08-21)*

**`UPGRADE_COMPATIBILITY_MATRIX.md` now exists** — the artifact half of this criterion is done.
**Owner review is still outstanding**, exactly as for criterion 4, so this is not MET.

*(Scored 🟡 PARTIAL on 2026-08-18. Restated as ❌ NOT MET on 2026-08-21 for consistency: the gate is
binary per `build.md` OPERATING CONTRACT 10, and a criterion whose text is "X is reviewed" is either
reviewed or it is not. The verdict has not changed in substance — it was never counted as met.)*

**2026-08-21:** carried into `WP0A_OWNER_REVIEW.md` Part 3, **presented unchanged and not
re-verified**, with §7 flagged as the section to read first. This session's REG-001 finding makes §7
item 1 sharper rather than softer: "verified to install, boot and build, not to behave identically"
has now been **demonstrated** by an actual behavioural regression the traversal introduced, not merely
hypothesised.

It covers: selected targets and why (WP 0B item 1), the pinning mechanism, the four-commit traversal
10.50.2 → 11.55.0 → 12.65.0 → 13.24.0 with the blocker resolved at each step (item 3), a per-package
disposition table for everything that blocked / moved / was removed, the MySQL 8.4 review (item 13),
the deferred frontend, the CI verification evidence, and an explicit section on **what it does not
establish**.

Version columns were read from `composer.lock` on 2026-08-18 rather than reconstructed from memory —
which corrected several values I would have got wrong, including the intermediate majors
(11.55.0 and 12.65.0, not the round numbers) and `collision` v8.9.5.

**Historical note — why this was NOT MET when first reviewed:**

no document was identifiable as the matrix. The *inputs* all existed —
`DEPENDENCY_INVENTORY.md`, the `composer why-not` evidence in `MEMORY.md` 2026-08-10, UP-002's PHP
pin rationale, the MySQL 8.4 notes — but nobody had assembled them. **A criterion phrased as "X is
reviewed" needs an X that exists**, and this one had been quietly treated as satisfied by its inputs.

**To close:** owner review, together with criterion 4's ledger review — they are the same sitting.

---

## Summary

| # | Criterion | Verdict (2026-08-21) |
|---|---|---|
| 1 | Installs reproducibly / documented blocker | ✅ **Met 2026-08-18** — proven on a clean Ubuntu checkout |
| 2 | **Critical behavior has characterization coverage** | ❌ **Not met — still the real blocker.** 94 of 80–120 (**count met**); **5 of 11** suites, 3 unwritten, 3 partial |
| 3 | Package seam loads without changing behavior | ✅ **Met** |
| 4 | `UPSTREAM.md` + ownership map reviewed | ❌ Not met — review pack ready, owner review outstanding |
| 5 | CI runs from a clean checkout | ✅ **Met 2026-08-18** — fully green incl. frontend build |
| 6 | Upstream merge rehearsal passes | ✅ **Met 2026-08-18** — `merge-rehearsal` job green in CI |
| 7 | Upgrade compatibility matrix reviewed | ❌ Not met — artifact assembled, owner review outstanding |

## The shortest path to a passing gate

Two items remain, and only one of them is work:

1. **One owner reading session.** `WP0A_OWNER_REVIEW.md` (2026-08-21) carries both the ledger and the
   matrix, and asks three explicit decisions. Closes criteria 4 and 7 together. **Hours.**
2. **Write the 3 remaining characterization suites and finish the 3 partials.** Criterion 2, and it
   also unblocks UP-007's status inside criterion 4. **1–2 sessions.** This is the whole remaining
   cost. The 80–120 count is already met at 94; closing it is **not** what remains.

Neither is blocked on the other. Characterization is the long pole; item 1 is a single sitting.

**Then, and only then, Step 5 — the merge of `contrib/laravel-supported-platform` into `ifgf/main`.**
It is gated on both and is the single hardest action in WP 0A to undo. As of 2026-08-21 it has
correctly **not** been performed.

**WP 0C must not begin.** `build.md` OPERATING CONTRACT 10 prohibits starting a later work package
while an earlier exit gate is incomplete, and WP 0C's own highest-risk items — the `usergroup_id`
replacement across 33 files, the cascade-delete work, the `userprofiles` dedupe — are precisely the
ones characterization coverage exists to make safe.

---

## Re-score, 2026-08-18 — what CI going green actually settled

Run `32090487802`, both jobs, every step green.

**Criterion 1 → MET.** `composer validate --strict`, `composer install` from the committed lockfile,
`npm ci` and `npm run production` all succeed on a clean Ubuntu runner. "Reproducibly" now means
demonstrated, not asserted.

**Criterion 5 → MET.** CI executes and passes, and it now covers the frontend production build that
WP 0A item 7 requires. It took four runs to get here, and each failure was real:

| Run | Failure | What it was |
|---|---|---|
| 1 | 22 × `MissingAppKeyException` | CI wrote a DB-only `.env.testing`; it *replaces* `.env`, not merges |
| 2 | 1 × imagick assertion | A characterization test was pinning the dev machine's extension list |
| 3 | frontend build | **UP-011** — a real production defect, broken on Linux since before the C5 audit |
| 4 | — | green |

**Criterion 6 → MET.** `merge-rehearsal` passes in CI: clean merge against `800c29f`, plus the full
characterization suite and the package suite green on the merged tree.

## What still fails, and it is the same three

- **2 — characterization coverage.** **5 of 11** suites; **the 80–120 count is met at 94, the
  coverage is not.** Verdict unchanged, and still the long pole — 3 suites, now 1–2 sessions.
- **4 — `UPSTREAM.md` and ownership map reviewed.** Owner review; classify `RouteServiceProvider`;
  fold in `phpunit.xml`. UP-007's "High" conflict risk should also be restated as the *measured*
  clean result. Hours.
- **7 — upgrade compatibility matrix.** **Assembled 2026-08-18** as
  `UPGRADE_COMPATIBILITY_MATRIX.md`; owner review outstanding. Closes in the same sitting as 4.

**Criteria 4 and 7 are now a single reading session.** Characterization is the gate.

---

## Re-score, 2026-08-21 — what suites 9, 11 and 6 settled, and what they did not

**No criterion changed verdict. The gate remains 4 of 7 met, 3 not met, ❌ NOT PASSED.**

**Criterion 2 — the numeric target is now MET and the criterion is NOT.** 37 → 62 → 79 → **94**
characterization tests; full `Feature` suite 48/109 → **105 passed, 345 assertions, 0 failed, 0
skipped**, 234s. Suites complete 2 → 4 → 5 → **6** of 11.

This paragraph previously read **"79 against a target of 80–120 is not coverage; it is more
coverage."** **The premise changed on 2026-08-21 and the conclusion did not.** 94 clears 80–120;
criterion 2 asks for coverage of *critical behaviour*; three suites are unwritten and three partial;
it remains ❌ NOT MET. The count was always the weaker half of the claim — suite 5 showed the counts
themselves were cache-dependent until 2026-08-21 — and now that it is satisfied it carries no weight
at all. **Do not score this criterion from the number.**

**Criteria 4 and 7 — the review pack exists; the review does not.** `WP0A_OWNER_REVIEW.md` reduces
both to one sitting and asks three decisions. Two of this review's own premises were **measured and
found wrong** while preparing it:

| Premise (2026-08-17) | Measured 2026-08-21 |
|---|---|
| "`phpunit.xml` appears in no entry" | It appears in **UP-005**. The defect is that UP-005's description of it (*"additive env only"*) went stale at `f60f1a4`; it is 29 insertions / **31 deletions** against the pin |
| "`RouteServiceProvider` should be classified [as an entry]" | It is **byte-identical** to both `d12c110` and `800c29f` — the fork never modified it. An entry would assert a change that does not exist; `monitor` is proposed instead |

**The lesson, and it is the same one as the compatibility matrix on 2026-08-18:** a criterion's
supporting claims decay too, not just its verdict. Both premises were reasonable when written and
neither had been checked against `git diff`. **Measure before classifying.**

**What the new coverage found — and why it argues the remaining 3 suites are not a formality.**
Eight findings across the day. From suites 9 and 11: **SEC-003** (unrestricted file upload; first
mis-rated RCE, actually stored XSS — **fixed the same day, UP-012**), **REG-001** (`protected $dates`
inert since Laravel 10; 21 columns across 9 models silently uncast), and the **SEC-002 export
extension**. From suite 6: **GRP-001**, **GRP-002**, **GRP-003**, the **dead granular group
permission model**, and the **`Gate::before` half of SEC-001**.

REG-001 is the one that bears on the gate. It is **the first demonstrated case of WP 0B's 10 → 13
traversal breaking working behaviour** — the attendance CSV export has been dead since the upgrade
and nobody knew. WP 0B item 6 was skipped by owner directive precisely on the bet that this class of
thing would not happen. Criterion 2 exists to retire that bet, and it has now paid out once. Two
suites found it; **three remain unwritten**.

**Step 5 (merge to `ifgf/main`) correctly NOT performed.** Gated on criterion 2 being green and on
the owner sign-off of 4 and 7. Neither holds.

**WP 0C must not begin.** Unchanged. `build.md` OPERATING CONTRACT 10.
