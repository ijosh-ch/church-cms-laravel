# Project requirements review

**Reviewed:** 2026-08-11  
**Branch:** `contrib/laravel-supported-platform` at `9120af9`  
**Reviewed upstream:** `d12c110`  
**Method:** targeted PRD/build reads, AGENTS/CLAUDE context, current git and test state, and Graphify queries against `graphify-out/graph.json`

## Verdict

The project does **not yet meet the original product requirements**. The PRD is a strong and mostly complete articulation of the requested IFGF Taipei/Zhongli system, but the repository is still in Phase 0. It currently contains an upgraded ChurchCMS foundation, not the completed IFGF product.

Work Package 0B is substantially complete: Laravel 13.24.0, PHP 8.4.24, and MySQL 8.4 are established. Work Package 0A is not complete because the package seam, characterization safety net, provider smoke tests, frontend CI build, and upstream merge rehearsal are still missing. FR-01 through FR-14 are therefore mostly planned rather than implemented.

Existing ChurchCMS features are useful compatibility anchors. Their presence does not satisfy the adapted requirements by itself. The required three-role policy, package-owned IFGF behavior, sidecar schema, opaque rotatable member QR, common attendance service, migration provenance, iCare history, CGSL, ministry, Calendar adapter, private media, and scoped reporting have not landed.

## Evidence boundary

The review used the current repository and the PRD evidence inventory. It could not independently re-open the authoritative Google Apps Script repository or workbook from this workspace. The project memory states that those sources outrank the PRD for existing required data and main functions. Requirements cannot be called fully revalidated until a future session can read those sources or a committed anonymized structural extract.

The Graphify graph was queried, but it was generated on 2026-08-09 and predates the Laravel 13/document changes. It accurately exposes legacy anchors such as `Role`, `Permission`, `Userprofile`, `EventAttendanceSession`, `MembershipCardController`, `RouteServiceProvider`, and `composer.json`; it is not current enough to prove completion. The current filesystem was used for completion claims.

## Work-package coverage

| Area | State | Evidence and remaining gate |
|---|---|---|
| WP 0A baseline inventory | Mostly complete | Dependencies, routes, migrations, safety database, upstream ledger, and Graphify ignore policy exist. |
| WP 0A characterization | **Not complete** | Two real test files exist. The required legacy behavior surface needs roughly 11 suites, including auth, permissions, profiles, events, attendance, QR, groups, birthday routes, exports, queues, and private media. |
| WP 0A package seam | **Missing** | `custompackages/ifgf/church-operations` does not exist; root `composer.json` has no package path repository. |
| WP 0A CI | Partial | Linux install, migration, backend test, and PSR-4 checks exist. The frontend production build, package provider smoke tests, and upstream merge rehearsal are absent. `composer audit || true` is reporting-only. |
| WP 0A branch topology | Partial | `main` and `ifgf/main` exist at `aa8194e`; `deploy` does not exist, correctly. The supported-platform series has not been merged into `ifgf/main`, and nothing is pushed for this branch. |
| WP 0B supported platform | Substantially complete | Laravel 13.24.0 and PHP 8.4.24 landed. Closure remains blocked by characterization, frontend decision evidence, current-upstream replay, and merge rehearsal. |
| WP 0C and WP 0D | Not started | No IFGF package schema, migration pipeline, authorization cutover, privacy review, or data-owner approval. |
| FR-01 through FR-13 | Planned, not delivered | No package-owned services, sidecars, routes, or acceptance suites implement the target contracts. |
| FR-14 biometrics | Correctly deferred | Phase 2 non-goal for first production release; no implementation should begin now. |

## Findings, ordered by risk

### P0: PRD and timezone decision disagree

PRD lines 859, 877, 909, and 1502 require UTC occurrence keys and UTC production timestamps. The uncommitted `build.md`, `UPSTREAM.md` UP-009, `.env.example`, and `config/database.php` changes instead establish Taipei wall-clock semantics and a `+08:00` MySQL session.

The focused `TimezoneCharacterizationTest` passes locally with 4 tests and 6 assertions. That proves the proposed configuration is internally consistent on this machine; it does not resolve the requirements conflict. `build.md` explicitly cannot change PRD scope. The owner must either amend the PRD and its occurrence identity contract or retain UTC storage and limit `Asia/Taipei` to application/display conversion.

### P0: the new timezone test will fail in CI as currently written

The CI workflow creates `.env.testing` without `TIMEZONE=Asia/Taipei`. Laravel testing loads `.env.testing`, while `config/app.php` defaults a missing `TIMEZONE` to UTC. The new test expects `Asia/Taipei`; the committed workflow does not provide it. Add the chosen timezone explicitly to the CI test environment after the owner resolves the storage contract.

This was reproduced by running the focused test with process `TIMEZONE=UTC`: 2 tests failed and 2 passed. The application and PHP timezone assertions received UTC; the separately hard-coded MySQL `+08:00` assertions passed.

### P0: characterization scope is materially incomplete

`build.md` WP 0A item 6 requires authentication, roles and direct permissions, member profile, group access, event management, attendance, member QR, birthday routes, exports, queues, and private media. `TESTING_PLAN.md` previously listed only seven suites and omitted event management, birthday routes, queues, and private media.

Only these tests currently exist:

