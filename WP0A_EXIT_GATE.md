# WP 0A exit gate review

**Date:** 2026-08-17 · **Re-scored:** 2026-08-18 after CI went green · **Branch:** `contrib/laravel-supported-platform`
**Reviewed against:** `build.md` L225–228 (Work Package 0A exit gate)

---

## Verdict: ❌ **NOT PASSED** — but materially closer

**Re-scored 2026-08-18: 4 of 7 criteria met, 0 partial, 3 not met** (was 1 met / 2 partial / 4 not
met). Criteria 1, 5 and 6 closed when CI ran fully green for the first time — run `32090487802`,
both jobs, every step, including the frontend production build on Linux.

`build.md` OPERATING CONTRACT 10 and the Step 7 instruction are both explicit: the gate is not
marked passed if any criterion fails. **It still fails three.**

The single blocking criterion is **characterization coverage**. Everything else is either met, or
fails for a reason that is cheap to fix — most of them fail for the *same* reason, below.

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

**This is the real blocker, and it is not close.**

| | |
|---|---|
| Characterization tests | **37** |
| Target (`TESTING_PLAN.md` Part 1) | **80–120** |
| Suites complete | **2 of 11** (roles+permissions, attendance) |
| Suites partial | **2** (auth, member profile) |
| Suites not started | **7** |

Not started: **QR / membership card, groups, event management, birthday routes, exports, queues and
notifications, private media and storage.** Partial gaps: auth owes password reset, email
verification, session lifetime and throttling; member profile owes create/edit/delete/export
behaviour beyond render and authorization.

**Why this matters more than the count suggests.** WP 0B crossed three Laravel majors with no
behavioural baseline, by explicit owner directive mid-session (`MEMORY.md` 2026-08-10). The entire
purpose of this criterion is to retire that risk. At 37 tests it is reduced, not retired. Private
media and exports are wholly uncharacterized and both touch member PII.

**Honest counterweight:** the coverage that *does* exist has already found four real things —
SEC-001 (`usergroup_id` bypasses all permissions), SEC-002 (attendance has no per-leader scope),
AUTH-001 (registration live although disabled), and WP 0C item 3 (`userprofiles` permits duplicate
rows). None were visible from reading the code. The method is working; there simply is not enough of
it yet.

**To close:** 7 suites. Realistically 3–4 sessions.

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

**Form is good; review has not happened.** UP-001 … UP-010 exist with baseline SHA, files, reason,
alternatives, conflict risk and disposition. But:

- **UP-007 still reads "applied 2026-08-10; NOT yet verified by characterization tests"** — and that
  is still true for the authorization-surface files it names (`User`, `Role`, `Permission`,
  `Userprofile`, `FeedbackMessage`, `AdminOrPermission`, `UserprofilePresenter`). Criterion 2 is its
  blocker too.
- **UP-008 is reserved, not landed** — and its scope changed on 2026-08-15 (8 call sites → 7).
- **`phpunit.xml` was modified during WP 0B and appears in no entry.**
- **`app/Providers/RouteServiceProvider.php` sits in "Not yet classified"** — and it has since been
  read during Step 6 and confirmed to apply `['web','auth','churchadmin']` to all of
  `routes/admin.php`, which makes it load-bearing for authorization. It should be classified.
- **"Reviewed" means owner-reviewed.** That has not occurred for any entry.

**One correction the rehearsal produced:** UP-007 rates its conflict risk **High**. The 2026-08-15
rehearsal *measured* it as clean against `800c29f`. The assessment should be updated to reflect a
measurement rather than a prediction — for the current upstream head only.

**To close:** owner review; classify `RouteServiceProvider`; add or fold in `phpunit.xml`.

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

**And:** the rehearsal has only ever run locally. The CI job that automates it has never executed —
same root cause as criterion 5.

**To close:** resolve the one documenting test, then let the CI job run.

---

## 7. Upgrade compatibility matrix is reviewed — ❌ **NOT MET**

**No document in the repository is identifiable as the upgrade compatibility matrix.**

The *inputs* exist and are substantial: `DEPENDENCY_INVENTORY.md` (production and dev packages,
abandoned packages, known vulnerabilities, framework constraints), the `composer why-not` findings
and per-major upgrade evidence in `MEMORY.md` 2026-08-10, the PHP pin rationale in UP-002, and the
MySQL 8.4 compatibility notes (`mysql_native_password` default change, one-way 8.0→8.4 upgrade).

What is missing is the **assembled matrix** — target versions against each traversed major, with the
compatibility decision and evidence per package — and any record of owner review of it.

**To close:** assemble from existing material; this is compilation, not investigation. Low effort,
and it is the deliverable that makes the whole upgrade auditable.

---

## Summary

| # | Criterion | Verdict |
|---|---|---|
| 1 | Installs reproducibly / documented blocker | ✅ **Met 2026-08-18** — proven on a clean Ubuntu checkout |
| 2 | **Critical behavior has characterization coverage** | ❌ **Not met — the real blocker** |
| 3 | Package seam loads without changing behavior | ✅ **Met** |
| 4 | `UPSTREAM.md` + ownership map reviewed | ❌ Not met — owner review outstanding |
| 5 | CI runs from a clean checkout | ✅ **Met 2026-08-18** — fully green incl. frontend build |
| 6 | Upstream merge rehearsal passes | ✅ **Met 2026-08-18** — `merge-rehearsal` job green in CI |
| 7 | Upgrade compatibility matrix reviewed | ❌ Not met — not assembled |

## The shortest path to a passing gate

Four of the six failures share two causes, and neither is characterization:

1. **Push the branch once.** Closes criterion 5, completes criterion 1, and lets the merge-rehearsal
   job in criterion 6 actually run. Add the frontend build step first. **Hours.**
2. **Assemble the compatibility matrix** from material that already exists. Criterion 7. **Hours.**
3. **Owner review of `UPSTREAM.md`**, plus classifying `RouteServiceProvider` and `phpunit.xml`.
   Criterion 4. **Hours.**
4. **Write the 7 remaining characterization suites.** Criterion 2, and it also unblocks UP-007's
   status inside criterion 4. **3–4 sessions.** This is the whole cost.

Nothing here is blocked on anything else. Characterization is the long pole; the other five are a
day's work between them.

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

- **2 — characterization coverage.** 4 of 11 suites. Unchanged, and still the long pole at 3–4
  sessions.
- **4 — `UPSTREAM.md` and ownership map reviewed.** Owner review; classify `RouteServiceProvider`;
  fold in `phpunit.xml`. UP-007's "High" conflict risk should also be restated as the *measured*
  clean result. Hours.
- **7 — upgrade compatibility matrix.** Still not assembled. Compilation from existing material,
  not investigation. Hours.

Two of the three are a morning's work. Characterization is the gate.
