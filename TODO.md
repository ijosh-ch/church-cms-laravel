# TODO

> Item 1 is always the literal next action. Rewritten every session end.
> Hard cap: 1,500 tokens — archive completed items to `MEMORY.md`, do not accumulate them here.
> Every item names its `build.md` item number and its `PRD.md` line range where relevant.

**Active work package:** 0A — Baseline and Safety Net (`build.md` L202–228)
**Session:** 3 → 4

---

## Execution readiness — checked 2026-08-10

**Versions: settled.** Re-verified today, not carried forward from an earlier check.

| | Target | Status |
|---|---|---|
| Laravel | **13.x** — min PHP 8.3, supports 8.3–8.5 | Confirmed current. Laravel 10 unsupported since Feb 2025 |
| PHP | **8.4** for production; 8.3.33 installed and valid for the L10 baseline and L13 | 8.3 loses *active* support 2026-11-23; 8.4 has security to Dec 2028. Pin at WP 0B item 14 |
| MySQL | **8.4 LTS** — extended support to ~2032 | 8.0 reached EOL April 2026. 9.x are Innovation releases, not LTS — do not target |

**Repository: not ready.** WP 0A is roughly 5 of 15 items done. Seven gates remain:

| # | Gate | State | `build.md` |
|---|---|---|---|
| 1 | **Nothing from Session 3 is committed** — 13 files including the `PRD.md` amendments | 🔴 | CONTRACT 6 |
| 2 | **No `ifgf/main` branch, no `deploy`** — work-package branches have no base | 🔴 | 0A item 12 |
| 3 | **No `custompackages/ifgf/church-operations`** — nowhere for new code to legally live | 🔴 | 0A item 11 |
| 4 | **No CI** — would have caught all four upstream defects before they cost round-trips | 🔴 | 0A items 7, 13 |
| 5 | **4 test files** — characterization coverage barely begun | 🔴 | 0A item 6 |
| 6 | **`php artisan test` throws `RequirementsException`** — collision/PHPUnit 10 mismatch | 🔴 | 0A item 6 |
| 7 | **No disposable test database** | 🔴 | 0A item 5 |

**Verdict: versions are settled, the repository is not.** Do not start WP 0B until gates 1–7 close
— an upgrade without characterization tests cannot be distinguished from an upgrade that broke
something.

### Order to close them

1. **Commit Session 3** (gate 1) — largest risk right now is losing uncommitted work.
2. **Branch topology** (gate 2) — cheap, unblocks everything after it.
3. **Fix `artisan test`** (gate 6) — nothing else in 0A is verifiable while the runner is broken.
4. **Test database** (gate 7) then **CI** (gate 4) — pulled forward from Session 13 deliberately.
5. **Package scaffold** (gate 3).
6. **Characterization tests** (gate 5) — the long pole, ~3 sessions at Release 1 scope.
7. **WP 0A exit gate review**, then WP 0B.

---

## ⚠ Awaiting owner approval — proposed release split

`PRODUCTION_PATH.md` proposes shipping **Release 1 = FR-01, 02, 03(minimal), 04, 08, 10(minimal),
11, 12** and deferring FR-05, 06, 07, 09, 13 to Release 2. Evidence: those five have **no
counterpart in the running system** — the iCare attendance sheet is empty, online attendance is 3
rows of 3,227, and CGSL/ministries/registration appear nowhere in the workbook or script.
Deferring them removes nothing the church has today. **Estimated ~35% less work to production
(68–83 sessions vs 100–125).**

Three decisions needed: (1) approve/reject the split, (2) **Q3 home branch** — last WP 0C blocker,
(3) **C1** — confirm every member QR is reissued at cutover.

**Two things that cannot be deferred:** Laravel 10 lost security support in **February 2025**, so
WP 0B is a hard production blocker; and **FR-11 must move earlier** — `usergroup_id` in 33 files is
the largest correctness risk and cannot be retrofitted after member and attendance ship.