1. `MemberImportCharacterizationTest`, a header-only import boundary test.
2. `TimezoneCharacterizationTest`, currently untracked and focused on configuration rather than the attendance workflow.

This is far below the behavioral baseline needed to support a three-major Laravel upgrade or authorization cutover.

### P0: authoritative legacy requirements are unavailable here

The owner decision in PRD Section 13.1.20 makes the workbook and Google Apps Script authoritative for existing required data and main functions. They are not accessible from this workspace. WP 0C must not infer their schema or behavior from memory. Provide both sources to the working session or add an anonymized structural extract before member migration work.

### P1: the mandatory IFGF package boundary is absent

The PRD, build invariants, and agent instructions all require new IFGF behavior in `custompackages/ifgf/church-operations`. That directory and its Composer path loading do not exist. No product feature should be added until the package provider and smoke tests load from a clean checkout.

### P1: CI does not meet its own WP 0A contract

`.github/workflows/ci.yml` does not run `npm ci` or `npm run production`, even though WP 0A requires a frontend production build. It also lacks the read-only upstream merge rehearsal and package provider smoke tests. Known Composer advisories are non-blocking because the workflow uses `composer audit || true`; that may be acceptable only while every remaining advisory has an explicit accepted disposition.

### P1: authorization is still legacy behavior, not the approved model

The graph and source inventory show current `Role`, `Permission`, `usergroup_id`, group middleware, direct grants, and Gate bypasses. The approved target requires exactly one top-level `member`, `leader`, or `admin` role, plus assignment-scoped leader access. Characterization must document every legacy bypass before WP 0C removes it. A green defect-characterization test must be named as a defect, not read as approval.

### P1: project state documents are stale

After fetching upstream, the current branch is 26 commits ahead and 8 behind `upstream/main`. `CONTEXT.md` still names an older HEAD and 17 commits. `TODO.md` says 14 local commits. The graph predates the current HEAD. Future sessions could therefore choose the wrong baseline unless these facts are refreshed.

### P1: the schema dump needs one authoritative disposition

`database/schema/mysql-schema.sql` is an untracked Laravel schema dump and makes the slow characterization program practical. A separate tracked root `mysql-schema.sql` already exists. The project needs to define which file is an application bootstrap artifact and which is a legacy/source reference. Do not leave two identically named schema files unexplained.

### P2: UP-009 status overstates git state

Before this review, UP-009 said the change was applied and “staged with this entry,” although the timezone files and test were unstaged/untracked. The entry now says `pending review`; it must remain that way until the owner approves the requirements resolution and the files are actually staged.

### P2: known defects remain deliberately unfixed

The non-empty member import crashes through an undefined `$request`; the push-notification trait references unavailable `LaravelFCM` classes; `FeedbackMessage` names a missing presenter; the QR PNG renderer requires absent Imagick. These are correctly tracked as separate fixes, but the first three need characterization and scheduling before their surfaces are extended.

## Owner questions

1. **Timestamp contract, blocking:** Should production persist Taipei wall-clock values, amending PRD lines 859, 877, 909, and 1502, or should timestamps remain UTC with `Asia/Taipei` used only for input/display? The current documents authorize both and cannot remain that way.
2. **Legacy evidence, blocking before WP 0C:** Will future sessions receive the GAS repository and workbook as additional workspace roots, or should an anonymized structural extract be committed here?
3. **Schema dump:** Should `database/schema/mysql-schema.sql` be committed as Laravel's test bootstrap? If yes, what is the intended role of the older root `mysql-schema.sql`: retained legacy reference, renamed reference, or removed in a separately reviewed change?
4. **CI security policy:** Is reporting known Composer advisories without failing CI accepted through WP 0A, or should unaccepted critical/high findings block now? The choice should be explicit in `DEPENDENCY_INVENTORY.md` and the CI comment.
5. **Frontend gate:** Is the current Vue 2/Mix 4 production build an explicitly accepted temporary WP 0A baseline, with Vite deferred, or must the Vite migration finish before WP 0A/0B can formally close? Existing notes say both “deferred” and “Phase 0 gate.”

## Recommended next sequence

1. Resolve owner question 1 and reconcile PRD, build, UPSTREAM, test environment, and CI as one documentation/configuration decision.
2. Commit or reject the Laravel schema dump, then confirm the full suite no longer spends eight minutes replaying 93 migrations per test class.
3. Characterize the membership-card/QR route, apply the approved PNG-to-SVG renderer fix, and close UP-008.
4. Complete all WP 0A characterization suites, beginning with roles/direct permissions and attendance, including denial and defect cases.
5. Scaffold `custompackages/ifgf/church-operations`, add provider smoke tests, and keep it behavior-free.
6. Add the frontend build and read-only upstream merge rehearsal to CI; replay the platform series against current upstream.
7. Formally close WP 0A/0B, merge the reviewed series into `ifgf/main`, and only then start WP 0C with the authoritative legacy sources available.

## Completion rule

Do not describe the project as meeting the original requirements until every Release 1 acceptance row in PRD Section 11 has evidence, the migration reconciles every non-empty authoritative source row, the three-role cutover has no legacy bypass, private media and Calendar privacy gates pass, mobile and recovery tests pass, and the production gate is explicitly approved. The present repository is a promising foundation, not that finished state.
