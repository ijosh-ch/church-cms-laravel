# Claude Cowork Opus 5 handoff prompt

Paste the prompt below into a fresh Claude Cowork task with this repository selected. Also select the Google Apps Script repository and anonymized workbook/extract if the owner chooses that option in `REVIEW.md`.

---

You are Claude Opus 5 in Cowork. Continue the ChurchCMS-to-IFGF project from the review dated 2026-08-11. This is a Work Package 0A/0B closeout session, not a product-feature session.

First follow `CLAUDE.md` exactly: run the prescribed git/status preamble, read `CONTEXT.md`, `TODO.md`, the newest five `MEMORY.md` entries, and `CLAUDE.md`, then only the line ranges named by the active item. Read `REVIEW.md` in full. Preserve every existing dirty-worktree change. Never commit, push, deploy, touch real member data, or use live Google Calendar.

Cowork cannot run PHP, Composer, Artisan, MySQL, or Graphify CLI. It may read/write selected Windows files and inspect git. Batch every required host command into one owner handoff. The existing Graphify graph is stale and may be used only as a legacy architecture index; verify current claims against exact files.

## Owner answers required before execution

Copy the owner's answers here before starting:

- Timestamp contract: `[TAIPEI_WALL_CLOCK or UTC_STORAGE]`
- Legacy evidence access: `[ADDITIONAL_WORKSPACE_ROOTS or COMMITTED_ANONYMIZED_EXTRACT]`
- Laravel schema dump: `[COMMIT_DATABASE_SCHEMA, IGNORE_IT, or NEEDS_REVIEW]`
- Composer-audit CI policy: `[REPORT_ONLY_UNTIL_WP0A or BLOCK_UNACCEPTED_HIGH_CRITICAL]`
- Frontend gate: `[ACCEPT_MIX4_TEMPORARILY or REQUIRE_VITE_BEFORE_CLOSE]`

If the timestamp answer is absent, do not edit PRD, build, timezone configuration, UPSTREAM, or CI. Present REVIEW.md question 1 and stop that part of the work. Do not infer the choice from the current dirty files because those files conflict with the approved PRD.

## Session scope

Resolve the review inconsistencies and leave one coherent, testable WP 0A baseline. Do not start WP 0C or any FR-01 through FR-14 product implementation.

1. Reconcile the chosen timestamp contract everywhere it is normative. Targeted PRD anchors are lines 859, 877, 909, and 1502; re-derive `EXECUTION_PLAN.md` Appendix A after any PRD edit. If Taipei wall-clock is approved, make the deviation explicit in PRD Section 13.1 as an owner decision and update the occurrence identity language. If UTC storage is approved, revise or revert the conflicting UP-009/build/config proposal without destroying unrelated user edits.
2. Make `.github/workflows/ci.yml` create a test environment consistent with the chosen contract. The current untracked timezone test passes locally but fails when `TIMEZONE=UTC` is simulated because `.env.testing` lacks the chosen `TIMEZONE`.
3. Apply the owner's schema-dump disposition. If committing `database/schema/mysql-schema.sql`, document the role of the tracked root `mysql-schema.sql`; do not delete either file unless the owner explicitly chose that outcome.
4. Reconcile `TESTING_PLAN.md` with WP 0A item 6. The baseline requires auth, roles/direct permissions, member profile, event management, attendance, member QR, groups, birthday routes, exports, queues, and private media. Do not silently reduce this to seven suites.
5. Correct factual state drift in `CONTEXT.md`, `TODO.md`, `UPSTREAM.md`, and `PRD_OPEN_QUESTIONS.md`. As of the review, HEAD is `9120af9`; the branch is 26 ahead and 8 behind `upstream/main`; the IFGF package and merge rehearsal are absent; two real test files exist; the Graphify graph is stale. UP-009 must not claim files are staged when they are not.
6. Keep the literal next action in `TODO.md`. Unless the owner changes priority, it is: resolve the timestamp/CI baseline, settle the schema dump, then characterize and apply the approved QR PNG-to-SVG fix under UP-008.
7. Do not scaffold the IFGF package, write product features, or begin the full characterization implementation in this same session unless all review reconciliation fits and the owner explicitly extends scope. The repository prohibits starting a second work package or an exit gate that will not fit.

## Verification handoff

Because Cowork cannot execute the toolchain, provide one copy-paste PowerShell block using `C:\php\8.4\php.exe` explicitly. It must run only focused, non-destructive checks first. Any `migrate:fresh`, `db:wipe`, or reset command must first print and assert environment, driver, host, and database name and must target only `churchcms_test_disposable`.

At minimum request:

```powershell
& 'C:\php\8.4\php.exe' artisan test tests/Feature/Attendance/TimezoneCharacterizationTest.php 2>&1 | Select-Object -Last 80
& 'C:\php\8.4\php.exe' artisan test 2>&1 | Select-Object -Last 120
```

Add `composer validate --strict`, the selected Composer audit policy, and frontend build commands only when their executables and script names have been verified from the repository. Never use `--ignore-platform-reqs`, `--no-verify`, or a forced dependency resolution.

## Exit gate

Return:

1. The resolved owner decisions and exact normative files reconciled.
2. Exact changed files with upstream/IFGF/document ownership.
3. Commands the owner ran, with pass/fail/skipped counts.
4. Remaining WP 0A/0B exit-gate items.
5. Graph staleness status.
6. A proposed commit message in the repository SOP format, but do not stage or commit until the owner reviews it.

---
