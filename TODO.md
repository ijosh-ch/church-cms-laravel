# TODO

> Item 1 is always the literal next action. Rewritten every session end.
> Hard cap: 1,500 tokens — archive completed items to `MEMORY.md`, do not accumulate them here.
> Every item names its `build.md` item number and its `PRD.md` line range where relevant.

**Active work package:** WP 0A closure — Steps 2–7 of the approved 7-step plan
**Session:** 2026-08-15 Step 1 complete (4 commits) → Step 2 characterization is next and is the gate

---

## Before anything: start MySQL

There is **no registered Windows service**. Nothing listens on 3306 after a reboot, and every test
errors with `SQLSTATE[HY000] [2002]`, which looks like a code failure and is not. Run in background:

```
& "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --defaults-file="C:\ProgramData\MySQL\MySQL Server 8.4\my.ini" --console
```

Accepts connections ~4s later. Registering it permanently needs elevation — owner question below.

## Now

1. **Step 2 — continue characterization.** WP 0A item 6, gate 5. **37 tests, 92 assertions**, whole
   `tests/Feature` suite green.

   ⚠ **Any test that renders an admin view must seed `settings.*` config first.** Copy
   `MemberProfileCharacterizationTest::seedRuntimeSettings()`. Without it every admin page 500s on
   `htmlspecialchars(): ... UrlGenerator given`, which looks exactly like an application bug and is
   not — see `CONTEXT.md`. This already produced one withdrawn finding (MEM-001).

   ⚠ **Corrected scope:** `TESTING_PLAN.md` Part 1 lists **eleven** suites (its "seven" heading is
   stale) and targets **80–120 tests**, not the 60–90 quoted in earlier notes.

   **Done:** 2 Roles+permissions, 4 Attendance. **Partial:** 1 Auth (owes password reset, email
   verification, session lifetime, throttling), 3 Member profile (render coverage blocked on
   MEM-001 + UP-008). **Not started:** 5 QR/card, 6 Groups, 7 Event management, 8 Birthday,
   9 Exports, 10 Queues, 11 Private media.

   ⚠ **Check each planned suite has a SUBJECT before budgeting it.** Several `TESTING_PLAN.md`
   entries describe FR-11 behaviour that does not exist yet and therefore cannot be characterized —
   that mistake already cost one wrong estimate. Characterization covers what IS.

   ⚠ **Any `/admin/*` route sits behind BOTH legacy gates** (`RouteServiceProvider` applies
   `['web','auth','churchadmin']` to all of `routes/admin.php`). **Use usergroup 4.** Denial is
   **401**. Read `RolePermissionCharacterizationTest`'s docblock before writing an auth assertion.

   **Diagnostic before assertions — this is the standing method, not a suggestion.** Print status,
   `Location` and state for several inputs side by side before writing a single expectation. Suite 1
   was re-baselined once because assertions were written from what the code appeared to say; three
   inputs producing one identical output is what exposed it. Every file written diagnostic-first
   since has needed zero correction, and the method found SEC-002 — an absence of a control that no
   amount of reading the attendance code reveals.

   **Diagnostic before assertions.** Print status, `Location` and the relevant state for several
   inputs side by side before writing a single expectation. Suite 1 had to be re-baselined once
   because assertions were written from what the code appeared to say; three inputs producing one
   identical output is what exposed it.

## Done this session — suite 1 (kept briefly for the next session's context)