---

## PRD — 16 open questions now tracked

`PRD_OPEN_QUESTIONS.md` triages `PRD.md` §13.2 (L1713–1730): 1 resolved, 15 open, each mapped to
the work package it blocks. **Two block WP 0C and need answers before Session 6:**

- **Q3 — home branch mandatory or optional?** `PRD.md` **contradicts itself**: L402 says optional
  (`Branch "0..1"`), L973 lists it as a required field. Fix both lines whichever way it resolves.
- **Q2 — which source wins when workbook and IFGF export disagree?** Recommendation is to defer
  the final answer until WP 0C's dry-run reports actual conflict volume, and build the pipeline
  assuming manual resolution until then.

Also note **Q14** (storage provider/region for member images) is needed at **Phase 1A.5**, not
Phase 1E as its wording suggests, and is inseparable from WP 0D's cross-border privacy review.

---

## Now — commit Session 3, then Session 4

1. **Commit Session 3.** Two commits, both currently uncommitted in the working tree:
   - `.graphifyignore` (staged, WP 0A item 15, pulled forward from Session 5) — commit with the
     graph-rebuild evidence.
   - `ROUTE_MIGRATION_INVENTORY.md` + `MEMORY.md` + `CONTEXT.md` + `TODO.md` (WP 0A item 4a).

2. **Cross-check the inventory against a booted app.** `ROUTE_MIGRATION_INVENTORY.md` §6 is
   explicit that it was produced by static analysis with no PHP. Run
   `php artisan route:list --json` and reconcile the 812-route count and the middleware map.
   Middleware applied in **controller constructors** is not captured by the static pass —
   `Auth/VerificationController.php:36` proves that pattern is in use, so the real authorization
   posture may be broader than the route files show. Record deltas in the inventory.

3. **Then Session 4 — WP 0A item 4b.** Inventory auth, roles, attendance, membership cards,
   exports, media, storage, queues, scheduler. Note from item 4a: `console.php` defines **no**
   scheduled tasks, so the scheduler section of 4b is expected to come back empty — confirm that
   rather than assume it.

## Next — Work Package 0A (`build.md` L207–224)

| Session | Item(s) | Action |
|---|---|---|
| 4 | 4b | Inventory auth, roles, attendance, membership cards, exports, media, storage, queues, scheduler. |
| 5 | — | *(freed — item 15 `.graphifyignore` was pulled forward into Session 3)* |
| 6 | 9, 10 | Pin upstream SHA, record divergence, create `UPSTREAM.md` compatibility ledger and the ownership map. Formalizes the freeze already recorded in `UPSTREAM.md`'s "Pin decision — 2026-08-09" note. |
| 7 | 11 | Scaffold `custompackages/ifgf/church-operations` with path loading and auto-discovery. No product behavior. |
| 8 | 14 | Provider smoke tests from a clean checkout. |
| 9–11 | 6 | Characterization tests — auth/roles, then member/groups/events, then QR/attendance/exports/media. `tests/Feature/Admin/MemberImportCharacterizationTest.php` (Session 2b) is the first, not the last. Verify no Markdown-mail template interpolates unescaped user text (league/commonmark exposure check, deferred from Session 2). |
| 12 | 5, 8 | Disposable MySQL 8.4 test database + anonymized fixtures. No real member data. `phpunit.xml` currently points the suite at sqlite `:memory:` as a Session 2b stopgap — replace with the real fixture DB here, don't just add to it. |
| 13 | 7, 13 | CI workflow + read-only upstream merge rehearsal. Must include a PSR-4 autoload-warning check. **Fix the `nunomaduro/collision`/PHPUnit 10 version mismatch first** (Session 2b finding — `php artisan test` currently throws `RequirementsException`; `vendor/bin/phpunit` works as a workaround but CI needs `artisan test` or an equivalent direct `phpunit` invocation either way). |
| 14 | 12, gate | Branch topology (`main` / `ifgf/main` / `deploy`) + WP 0A exit gate review. |

