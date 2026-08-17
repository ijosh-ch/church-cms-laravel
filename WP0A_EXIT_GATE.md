# WP 0A exit gate review

**Date:** 2026-08-17 · **Branch:** `contrib/laravel-supported-platform` · **HEAD:** `c3dc255`
**Reviewed against:** `build.md` L225–228 (Work Package 0A exit gate)

---

## Verdict: ❌ **NOT PASSED**

**1 of 7 criteria fully met, 2 partial, 4 not met.** `build.md` OPERATING CONTRACT 10 and the Step 7
instruction are both explicit: the gate is not marked passed if any criterion fails. It fails four.

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

## 1. Source installs reproducibly, or has a documented blocker — 🟡 **PARTIAL**

**Evidence for:** `composer install` succeeds; `composer validate --strict` is clean including the
new path package; `composer.lock` is committed and resolves the IFGF package. `npm run production`
builds locally (exit 0, ~290s).

**Why not full:** "Reproducibly" means *on a clean checkout*, and that has never been demonstrated —
see criterion 5. Every installation to date has been on this one Windows machine with a warm
Composer cache. The 166 npm vulnerabilities (17 low, 78 moderate, 58 high, 13 critical) and the Vue 2
EOL ReDoS advisory are **documented** in `DEPENDENCY_INVENTORY.md`, which satisfies the "documented
blocker" half for the frontend.

**To close:** push the branch so CI executes once. That is the whole fix.

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

## 5. CI runs from a clean checkout — ❌ **NOT MET**

**`.github/workflows/ci.yml` has never executed. Not once.**

`git ls-remote --heads origin contrib/laravel-supported-platform` returns nothing and the branch has
no upstream tracking. **All 15 commits are local.** The workflow is comprehensive — Ubuntu, PHP 8.4,
MySQL 8.4 service, `composer validate --strict`, audit, PSR-4 compliance, `migrate:fresh --seed`,
`artisan test`, the package suite, and the merge-rehearsal job — but a CI file that has never run is
a hypothesis, not a gate.

**Also missing:** the **frontend build**. WP 0A item 7 requires "dependency installation, static
validation, database migration, backend tests, **and frontend production build**". There is no
`npm ci` / `npm run production` step.

**To close:** add the frontend build step, then push once and read the result. This is the
cheapest-to-close failing criterion and it also closes criterion 1.

---

## 6. Upstream merge rehearsal passes — 🟡 **PARTIAL**

**Ran for the first time in the project's history on 2026-08-15**, against `upstream/main` =
`800c29f` (40 ahead / 9 behind).

**The merge is clean** — `git merge-tree --write-tree` exits 0 with no conflict output, and the real
merge on a throwaway branch applied cleanly across 8 files with no `composer.json`, migration or
config changes. UP-007's long-standing prediction that `laracasts/presenter` would be the likeliest
conflict **did not materialise**.

**Why not full:** the characterization suite on the merged tree is **47 of 48**. The failure is
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
| 1 | Installs reproducibly / documented blocker | 🟡 Partial — never proven on a clean checkout |
| 2 | **Critical behavior has characterization coverage** | ❌ **Not met — the real blocker** |
| 3 | Package seam loads without changing behavior | ✅ **Met** |
| 4 | `UPSTREAM.md` + ownership map reviewed | ❌ Not met — owner review outstanding |
| 5 | CI runs from a clean checkout | ❌ Not met — **has never executed** |
| 6 | Upstream merge rehearsal passes | 🟡 Partial — merge clean, one documenting test red |
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