**Suite 1: COMPLETE.** WP 0A item 6, gate 5. **13 tests, 28 assertions, 8.7s.**

   ✅ **Done:** `tests/Feature/Auth/RolePermissionCharacterizationTest.php` — **7 passed**,
   13 assertions, 7.5s. Covers both legacy gates, direct grants, role-mediated grants, the denial
   path, and the absence of any single-role constraint. Records **SEC-001**.

   ⚠ **READ THE CLASS DOCBLOCK BEFORE ADDING ANY AUTH TEST.** The surface has **two** legacy gates
   in order: `churchadmin` → `MustBeChurchAdmin` (usergroup 3/4 pass, 1 → `/portal`, else 403) runs
   **first**, then `permission` → `AdminOrPermission`. **Use usergroup 4** — it clears gate 1 and is
   not gate 2's bypass value. **Denial is 401**, not 403. An authorized request returns **500**
   (controller defect, suite 3's problem). The first version of this file used usergroup 1 and
   measured the `/portal` redirect for three commits; the "role resolution may be broken" finding
   was a false alarm from that same mistake.

   ✅ **`tests/Feature/Auth/AuthenticationCharacterizationTest.php`** — 6 tests. Login, logout,
   invalid credentials, and two documented defects. **Suite 1 is COMPLETE** — 13 tests,
   28 assertions, 8.7s across both files.

   **New finding — AUTH-001:** `routes/web.php` calls `Auth::routes()` **twice** (L68 with
   `'register' => false`, L71 bare), so registration is **live** despite being explicitly disabled.
   `GET /register` returns 200. Check what `RegisterController` does on an unauthenticated POST
   **before any public deployment**. Do not fix by deleting L71 in a characterization pass — it is
   upstream-owned, needs an `UPSTREAM.md` entry, and other routes may exist only because of it.

   ⚠ **Scope correction, 2026-08-15.** An earlier note listed "role assignment and replacement" and
   "the final-admin guard" as missing from suite 1. **They are not characterizable** — invariant 9's
   `RoleAssignmentService` does not exist, and neither does the three-role model. Characterization
   covers what IS; those are FR-11 behaviours and belong to that work, with ordinary tests written
   alongside them. **Suite 1's real remaining scope is the incomplete test above plus
   authentication itself** (login, logout, session, password reset). That is smaller than stated.

   **Read the existing file's class docblock before adding to it** — it carries three measured
   behaviours that are counter-intuitive and were each hit as a failure first: denial for an
   authenticated user without the permission is a **302 redirect, not 403**; a guest gets **401**,
   not a login redirect (the group has `permission:*` but no `auth`); and `users.usergroup_id` is a
   foreign key to **`user_group`** (singular) where the fixture must insert an **exact** id.

   **Capture what IS, not what should be.** If a behaviour looks wrong, assert the wrong behaviour
   and name the test `test_documents_defect_*`. Fixing and characterizing in one pass destroys the
   baseline. Green on a defect test is never endorsement — cross-reference a written finding
   (decision 4, 2026-08-10: every `usergroup_id` Gate bypass gets one).

   **Assert the denial explicitly:** a leader with **no** assignment is **DENIED**. Hidden
   navigation is not authorization (`build.md` SECURITY 11).

2. **Step 2 — characterization suite 2: attendance** (open / scan / lock / unlock). The ~60% of
   FR-04 that Phase 1B extends. **Write this assertion before the `status` column exists:**
   a **MISSING** `EventAttendee` row is **NOT** absence — it is "not recorded". `EventAttendee` is
   presence-only today, and the WP 0C migration must not be able to quietly change what "no row"
   means. `TimezoneCharacterizationTest` already lives in this suite's directory and is green.

3. **Step 2 — the remaining five suites.** Member profile, group access (incl. the `usergroup_id`
   Gate bypasses), member QR / membership card, exports, birthday routes, queues, private media.
   See `TESTING_PLAN.md` Part 1. Record pass/fail/skip in `MEMORY.md` — **that record is the
   baseline**, not the code.

4. **Step 3 — scaffold `custompackages/ifgf/church-operations`.** WP 0A item 11, gate 3. Composer
   path repository, PSR-4, `extra.laravel.providers` auto-discovery, committed lockfile resolution,
   package test-runner path. **No product behaviour.** Generate into `app/`, then `git mv` and fix
   the namespace with one `sed` (`CLAUDE.md`). Record root `composer.json` and `composer.lock` as
   approved upstream-owned touchpoints.

5. **Step 4 — provider smoke tests from a clean checkout.** WP 0A item 14: package routes,
   migrations, views, translations, commands and policies all load.

6. **Step 5 — merge `contrib/laravel-supported-platform` into `ifgf/main`.** **Only after step 2
   passes.** The one hard-to-undo action in the plan. Re-run the full suite after merging.

7. **Step 6 — upstream merge rehearsal, read-only.** WP 0A item 13. No push, no mutation of
   protected branches. Recurring infrastructure, not a one-off — the owner wants upstream's later
   features. `laracasts/presenter`'s removal is the likeliest conflict (UP-007).

8. **Step 7 — WP 0A exit-gate review.** Report each `build.md` L226–228 criterion met / not met.
   **Do not mark the gate passed if any criterion fails.** Then STOP — WP 0C needs its own approval
   (`tools/PROMPTS.md` P1).

## Next — deferred within WP 0A

| Item | Action |
|---|---|
| Now item 2 (old) | **QR `format('png')` → `format('svg')`**, 8 upstream-owned Blade call sites + their `data:image/...` prefixes, needs **UP-008** (number reserved). Owner decision 2026-08-10 #1. Order: characterize the route's non-QR behaviour + the current `RuntimeException` → change → flip the assertion. `/` returns 500 until this lands (`imagick` absent, pre-existing). |
| C7 | Update `UPSTREAM.md` for every upstream-owned file touched: `app/Models/{User,Role,Permission,Userprofile,FeedbackMessage}.php`, `app/Http/Middleware/AdminOrPermission.php`, `app/Presenters/UserprofilePresenter.php`, `config/app.php`, `phpunit.xml`, `composer.json`. |
| 0A item 13 | Push branches only after review. Nothing is pushed; 30 ahead / 8 behind. |
| Route count | `route:list` = 730 vs the inventory's 812 static declarations; 12 commented, ~70 unexplained, **no pre-upgrade baseline to diff**. Check out `086f33d`, `route:list --json`, diff. Fix the inventory's "no scheduled tasks" claim while there (`schedule:list` shows two). |

## Blocked / deferred

- **Vite migration** — separately approved deferral. Build still works (exit 0); 166 npm
  vulnerabilities, Vue 2 EOL with an unfixable ReDoS advisory. Never run `npm audit fix`.
- **Upstream sync** — frozen at `d12c110` by design until the WP 0A exit gate passes.

## Decisions taken — do not re-ask

| # | Decision | Consequence |
|---|---|---|
| 2026-08-15 | **Timestamps: UTC at rest.** `PRD.md` wins; the `Asia/Taipei` proposal is rejected. | Landed `f192b11`. **Accepted cost:** `attendance_date` is a `date()` column, so a 00:00–08:00 Taipei service files under the previous UTC day. Any code deriving a calendar day from an instant must convert to the branch timezone first. Pinned by a `test_documents_*` test. See UP-009. |
| 2026-08-10 #1 | **QR: `format('svg')`.** No imagick anywhere. | Needs UP-008. |
| 2026-08-10 #2 | **Upstream: rehearse only, keep the `d12c110` pin.** | Step 6. |
| 2026-08-10 #3 | **Keep `app/Support/Presenter/`.** | Carried in UP-007. |
| 2026-08-10 #4 | **Characterize all group access, incl. `usergroup_id` Gate bypasses**, each named `test_documents_defect_*` + a written security finding. | 32 files. Feeds the FR-11 cutover. |

## Decisions awaiting the owner

- **Register MySQL 8.4 as a Windows service?** Needs one elevated command. Until then every session
  starts it by hand.
- `app/Imports/UsersImport.php::collection()` crashes on any non-empty import (undefined `$request`).
  Schedule before member-import work.
- `app/Traits/SendPushNotification.php` references never-autoloaded `LaravelFCM\*` classes.
- `app/Models/FeedbackMessage.php` points `$presenter` at a non-existent `App\Presenters\UserPresenter`.
- Whether `barryvdh/laravel-dompdf` (unused, zero call sites) should be removed.
- Which of `yarn.lock` / `package-lock.json` survives.
- The remaining `REVIEW.md` questions: legacy-source access, Composer audit, frontend CI gate.