## Blocked

- **WP 0B** — cannot start until the WP 0A exit gate passes. Findings staged in
  `EXECUTION_PLAN.md` §1.4 and `DEPENDENCY_INVENTORY.md`; `laravel/legacy-factories` is a hard
  blocker.

## Decisions awaiting the owner

- Production PHP pin: 8.4 recommended, subject to the extension audit *(WP 0B item 14)*.
- Vue 2 / laravel-mix 4 → Vite: proposed as a separate IFGF-neutral package *(WP 0B item 8)*.
  `DEPENDENCY_INVENTORY.md` lists the 14 npm packages this migration replaces wholesale.
- Whether `barryvdh/laravel-dompdf`/`dompdf/dompdf` (confirmed unused, 6 advisories, zero call
  sites) should be removed or kept for planned-but-unbuilt PDF export. **Ask before Session 4** —
  PRD scope decides this, not the audit.
- Which of `yarn.lock` / `package-lock.json` survives. `npm ci` is proven; recommend deleting
  `yarn.lock`. Defer until the Vite decision is in view so it is not decided twice.
- **New, Session 2b:** `app/Imports/UsersImport.php`'s `collection()` method dereferences an
  undefined `$request` variable as soon as any import file has at least one data row — a PHP
  `Error`, not caught by its own `catch (Exception $e)`. **Member import is currently broken for
  every real (non-empty) file**, independent of the `phpoffice/phpspreadsheet` upgrade. Not fixed
  in Session 2b (out of UP-005's "upgrade, don't fix business logic" scope) — see `UPSTREAM.md`
  UP-005 "Alternatives considered". **Ask whether this is worth an out-of-band fix before Session
  9–11's member/groups/events characterization work reaches it,** or whether it stays a documented
  known-broken path until then.
- **New, Session 2b:** `app/Traits/SendPushNotification.php` imports and instantiates
  `LaravelFCM\Message\OptionsBuilder`/`PayloadNotificationBuilder` — classes that were never in the
  autoloader even before UP-006 removed the orphaned `brozot/laravel-fcm` directory (confirmed via
  `class_exists()`, see `UPSTREAM.md` UP-006). Push notifications via this trait have been silently
  broken pre-existing. Rewriting it to call the already-installed
  `laravel-notification-channels/fcm` is a real fix, not a removal, so it needs its own
  characterization test + UPSTREAM.md entry — **ask whether to schedule this**, and if so where
  (Session 4's "auth, roles, attendance..." inventory touches related notification listeners).

## Resolved — 2026-08-09

- `phpoffice/phpspreadsheet`: characterized (`tests/Feature/Admin/MemberImportCharacterizationTest.php`),
  then upgraded 1.30.0 → 1.30.6. `UPSTREAM.md` UP-005. Committed `9045ce5`.
- `botman/botman`, `botman/driver-web`, and orphaned `custompackages/brozot/laravel-fcm` removed;
  confirmed the FCM push path was already unreachable before removal, so no characterization test
  was required. `UPSTREAM.md` UP-006. Committed `8c85781`.
- Upstream sync formally frozen at `d12c110` in `UPSTREAM.md`'s Baseline table; sync only after the
  WP 0A exit gate. Committed `e9596f5`.
- **Out-of-order, owner-requested:** WP 0A item 15 (`.graphifyignore`) pulled forward from Session
  5. `graph.json` 15MB → 7.7MB; `public/js/app.js` and other compiled/vendored assets no longer
  indexed; verified via a live query. See `CONTEXT.md` "Graphify" for detail and the one open,
  non-blocking `fail-closed` warning. Session 5's remaining scope (rebuild cadence, confirm no
  regressions once more code lands) is unaffected — this just closes the item early.
