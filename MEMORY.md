# MEMORY

> Append-only. Newest entry at the top. One entry per session.
> Sessions read the **last 5 entries only** — never the whole file.
> Record what was learned and what failed, not what was written. `git log` already has the diff.

---

## 2026-08-21 — Session 8 — Suites 9 and 11 characterized; SEC-003 found, mis-rated, fixed; 37 → 62 tests

**Outcome:** the two wholly-uncharacterized PII surfaces are now covered. Characterization went
**37 → 62 tests**; the full `Feature` suite is **73 passed, 250 assertions, 0 failed, 0 skipped**
(2m41s), up from 48/109; package suite 3 passed, 10 assertions. Suites complete: **4 of 11**. The exit gate is **unchanged at 4 of 7** —
criterion 2 moved a long way but is not met, and 4 and 7 still need the owner. **Step 4 (merge to
`ifgf/main`) was correctly NOT performed** — it is gated on both.

**Recorded pass/fail/skip — THIS IS THE BASELINE**

| Run | Result |
|---|---|
| Baseline before any change | 48 passed, 109 assertions, 0 failed, 0 skipped, 3m40s |
| `ExportCharacterizationTest` alone | 11 passed, 49 assertions, first run, **zero correction** |
| `DateCastRegressionCharacterizationTest` alone | 5 passed, 50 assertions, first run, **zero correction** |
| `PrivateMediaCharacterizationTest` alone | 7 passed, 30 assertions — **1 failure on first run**, see below |
| Full `Feature` suite, mid-session | 71 passed, 238 assertions, 0 failed, 0 skipped, 2m41s |
| `PrivateMediaCharacterizationTest` after the SEC-003 fix | 9 passed, 42 assertions |
| **Full `Feature` suite, end of session** | **73 passed, 250 assertions, 0 failed, 0 skipped, 2m41s** |
| **Package suite** | **3 passed, 10 assertions** |

Characterization tests: **62** (73 minus the 11 package smoke tests). Target 80–120.

**Three new findings. SEC-003 was FIXED the same day by owner direction (UP-012); the other two
are characterized, NOT fixed and need an owner decision.**

- **SEC-003 — the avatar endpoints accepted ANY file type. FIXED, UP-012.** Both
  `Admin\UserProfileController::updatechangeavatar()` and the Preacher twin took a plain
  `Request`, called no `validate()` and used no FormRequest. Reachable by every church admin (the
  route is in `routes/admin.php` behind `['web','auth','churchadmin']` but inside **no** permission
  group) and, via SEC-001, by any `usergroup_id == 3` account holding nothing.
  **I RATED IT RCE AND THAT WAS WRONG — see the first lesson below.** The real vector was
  **stored XSS**. Fixed by type-hinting `EditUserProfileImgRequest`, which **already existed**
  with the right rule and was **already wired to the API twin** — it had simply never been
  attached to the two web endpoints. Two lines per file; nothing new written.
- **REG-001 — `protected $dates` is inert, and 21 date columns across 9 models silently return
  strings.** The property was removed in **Laravel 10**; this application declares it on **37 models**
  and was carried 10 → 13 by WP 0B. `Illuminate\Database\Eloquent\Model` no longer defines it, nothing
  reads it, and **no error is raised**. Measured on 13.24.0: 21 columns uncast, including
  `EventAttendanceSession::attendance_date`, `EventAttendee::scanned_at` and
  `Userprofile::date_of_birth`. `deleted_at` is rescued everywhere by `SoftDeletes` — **except on
  `SendMail`, which lists it in `$dates` but does not use the trait at all**, a separate finding.
  `Post` and `User` survive because they declare `$casts` as well; `Post` is the worked example of
  the fix.
- **SEC-002 extends to the export surface.** `EventAttendanceController::export()` guards only on the
  permission middleware and `abort_unless($session->church_id === Auth::user()->church_id, 403)`. It
  never consults `event_managers`; an assigned manager and an unassigned leader reach **identical**
  outcomes. Church isolation does work (403) and is asserted on the other side.

**Learned — carry forward**

- 🔴 **THE BIG ONE: `UploadedFile::fake()` DERIVES ITS MIME TYPE FROM THE FILENAME, so it
  cannot settle any question about file-type handling — and it inflated SEC-003 into a false RCE
  rating.** I reported a deployment-dependent remote code execution because a fake upload stored
  `shell.php` as `.php`. Re-measured with a real `UploadedFile` built from real bytes:

  | Upload | Detected MIME | `guessExtension()` | Stored as |
  |---|---|---|---|
  | `UploadedFile::fake()` named `shell.php` | `application/x-php` | `php` | `….php` ← **the harness** |
  | real PHP source, named `shell.php` | `text/x-php` | *(empty)* | **no extension** |
  | real PHP source, named `shell.jpg` | `text/x-php` | *(empty)* | **no extension** |
  | real SVG with `<script>` | `image/svg+xml` | `svg` | `….svg` ← **the real vector** |
  | real HTML with `<script>` | `text/html` | `html` | `….html` ← **the real vector** |

  `Storage::putFile()` names via `hashName()`, which appends `guessExtension()` — derived from
  the **detected MIME**, never the client filename. PHP source lands extensionless and could never
  match a `\.php$` handler. **The fourth time this project has asserted against the harness instead
  of the application, and the first time it inflated a severity rating rather than merely costing a
  commit.** Rule: **a fake file is for convenience, never for a security conclusion. When the answer
  depends on file CONTENT, construct a real `UploadedFile` from real bytes.**
- **Laravel's `image` validation rule ALLOWS SVG** (`ValidatesAttributes` excludes it only without
  `allow_svg`). SVG is the classic stored-XSS carrier, so `image` is the wrong rule for an avatar.
  **Use an explicit `mimes:` allow-list, and do not let anyone “simplify” it back.**
- **Look for the control before building one.** SEC-003's fix was two lines because
  `EditUserProfileImgRequest` already existed with the correct rule and was already wired to the
  API twin. The defect was not a missing control — it was a control **not connected** to two of its
  three call sites. Grep for an existing FormRequest before writing validation.

- **The single most valuable thing found today came from a fixture that looked broken.** The
  attendance export 500'd with `Call to a member function format() on string`. The tempting reading
  was "my raw DB insert produced a string" — a fixture problem. Reading the actual failing line
  instead produced REG-001, a systemic upgrade regression across 9 models. **This is the 2026-08-15
  MEM-001 lesson running in the opposite direction:** that time a 500 looked like an application bug
  and was a fixture problem; this time it looked like a fixture problem and was an application bug.
  The rule that covers both: **read the failing line before deciding which it is.**
- **Diagnostic-first held, and the one deviation is the proof.** Three files were written after a
  side-by-side diagnostic and needed **zero** correction across 23 tests. The single first-run failure
  in the whole session was the one assertion I did **not** measure first — I guessed
  `read-gallery` for `/admin/getphoto/{event_id}` from the route's name. It is `read-events`. Four
  sessions, four confirmations: **the guessed assertion is the one that fails.**
- **A diagnostic that disproves your hypothesis is worth as much as one that confirms it.** I expected
  `$_SERVER['HTTP_USER_AGENT']` to make these endpoints 500 under a UA-less request. Measured with and
  without, side by side: **no difference, 200 both ways.** Had I written that test from the hypothesis
  it would have characterized nothing and failed on the first machine that set a default UA — the
  exact class of error that cost CI run 2 on 2026-08-17.
- **`League\Csv\Writer::output()` echoes to the SAPI and returns null, so the CSV never travels in the
  Laravel response.** `$response->getContent()` is `''` and there is no `Content-Disposition` on the
  response. Every export assertion has to capture PHP's output buffer instead. A test asserting on
  `getContent()` here would pass forever and prove nothing — and any middleware appending to the
  response would corrupt the download, because the CSV is already on the wire.
- **Two routes can claim one URI and the loser fails silently.** `routes/admin.php:176` and `:574`
  both register `GET /admin/export`. Laravel keeps the last, so the subscriber export landing page is
  simply unreachable — no error, no warning. `route:list` shows one route, which is also why a
  shadowed duplicate cannot explain the 731-vs-812 gap.
- **`$log`/`$message` assigned only inside success branches is a live 500.** `ExportMemberController::
  exportUsers()` reaches `doActivityLog(..., string $logname, string $message)` with both unset on
  every non-success path → `TypeError`. The order is the interesting part: **`$csv->output()` has
  already echoed the file**, so the member receives a valid CSV *and* an HTTP 500, and no activity-log
  row is written for an export that did happen. An empty church hits this on the first click.
  `exportGuests()` differs in one detail — it passes the `LOGNAME_EXPORT_GUEST` constant directly —
  so it does not 500; it corrupts quietly instead, calling `output()` **twice** and writing the body
  two times. Same bug shape, opposite symptom.
- **"Private media" was the wrong name for suite 11 and finding that out WAS the characterization.**
  There is no private media: all four disks are public, `uploads` is rooted at `public_path()`
  itself, and no `temporaryUrl()`, signed route or `hasValidSignature()` call exists anywhere in
  `app/`. Member-photo privacy rests entirely on the 40-character `hashName()` filename — obscurity,
  not authorization. There is also **no place to put an authorization check today**: the endpoints
  named `getPhoto` return Eloquent rows carrying paths, not bytes, so the image fetch never enters
  PHP. FR-11 must **create** that checkpoint, not tighten one.
- **`RouteServiceProvider.php` is byte-identical to both `d12c110` and `800c29f`.** The exit gate's
  "classify it, add an entry" premise does not hold — `UPSTREAM.md` entries record fork *changes*, and
  there is no change. Proposed `monitor` instead: unmodified, authorization-critical, re-read after
  every upstream sync. **Measure before classifying; the ledger's own semantics decide the answer.**
- **`phpunit.xml` is not unrecorded — it is recorded inaccurately.** UP-005 lists it as "additive
  test-isolation env only", which stopped being true at `f60f1a4`. Against the pin it is 29
  insertions / **31 deletions**. A stale accurate-sounding row is worse than a missing one: the
  conflict-risk figure a future reader relies on is wrong.

**Not done — read before assuming progress**

- **5 of 11 suites still not started:** 5 QR/card, 6 Groups, 7 Event management, 8 Birthday,
  10 Queues. **3 partial:** 1 Auth (password reset, email verification, session lifetime,
  throttling), 3 Member profile (create/edit/delete/export behaviour), 4 Attendance (`searchMember`,
  `removeAttendee`). At 60 of 80–120, criterion 2 is **not met**.
- **SEC-003's blast radius is NOT closed.** The two avatar endpoints are fixed;
  `Common::uploadFile()` has **47 call sites** and the rest are unvalidated and unmeasured. Several
  take a plain `Request` the same way. That needs its own entry, and it should be sized against
  suite 11's finding that **no private disk exists at all** — an allow-list across 47 call sites is a
  worse answer than moving member media off a public disk.
- **Criteria 4 and 7 remain owner-gated.** `WP0A_OWNER_REVIEW.md` was written this session to make
  that one sitting possible; it asks three explicit decisions (D1 UP-007 restatements, D2
  `RouteServiceProvider` classification, D3 `phpunit.xml` entry) and **deliberately marks neither
  criterion met**.
- **Step 4, the merge to `ifgf/main`, was NOT performed** and must not be until criterion 2 is green
  and the review is signed off. It remains the single hardest action here to undo.
- **Suite 8 (birthday) now has a known dependency on REG-001** — it derives from
  `Userprofile::date_of_birth`, which is one of the 21 uncast columns. Expect date handling there to
  be stringly-typed and possibly wrong; characterize what it does, do not fix it.

## 2026-08-18 — Session 7 — UP-011 applied; CI fully green; exit gate re-scored 4 of 7

**Outcome:** the frontend production build passes on Linux **for the first time in this project's
history** (CI run `32090487802`, both jobs, every step). Exit-gate criteria 1, 5 and 6 close;
**4 of 7 met, 0 partial, 3 not met**, up from 1/2/4. Characterization is now the only long pole.

**Learned — carry forward**

- **`core.ignorecase = true` makes a case-only `git mv` a SILENT NO-OP.** It reports success and
  changes nothing. UP-011 needed two steps via an intermediate name, and verification with
  `git ls-files` — **a directory listing lies about case on this filesystem.** The commit shows three
  `R` renames at 100% similarity, which is the proof the rename was recorded rather than faked.
- **Rename the directory, not the imports.** Both fix the build; only one removes the inconsistency.
  The convention was 34 lowercase component dirs to 1 capitalised, and all three call sites already
  expected lowercase. **When two fixes work, prefer the one that also deletes the anomaly.**
- **A build result is only evidence for the platform it ran on.** "`npm run production` still works
  (exit 0, 290s)" had been carried since the C5 audit as evidence the frontend was safe to defer. It
  was true only on Windows; the build had been broken on Linux — the production target — the whole
  time. The note was accurate and the inference from it was wrong.
- **It took FOUR CI runs, and three failed for real reasons.** Run 1: 22 `MissingAppKeyException`
  (CI wrote a DB-only `.env.testing`; it *replaces* `.env`). Run 2: a characterization test pinning
  the dev machine's extension list. Run 3: UP-011. Run 4: green. **A CI file that has never executed
  is a hypothesis — this one was wrong in three separate ways**, and every one was invisible locally.
- **`MEMORY.md` had already recorded run 1's trap on 2026-08-10** and the workflow still shipped it.
  Knowing a thing and executing it are different; only the second finds this.
- **Reconcile a re-scored document end to end.** Updating the exit gate's summary table left sections
  1, 5 and 6 still reading PARTIAL/NOT MET — internally contradictory, and worse than not updating it
  at all. Re-score the *whole* artifact or none of it.
- **`TODO.md` had drifted well past its own 1,500-token cap** with a stale "done this session" block
  and a duplicated paragraph. Rewritten to 828 words. It is the next session's entry point; bloat
  there has a direct cost.

**Compatibility matrix assembled — criterion 7's artifact now exists**

`UPGRADE_COMPATIBILITY_MATRIX.md`, 1,777 words. Compilation from `composer.lock`, UP-001…UP-011,
`MEMORY.md` 2026-08-10 and `DEPENDENCY_INVENTORY.md`.

- **Reading `composer.lock` instead of reconstructing from memory corrected several values I would
  have stated wrongly** — the intermediate majors were **11.55.0** and **12.65.0**, not round
  numbers; `collision` is **v8.9.5**; npm is **11.4.2**, not what I assumed. **Compile a matrix from
  the lockfile, never from recollection**, even when the recollection is your own from days earlier.
- **`composer.json` `require.php` (`^8.3`) and `config.platform.php` (`8.4.24`) are deliberately
  different values** — the supported *range* versus what the solver resolves against.
  `composer.lock` carries it as `platform-overrides`. **Do not "align" them.**
- **The matrix ends with a section on what it does NOT establish**, because a matrix that reads as
  complete when it is not is worse than none: it is not behavioural proof (criterion 2 is still
  unmet), the lifecycle dates are unverified since 2026-08-10, no anonymized production snapshot has
  been tested, and the 730-vs-812 route count is still unreconciled.
- **Assembling is not reviewing.** Criterion 7 is marked **partial**, not met — same treatment as
  criterion 4. The artifact existing is the deliverable; the owner reading it is the criterion.
  Criteria 4 and 7 now close in one sitting.

**Not done — read before assuming progress**

- **7 of 11 characterization suites not started, 3 partial.** 37 characterization tests against
  **80–120**. The single blocking criterion, 3–4 sessions. **Exports and private media are wholly
  uncharacterized and both touch member PII.**
- **Criterion 4** — owner review of `UPSTREAM.md`; classify `RouteServiceProvider`; fold in
  `phpunit.xml`; restate UP-007's *predicted* High conflict risk as the *measured* clean result.
- **Step 5** (merge into `ifgf/main`) correctly still blocked. **WP 0C must not begin.**

## 2026-08-15 — Session 6 — WP 0A Step 1: baseline committed, timestamp contract settled on UTC

**Outcome:** the four-month backlog of uncommitted work is committed in four attributable commits
(`aa3d279`, `7ab95a9`, `4d8a13d`, `f192b11`), and the single blocking owner decision is resolved.
**Step 2 (characterization suites) was not started** — deliberately, at the budget line. WP 0A items
6, 11, 13 and 14 all remain open; nothing about the exit gate changed this session.

**The decision — UTC at rest, `PRD.md` wins.** `REVIEW.md` question 1 is answered. The 2026-08-10
`Asia/Taipei`-at-rest proposal is **rejected**. All five normative statements (four `PRD.md` lines
plus `build.md` TECHNICAL BASELINE 3) now agree and no document carries a deviation pointer.

**Learned — carry forward**

- **A confirmed decision can contradict a later instruction. Check before executing.** The session's
  step plan said "commit the timezone fix"; the answer given minutes earlier said "UTC — revert the
  staged change". The staged files *were* the rejected contract. Committing as instructed would have
  written the rejected contract into history and, worse, into the characterization baseline that
  Step 2 exists to establish. Surfacing the conflict cost one round-trip; not surfacing it would
  have cost a revert plus a corrupted baseline.
- **The connection pin is required under either contract — and UTC makes it EASIER to lose.** Most
  Linux and CI hosts default to UTC, so a missing `'timezone'` in `config/database.php` now passes
  by luck almost everywhere and fails only on this dev machine, where `@@global.time_zone` is
  `SYSTEM` = Taipei. Do not delete the live `@@session.time_zone` assertion on the grounds that
  "the host is UTC anyway". That is exactly backwards.
- **UTC at rest has a real, accepted cost that is now pinned by a test.**
  `event_attendance_sessions.attendance_date` is a `date()` column that never converts, so a service
  between **00:00 and 08:00 Taipei files under the previous UTC calendar day** — an 06:00 prayer
  meeting lands on the day before. Services from 08:00 onward are unaffected, which covers every
  regular Sunday service. The rule this creates: **any code deriving a calendar day from an instant
  must convert to the branch timezone first**; the 8 `date()` columns are where it gets broken.
  `test_documents_early_taipei_services_resolve_to_the_previous_utc_day` asserts the shift so WP 0C
  cannot change what a stored `attendance_date` means without turning it red.
- **Two `TODO.md` facts were stale — verify before acting on a note.** `mysqldump` is already on the
  Machine PATH (`C:\Program Files\MySQL\MySQL Server 8.4\bin`), no elevated edit needed, and
  `database/schema/mysql-schema.sql` had **already been generated** on 2026-08-11 and only needed
  committing. Item 3 was budgeted as work and was actually done. Also: the two `mysql-schema.sql`
  files never compete — Laravel reads only `database/schema/<connection>-schema.sql`, so the root
  one is an unrelated legacy artifact. That question is closed.
- **MySQL 8.4 has NO registered Windows service on this machine.** `Get-Service` returns only
  `postgresql-x64-17`; there is no `mysqld` service and nothing listens on 3306 after a reboot. The
  install is intact (`mysqld.exe`, `C:\ProgramData\MySQL\MySQL Server 8.4\my.ini`, `D:\MySQL\data`
  holding `churchcms_test_disposable`). Start it manually:
  `& "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --defaults-file="C:\ProgramData\MySQL\MySQL Server 8.4\my.ini" --console`
  in the background; it accepts connections ~4s later. **Every test session must do this first** or
  the whole suite errors with `SQLSTATE[HY000] [2002]` and looks like a code failure. Registering
  the service permanently needs elevation and is an open owner question.
- **The suite is fast again.** `TimezoneCharacterizationTest` runs **6 tests / 11 assertions in
  1.96s** using `DatabaseTransactions`. The committed schema dump is what makes the `RefreshDatabase`
  suites in Step 2 viable; the ~8-minute figure was measured before it existed.

**Recorded pass/fail/skip — THIS IS THE BASELINE so far**

| Suite | Result |
|---|---|
| `TimezoneCharacterizationTest` | **6 passed, 0 failed, 0 skipped**, 11 assertions, 1.96s |
| `MemberImportCharacterizationTest` | **not rerun this session** — unchanged since Session 2b |
| The other 7 planned suites | **do not exist yet** |

`php artisan about --only=environment` reports `Timezone ... UTC` on Laravel 13.24.0 / PHP 8.4.24.

**Step 2 started — suite 1, first file (added after the reconciliation commit)**

`tests/Feature/Auth/RolePermissionCharacterizationTest.php` — **4 passed, 0 failed, 0 skipped**,
6 assertions, 6.6s. Coverage is now **3 test files, 10 tests**.

- **SEC-001 — the `usergroup_id` bypass is ONE LINE, not 33 call sites.** `app/Http/Kernel.php:74`
  aliases `'permission'` to `App\Http\Middleware\AdminOrPermission` instead of to Laratrust's own
  middleware, and that class bypasses the entire granular permission system for
  `usergroup_id == 3` (`AdminOrPermission.php:16`). So **every** `permission:*` route in the
  application is affected by a single alias. Easier to fix than the 33-file estimate suggested and
  far easier to miss. It cannot be removed before FR-11 maps legacy groups onto the three approved
  roles — removing it today locks out every existing church admin.
- **Denial is by 302 redirect, not 403** — for an authenticated user without the permission. A
  redirect is still a denial (the request never reaches the controller), so `build.md` SECURITY 11
  is satisfied. **An assertion of 403 would have been asserting what SHOULD be and would have failed
  against a system that refuses correctly.** This is the exact trap a characterization pass exists
  to avoid, and it was hit on the first run.
- **A guest gets 401, not a login redirect**, because `routes/web.php:182` guards the group with
  `permission:*` but **not** with `auth`. The two denial paths differ. Both recorded, neither
  normalized — adding `auth` changes an upstream-owned route group and belongs to FR-11 with its
  own `UPSTREAM.md` entry.
- **Fixture lesson:** `users.usergroup_id` is a foreign key to **`user_group`** (singular, not
  `usergroup`), and the bypass compares the literal value `3`, so the fixture must insert that
  **exact id** — `insertGetId` hands back whatever autoincrement is free and silently destroys the
  point of the test.
- **Assert "not 403" rather than 200 for allowed cases.** Asserting 200 couples an authorization
  test to view rendering and seed data, so an unrelated view change fails an auth test and teaches
  the next session to weaken it. Reasoning is in the class docblock so it is not "tightened" back.

**Suite 1 — the first baseline was WRONG, and the correction is the lesson**

Final state: **7 passed, 13 assertions, 7.5s. No incomplete.**

- **THE AUTHORIZATION SURFACE HAS TWO LEGACY GATES, IN ORDER — and I measured the wrong one for
  three commits.** `churchadmin` → `MustBeChurchAdmin` (`Kernel.php:71`) runs **first**: usergroup
  **3 or 4 pass**, usergroup **1 redirects to `/portal`**, anything else **aborts 403**. Only then
  does `permission` → `AdminOrPermission` run. My fixtures used usergroup 1, so **every**
  "permission" assertion was observing the `/portal` redirect and never reached the permission
  middleware at all.
- **Consequence: two committed assertions were vacuous and one finding was false.** "Denial is a 302
  redirect" was not a denial — it was gate 1. The `assertNotSame(403)` control passed on that same
  302 regardless of whether the grant worked. And the `markTestIncomplete` claiming role-mediated
  resolution might be broken was **wrong** — Laratrust resolves it correctly; the request simply
  never got that far.
- **What actually caught it: a throwaway diagnostic that printed status + Location + `hasPermission`
  for three users side by side.** All three — direct grant, role grant, no grant — returned the
  identical `302 → /portal`. Three different inputs producing one output is the signal that the
  thing under test is not the thing deciding. **Reach for that before theorising.** I had spent two
  round trips on hypotheses (cache pollution, fixture shape) that a single dump would have killed.
- **Correct facts, now pinned:** denial is **401** (`config/laratrust.php` `handling => abort`,
  `abort.code => 401`), **not** 403. Use **usergroup 4** to test permissions — clears gate 1, is not
  gate 2's bypass value. An authorized request returns **500**: it clears both gates and
  `UserController@index` then fails on its own, a separate pre-existing defect owned by suite 3.
- **`test_documents_churchadmin_gate_redirects_usergroup_one_before_permissions` now pins gate 1
  explicitly**, and deliberately grants the permission first, so the redirect proves gate 1
  *outranks* the permission check rather than merely agreeing with it. If gate 1 changes, that test
  fails loudly instead of silently changing what every other test measures.
- **A green characterization test proves nothing until you know which gate answered.** Every
  assertion in an authorization suite needs a control that fails for the opposite input. The
  `usergroup_id == 3` bypass test now runs a group-4 control in the same test and asserts it gets
  401 — without that contrast, "group 3 got through" is equally consistent with "everyone gets
  through".
- **Scope correction — part of what I listed as "missing from suite 1" is not characterizable.**
  "Role assignment and replacement" and "the final-admin guard" come from invariants 5 and 9, and
  `RoleAssignmentService` **does not exist**; neither does the three-role model. Characterization
  covers what IS. Those are FR-11 behaviours needing ordinary tests written with them. Suite 1's
  real remainder is the open question above plus authentication. **Check whether a planned test has
  a subject before budgeting it** — several entries in `TESTING_PLAN.md` Part 1 may be the same
  mistake.
- `test_documents_no_constraint_prevents_a_user_holding_multiple_roles` passes: **nothing today
  stops a user holding several roles.** Not a defect — the absence of a rule the system never
  claimed. Recorded because FR-11 must migrate whatever multi-role data already exists.
- **`markTestIncomplete` is the right tool for an unresolved characterization question.** It is
  visible in every run, carries the reasoning at the point of failure, and does not turn the suite
  red or silently pass.

**Suite 1 COMPLETE — authentication added**

`AuthenticationCharacterizationTest` — 6 tests. Suite 1 total: **13 tests, 28 assertions, 8.7s.**

- **AUTH-001 — registration is LIVE although explicitly disabled.** `routes/web.php` calls
  `Auth::routes()` **twice**: L68 with `['verify' => true, 'register' => false]`, then L71 **bare**,
  which re-registers the full default set and reopens what L68 closed. `GET /register` returns
  **200**. This is the public account-creation surface of a church member database. Whether it is
  exploitable depends on what `RegisterController` does with `usergroup_id`/`church_id` on an
  unauthenticated POST — **not characterized, check before any public deployment.** The duplicate
  call is also a plausible contributor to the unexplained **730 vs 812** route-count gap; check it
  when that item is picked up.
- **Email verification is configured but not load bearing.** `['verify' => true]` registers the
  routes and `users.email_verified_at` exists, but an account with a NULL value logs in normally.
  Matters to FR-02's activation flow, which cannot assume verification means anything today.
- **Post-login landing is `/member/home`, not `/home` — and `/home` does not exist** (a guest
  requesting it gets 404, not a redirect). Conventional Laravel assumptions do not hold here.
- **Invalid login redirects to `/`, valid login redirects to `/member/home`.** Both are 302, so
  **status alone cannot distinguish success from failure** — `Auth::check()` is the only thing that
  separates them, and it is asserted first in both tests. Same class of mistake as the gate mix-up:
  a redirect is not self-describing.
- **The diagnostic-first order worked.** Every value in this file was measured before any assertion
  was written, and nothing needed re-baselining. That is the direct counterexample to the
  RolePermission file, which was written the other way round and had to be corrected. **Make it the
  standing method for suites 2–7.**

**Suite 2 started — the semantics, which are the part that expires**

`AttendanceSemanticsCharacterizationTest` — 4 tests, green. Deliberately schema/semantics level
rather than HTTP: **the meaning of the data outlives any controller, and a migration can corrupt
meaning without touching a route.** The HTTP flow is still owed and is ordinary work; this part had
a deadline.

- **`event_attendees` is presence-only and the absence of a row is NOT absence.** Columns are
  `session_id, church_id, event_id, user_id, scanned_at, scanned_by` — no `status`, no
  `participation_mode`, no `capture_method`. A member whose scan failed, a session nobody opened,
  and a member who genuinely stayed home are **represented identically: by nothing**. So an FR-04
  backfill writing `'absent'` for everyone without a row would **invent pastoral data that was
  never observed** — a wrong answer to "who has stopped coming?", which is precisely what FR-10's
  inactive-risk report asks. Once the migration runs, "what did a missing row mean before?" is
  unanswerable from the database. That is why this had to be written now.
- **Both duplicate guards are DATABASE constraints, not controller logic.** `event_attendees`
  UNIQUE `(session_id, user_id)`; `event_attendance_sessions` UNIQUE `(event_id, attendance_date)`.
  FR-04's `AttendanceRecorder` must not drop the first while "moving the check into the service
  layer" — the constraint is what actually holds under concurrency, not the 409.
- **The one-session-per-day key collides with the UTC decision made earlier this same session.**
  `attendance_date` is a `date()` column that never converts. Under UTC at rest, a 00:00–08:00
  Taipei service falls on the previous UTC day — so an early prayer meeting and the main Sunday
  service can get **different** `attendance_date` values on the same Taipei day, while a
  Saturday-evening and a Sunday-early service can **collide** on the same one. Two decisions taken
  hours apart that have to be resolved together in FR-03. **Worth looking for more of these**: the
  UTC choice touches all 8 `date()` columns.

**Suite 2 COMPLETE — and the HTTP flow found the finding the semantics could not**

`AttendanceFlowCharacterizationTest` — 6 tests. **Full `tests/Feature` suite: 30 passed,
77 assertions**, including `MemberImportCharacterizationTest`, which had not been rerun since
Session 2b and does still pass.

- **SEC-002 — attendance has NO per-leader scope, and it is an ABSENCE of a control.** An
  `event_managers` table exists and `EventAttendanceController` manages it through
  `manageManagers`/`storeManager`/`removeManager` — but `openSession`, `markAttendee`, `lock` and
  `unlock` **never consult it**. Their only guards are the permission middleware and
  `abort_unless($session->church_id === Auth::user()->church_id, 403)`. Any holder of
  `create-attendance` can record attendance for **any** event in their church. **Assigning event
  managers is bookkeeping, not authorization.** PRD invariant 7 requires assigned-scope limits; that
  control does not exist, so FR-11 must **build** it, not adjust it.
- **A missing control is invisible to code reading — only a probe finds it.** No amount of reading
  the attendance actions reveals a check that is simply not there; the grep for `manager` even
  returned hits, from the management endpoints, which is actively misleading. What found it was
  running an unassigned leader through the endpoint and getting **200**. **When the requirement is a
  denial, test the denial — never infer it from the presence of a related table or route.**
- **Write a required denial that does not hold as `test_documents_defect_*` asserting the CURRENT
  behaviour.** An aspirational `assertSame(403)` would have produced a red suite that the next
  session "fixes" by deleting, leaving the defect no trace. Asserted as the defect it is, it cannot
  be missed or lost, and the replacement instruction is in the docblock.
- **Assert the one control that DOES work, separately.** Church isolation returns 403 cross-church
  and has its own test — otherwise a future refactor could remove the last remaining scope check
  while the suite stayed green, and SEC-002 makes that failure mode plausible rather than theoretical.
- **`unlock` records no reason and there is nowhere to put one** — no `unlock_reason` /
  `reopen_reason` / `unlocked_by` column, and the endpoint takes no input. FR-04 requires a mandatory
  reopen reason. Reopening attendance is precisely what a pastoral audit trail exists for, and it
  leaves none. Only visible by exercising the endpoint, which is why it belongs in the flow file.
- Measured, all green: duplicate → **409** `already_checked_in` (and still exactly one row, so the
  409 and the UNIQUE constraint agree); no permission → **401**; other church → **403**; locked
  session → **403** JSON; unknown member → **404**; lock/unlock → **302** setting and clearing
  `locked_at`.

**Suite 3 started — and it found the first real upgrade regression candidate**

`MemberProfileCharacterizationTest` — 6 tests. **Full suite: 36 passed, 97 assertions.**

- **~~MEM-001~~ — RAISED AND WITHDRAWN IN THE SAME SESSION. It was wrong.** I recorded "the member
  admin UI is broken on Laravel 13" as a suspected upgrade regression, committed it, then settled
  it and found **no defect at all**. With `settings.*` config seeded, `/admin/members`,
  `/admin/member/add` and `/admin/member/edit/{name}` all return **200**.
- **The real cause is a FIXTURE requirement that will bite every remaining admin-view suite.**
  `AppServiceProvider::boot()` populates `settings.*` from `church_details` for `Church::first()`.
  A disposable test DB has no such rows, so `config('settings.favicon')` is NULL, and
  `layouts/admin/layout.blade.php` line 6 does `{{ url(\Config::get('settings.favicon')) }}`.
  **`url(null)` returns the UrlGenerator instance** — documented Laravel behaviour, not a version
  change — and Blade's `e()` rejects it. Set the config directly; the provider runs at **boot**,
  before fixture rows exist, so inserting `church_details` mid-test does nothing.
- **A 500 IN A TEST ENVIRONMENT IS A FIXTURE QUESTION UNTIL PROVEN OTHERWISE.** This is the third
  time this session that an assertion was measuring the harness rather than the application — after
  the usergroup-1 gate mix-up and the role-resolution false alarm, both also caused by not asking
  "what is actually answering this request?". **The cheap check that settles it every time: read the
  actual failing line.** One look at the compiled view gave the answer that a `086f33d` checkout
  plus two `composer install` runs would have cost an hour to reach — and the plan I had written
  called for the expensive path.
- **What made it recoverable was writing the finding as a falsifiable claim.** MEM-001 was recorded
  with an explicit "NOT PROVEN, settle it by X" rather than as fact, so withdrawing it was routine
  instead of embarrassing. **Record suspected regressions with their disproof procedure attached.**
- `GET /admin/member/show/{name}` **does** still 500, on missing **imagick** — real, survives the
  fixture, already owned by UP-008. Two failures behind one status code, and only one was genuine.
- **WP 0C item 3 confirmed WITH DATA:** `userprofiles.user_id` has a foreign key but the index is
  `Non_unique = 1`, and a second profile row for the same user **inserts cleanly**. Invariant 4
  requires exactly one. Every `$user->userprofile` accessor silently picks **one** row, so a
  duplicated member can show different names or birthdays by row order. **The dedupe must precede
  the unique key, and "which row wins" is a pastoral data decision for the owner, not a technical
  one.**
- Suite 3 now has **real render coverage** — list, add and edit all assert 200 — because the fixture
  was the problem, not the application. The earlier version of this file asserted the 500s as
  defects; that version was replaced, not extended.
- **Corrected a number I had been repeating:** `TESTING_PLAN.md` Part 1 lists **eleven** suites (the
  "seven" in its own heading is stale) and targets **80–120** tests, not 60–90. Suite 1 also still
  owes password reset, email verification, session lifetime and throttling; suite 4 owes
  `searchMember` and `removeAttendee`. **Read the table, not the heading.**

**Steps 3 and 4 done — the package seam exists and is proven**

`custompackages/ifgf/church-operations` scaffolded and loading (WP 0A items 11 and 14, gate 3,
recorded as **UP-010**). **Application suite: 48 passed, 109 assertions.** Package's own suite:
**3 passed, 10 assertions.** Nothing IFGF-specific was legally placeable before this.

- **Auto-discovery means the root `app.php` is never touched.** The package declares
  `extra.laravel.providers` and Laravel finds it, so the change set is two lines in the root
  `composer.json` plus the lockfile — one fewer upstream-owned file than registering the provider by
  hand. Verified in `bootstrap/cache/packages.php`, not assumed.
- **Two Composer gotchas that cost a round trip each, neither obvious from the error text.**
  (1) A **path** package with no `version` resolves to `dev-<current-branch>`, which fails the root's
  `minimum-stability: stable` — declare `"version"` in the package manifest. (2) `"*"` fails
  `composer validate --strict` as an unbound constraint. Worth knowing before the next path package.
- **`sed -i` silently did not match the generated namespaces.** `CLAUDE.md`'s generate → `git mv` →
  `sed` recipe half-worked: the Artisan generators and the move were fine, the namespace rewrite was
  not, and it **failed silently** — `grep` afterwards is what caught it. The files needed real
  content anyway so I wrote them, but **verify a `sed` rewrite instead of trusting its exit code.**
- **The seam's failure mode is SILENT, which is the whole reason item 14 exists.** A merge that drops
  the `repositories` or `require` entry does not error — the package stops loading and IFGF
  behaviour disappears while the application keeps serving normally. That is why the smoke test
  asserts **each registration kind separately**: a single "the package loads" test would pass on a
  provider that registered nothing.
- **A placeholder policy must DENY.** The marker policy returns false and there is a test asserting
  it. Scaffolding that accidentally grants is a security hole wearing the costume of placeholder
  code.
- **WP 0A item 11 says "do not implement product behavior", so I guarded it with a test** — in
  *both* suites, asserting the package's `database/migrations/` is empty. Instructions that are easy
  to violate later deserve an assertion, not a comment.
- ⚠ **CI runs neither new suite.** Neither the package phpunit config nor the smoke tests are in
  `.github/workflows/ci.yml`. **A seam whose alarm never runs in CI is not actually guarded** —
  in `TODO.md`, small, and should land before Step 5.

**Step 6 done — the merge rehearsal ran for the first time, and it earned its keep immediately**

Against `upstream/main` = `800c29f`, 40 ahead / 9 behind. **THE MERGE IS CLEAN — no conflicts.**

- **UP-007's standing prediction was wrong.** It named `laracasts/presenter`'s in-house replacement
  as the likeliest conflict. It produced none. Upstream touches only 8 files and **no**
  `composer.json`, migrations or config, so there is no dependency churn at all. **A predicted
  conflict is not a measured one** — this had been carried as a risk since Session 2b on no evidence.
- **The rehearsal paid for itself on its first run, via a FAILING test.** On the merged tree the
  characterization suite is 47/48, and the failure is
  `test_documents_defect_member_show_fails_on_missing_imagick` — red **because upstream FIXED the
  defect**. Upstream rewrites `resources/views/member/idcard/idcard.blade.php` (179 lines) and
  **deletes the `format('png')` QR call**. So `GET /admin/member/show/{name}` returns 200 instead of
  500 once merged.
- **Consequence: UP-008 is 7 call sites, not 8, and `idcard.blade.php` must NOT be hand-edited.**
  Doing UP-008 as planned would have hand-edited a file upstream rewrites, manufacturing a conflict
  to fix a problem upstream already removed. **This is the concrete argument for running the
  rehearsal BEFORE planned upstream-owned work, not after.**
- **A documenting test going red is the mechanism working, not breaking.** `test_documents_defect_*`
  named tests are designed to fail when the defect is fixed. Because the docblock says what to do
  when that happens, the red is self-explaining rather than alarming. **The naming convention paid
  off here for the first time.**
- **`git merge-tree --write-tree` is the right first move** — it computes the merge in memory and
  reports conflicts without touching the index or working tree, so the conflict question is answered
  before anything is mutated. The throwaway-branch merge is only needed to *run the tests*.
- **Automated as the `merge-rehearsal` CI job**, read-only by construction: `contents: read`,
  upstream added fetch-only with its push URL disabled, merge on a throwaway branch in an ephemeral
  runner, nothing ever pushed. It runs **alongside** `test` rather than gating it, so a red
  rehearsal never blocks unrelated work — and it is **supposed to be able to fail**.
- Also closed two smaller CI gaps: the package suite now runs in CI, and the generated `.env.testing`
  now sets `TIMEZONE=UTC` **explicitly**. It resolved to UTC by accident before, via
  `env('TIMEZONE','UTC')` — and an accident is not a contract. That was an open `REVIEW.md` item.

**Step 7 done — WP 0A exit gate reviewed. VERDICT: NOT PASSED.**

`WP0A_EXIT_GATE.md`. **1 of 7 criteria met, 2 partial, 4 not met.**

- **The most useful thing the review produced was noticing that CI HAS NEVER RUN.**
  `git ls-remote --heads origin contrib/laravel-supported-platform` returns **nothing**; the branch
  has no upstream tracking and all 15 commits are local. A comprehensive CI file was written in
  WP 0B, extended twice this session, and **has never executed once**. It had been counted as a
  closed gate for a week. **A CI file that has never run is a hypothesis, not a gate** — and it was
  only caught by checking the remote instead of reading the workflow.
- **Four of the six failures are cheap, and none of them is characterization.** Push once (closes 5,
  completes 1, lets the rehearsal job run), assemble the compatibility matrix from material that
  already exists (7), owner-review the ledger (4). About a day. **Characterization is the only long
  pole** — 7 suites, 3–4 sessions. Worth knowing before assuming the gate is months away.
- **Criterion 7 has no artifact at all.** No document is identifiable as the "upgrade compatibility
  matrix". The inputs are all there — `DEPENDENCY_INVENTORY.md`, the `composer why-not` evidence in
  the 2026-08-10 entry, UP-002's PHP pin, the MySQL 8.4 notes — but nobody ever assembled them.
  **A gate criterion phrased as "X is reviewed" needs an X that exists**; this one was quietly
  assumed to be satisfied by its inputs.
- **The review corrected UP-007's own risk rating.** It rates conflict risk **High** on prediction;
  Step 6 **measured** it clean against `800c29f`. Left as-is pending owner review rather than edited,
  but flagged — an assessment that has since been measured should say so.
- **Writing the gate as a document rather than a chat answer was the right call.** Seven criteria
  with evidence and a "to close" line each is a reference the next session reads instead of
  re-deriving. It is also the artifact the owner needs in order to *review* anything.

**CI executed for the first time ever — and found two defects plus one production bug**

Branch pushed. Three runs. `merge-rehearsal` is **green**; `test` is green on the entire PHP side
(**48 passed, 108 assertions on Ubuntu**) and fails only at the frontend build.

- **UP-011 (proposed) — `npm run production` has ALWAYS been broken on Linux.** `app.js` imports
  `./components/payaccount/` while git records **`Payaccount/`**. Case-insensitive Windows resolves
  it; Linux does not — **and production is a Linux VPS.** The "production build still works (exit 0,
  290s)" claim carried since the C5 audit was **true only on Windows**, and had been treated as
  evidence the frontend was safe. **A build result is only evidence for the platform it ran on.**
  Second Windows-passes/Linux-fails defect after UP-003; the class is not exhausted — anything
  resolved by string path at build or autoload time is exposed.
- **Run 1: 22 `MissingAppKeyException` failures.** CI wrote a DB-only `.env.testing`, and
  `.env.testing` **replaces** `.env` rather than merging. `MEMORY.md` recorded that exact trap on
  2026-08-10 and the CI file still had it, **because CI had never run**. Knowing a thing and
  executing it are different; only the second one finds this.
- **Run 2: 1 failure — a test that was pinning the dev machine.** The imagick assertion was a flat
  `assertSame(500)`, true only where imagick is absent. GitHub runners ship it, so CI got 200.
  Rewritten environment-aware with both branches asserted. **Assert environment-dependent behaviour
  against the environment, not one machine's defaults.** Third time this session an assertion
  measured the harness rather than the application.
- **Deliberately did NOT install imagick in CI to make it green.** The dependency *is* the finding —
  UP-008 removes it so no environment needs it, and the production VPS lacks it too. Making CI green
  by giving it the extension would have hidden exactly what the test tracks.
- **A verification step now guards the `sed` rewrites in CI**, because a silently non-matching `sed`
  already bit this project once during the package scaffold.

**Not done — read before assuming progress**

- **7 of 11 characterization suites not started; 2 partial.** **48 tests** total, only **37**
  characterization, against **80–120**. The single blocking criterion.
- **UP-011 is proposed, not applied** — upstream-owned, needs approval. CI stays red until it lands.
- **Frontend build still absent from CI** (item 7).
- **Step 5** (merge into `ifgf/main`) correctly still blocked on characterization.
- **WP 0C must not begin** — OPERATING CONTRACT 10. Steps 3–7 of the closure plan (package
  scaffold, smoke tests, `ifgf/main` merge, upstream rehearsal, exit-gate review) remain
  **not started**.
- **No evidence of any 10 → 13 upgrade regression has been found.** The one candidate was withdrawn.
  That is not the same as proving there is none — 37 tests is thin — but the "WP 0B complete" claim
  survives this session intact.
- **This session ran ~40k past the `CLAUDE.md` 120k handoff line**, at owner direction. The last
  stretch produced the open question above. Treat the newest assertions as provisional and review
  `RolePermissionCharacterizationTest` from a fresh session before building on it.
- Steps 3–7 (package scaffold, provider smoke tests, `ifgf/main` merge, upstream merge rehearsal,
  exit-gate review) untouched.
- Nothing pushed. The branch is now 30 ahead / 8 behind `upstream/main`.

---

## 2026-08-11 — Requirements re-review against PRD, Graphify, and current worktree

**Outcome:** the PRD still represents the requested product, but the repository is a Phase-0
foundation and does not yet meet the original product requirements. WP 0B is substantially
complete; WP 0A remains open on characterization, package loading, frontend CI, provider smoke
tests, and upstream merge rehearsal. No FR-01 through FR-13 package implementation has landed.

**Highest-risk finding:** PRD L859/L877/L909/L1502 still require UTC timestamps while the pending
UP-009/build/config changes establish Taipei wall-clock semantics. `build.md` cannot override the
PRD. The owner must choose one contract before the timezone files are approved. The proposed
timezone test passes locally (4 tests, 6 assertions), but CI's generated `.env.testing` does not
set `TIMEZONE`. A UTC process simulation confirmed the failure: 2 failed, 2 passed.

**Other findings:** only two real test files exist; the previous seven-suite testing plan omitted
event management, birthday routes, queues, and private media required by WP 0A item 6. The IFGF
package is absent. CI lacks the frontend build, package provider smoke tests, and merge rehearsal.
The current branch is 26 ahead and 8 behind `upstream/main`; the Graphify graph predates current
HEAD and was used only for legacy architecture anchors. The authoritative GAS/workbook sources
remain unavailable from this workspace and must be supplied or anonymized before WP 0C.

**Tool note:** in this Codex shell, bare `php` is not on PATH. `C:\php\8.4\php.exe` runs directly
and was used for the focused test. Do not repeat PATH diagnosis.

---

## 2026-08-10 — Session 4 — Toolchain to latest LTS; Laravel 10 → 13; PHP 8.4 pinned

**Done:** WP 0A gates 1, 2, 6, 7 closed + CI (gate 4). WP 0B effectively complete:
**Laravel 10.50.2 → 13.24.0** in three attributable commits, **PHP 8.4.24** pinned,
**MySQL 8.4.9 → 8.4.11**. 14 commits on `contrib/laravel-supported-platform`.

**Owner directive mid-session:** after B1–B4, the owner asked to skip ahead and finish the
upgrade. **B5 (characterization tests) and B7 (baseline) were NOT done.** The three majors are
therefore verified against **one** test, not the Release-1 safety net WP 0A was designed to
produce. This is the single largest caveat on the whole upgrade — see "Not verified" below.

**Learned — carry forward**

- **`laracasts/presenter` was the sole hard blocker for Laravel 13**, not laratrust. 0.2.8 is its
  newest release and stops at `illuminate/support ^12.0`. **An earlier reading that laratrust also
  blocked 13 was wrong** — the solver was listing older 8.x versions; installed **8.5.5 declares
  `^13.0`** (8.5.4 is the first that does). Always check the *installed* version's own
  `composer.json` before believing a solver summary. Replaced in-house at
  `app/Support/Presenter/` (~60 lines, 4 files, behaviour-identical).
- **Laratrust 8 renamed its entire public API** — `LaratrustUserTrait` → `HasRolesAndPermissions`,
  `Models\LaratrustRole` → `Models\Role`, `Models\LaratrustPermission` → `Models\Permission`,
  `Middleware\LaratrustPermission` → `Middleware\Permission`. Four call sites, all on the
  authorization surface. Aliased on import so local class names are unchanged.
- **`composer require` silently moved runtime packages into `require-dev`** twice (10→11 and
  11→12) because the non-interactive prompt default answers "no" to the move question and then
  writes to the wrong section anyway. **Edit `composer.json` directly for multi-package major
  bumps** — that is what the 12→13 step did, and it was clean.
- **collision ≥8.6 conflicts with PHPUnit 10**, so 11→12 forced `phpunit/phpunit ^10.5 → ^11.0`.
  The first attempt failed closed on exactly that rather than downgrading silently. Good signal:
  the no-forced-resolution rule surfaced a real constraint instead of hiding it.
- **`Illuminate\Console\Events\CommandStarting` is never dispatched when `APP_ENV=testing`** —
  `Foundation\Console\Kernel` only bridges Symfony's console events when `! runningUnitTests()`.
  A safety guard built on it silently no-ops in exactly the environment it exists to protect.
  `DatabaseSafetyServiceProvider` therefore registers **two** listeners; see its docblock.
- **The suite is now ~8 minutes for ONE test** because `RefreshDatabase` replays all 93 migrations
  against MySQL per test class. `php artisan schema:dump` is the fix; it fails locally because
  `mysqldump` is not on PATH (it is in `C:\Program Files\MySQL\MySQL Server 8.4\bin`).
  **Resolve this before B5** — 10–15 characterization tests at this cost is hours per run.
- **MySQL 8.4 on Windows: `'root'@'localhost'` ≠ `'root'@'127.0.0.1'`.** `localhost` means
  named-pipe/shared-memory; Workbench and the CLI connect over TCP. The original "failed
  connection" was a *missing account for that host*, not a wrong password. Also:
  `--skip-grant-tables` disables networking entirely on Windows, so it is useless for remote
  inspection there.
- **Docs correction — `console.php` DOES schedule tasks.** `schedule:list` shows
  `gego:checkquote` (hourly) and `gego:checkgetresponse` (daily).
  `ROUTE_MIGRATION_INVENTORY.md`'s "no scheduled tasks" claim is static-analysis error.

**Late-session additions (HTTP verification + cleanup)**

- **The app serves real pages on Laravel 13 + PHP 8.4** — `/login` and `/register` return **200**
  with rendered HTML against the seeded test DB. This is the first time the app was exercised over
  HTTP at all this session; everything before it was CLI only, and a framework that boots `artisan`
  can still fail on every request. Worth doing before declaring any upgrade good.
- **`/` returns 500: `imagick` extension missing** (`BaconQrCode` needs it). **Pre-existing** —
  imagick is absent from *both* the old 8.3 and new 8.4 installs and was never in the project's
  27-extension list, so `/` failed identically before the upgrade. `gd` is present and is the
  cheaper fix. **Blocks the server redeploy** if unresolved. `TODO.md` item 1.
- **`.env.testing` REPLACES `.env`; it does not merge.** The B4 version held only `DB_*` keys, so a
  served request died on a missing `APP_KEY` while the PHPUnit suite passed (phpunit.xml supplies
  those separately). It is now a complete environment copied from `.env` with DB overrides. A
  DB-only `.env.<env>` file looks correct and passes tests while breaking the actual app.
- **`php artisan serve --env=testing` does not reach the served subprocess** — the flag configures
  the serve command, not the child that handles requests. Export `APP_ENV=testing` as a real
  environment variable instead.
- **Deleted after verification:** `C:\php\8.3` (88 MB) and
  `D:\MySQL\data-backup-pre-8.4.11-20260810` (201 MB), plus two stale `php.ini.bak-*` files.
  Live `customer_service` data confirmed byte-identical before the backup was removed, and no file
  in the repo or `composer.bat` hardcoded an 8.3 path. **There is no longer a PATH-reorder PHP
  rollback** — reinstalling 8.3 from `windows.php.net` plus `tools\fix-php-ini.ps1` takes about two
  minutes and is the documented path now.
- **MySQL root auth is intermittently flaky on this box.** A verified-working root login failed
  again later in the session with no restart between. Unresolved; the scoped `iJosh` user has been
  reliable throughout. Prefer it, and do not assume a failed root login means a wrong password.

**Not verified — read before trusting this upgrade**

- **No characterization tests exist** for auth, roles, member profile, QR/membership card,
  attendance open/scan/lock/unlock, group access or exports. The upgrade is green against
  `MemberImportCharacterizationTest` only (1 test, 4 assertions, identical at 10/11/12/13/8.4).
- **`route:list` reports 730 routes; the inventory claims 812 static declarations.** 12 are
  commented out; ~70 remain unexplained, most likely duplicate method+URI pairs. **There is no
  pre-upgrade `route:list` to diff against**, so this is NOT proven upgrade-neutral.
- **PHP 8.4 is pinned but not the resolved `php`** — `C:\php\8.3` is in the MACHINE PATH, which
  wins over User PATH. Needs one elevated command (in `TODO.md`).
- **Pre-existing defect found, not fixed:** `app/Models/FeedbackMessage.php` sets `$presenter` to
  `App\Presenters\UserPresenter`, which does not exist — `present()` on that model has always
  thrown. Unrelated to the upgrade.

---

## 2026-08-09 — Session 2b — UP-005, UP-006, upstream pin: three owner-approved decisions actioned

**Work package:** 0A item 6 (first characterization test) + item 3 findings actioned · **Branch:**
`codex-PRD` · **HEAD:** `e9596f5` (preceded by `8c85781`, `9045ce5`)

**Done**

- **UP-005.** Stood up `tests/` from nothing — this repo had zero tests, no `TestCase.php`, no
  `CreatesApplication.php`, no suite directories. Wrote
  `tests/Feature/Admin/MemberImportCharacterizationTest.php` covering
  `ImportMemberController.php:56` → `Excel::import()`, proved it green against pinned
  `phpoffice/phpspreadsheet` 1.30.0, ran `composer update phpoffice/phpspreadsheet
  --with-dependencies` → 1.30.6, re-ran the same test green and unchanged. `composer audit`:
  49/13 → 40/12 advisories, `phpoffice/phpspreadsheet` fully cleared. `laravel/framework`
  confirmed still 10.50.2 throughout.
- **UP-006.** Removed `botman/botman` + `botman/driver-web` (zero call sites, reconfirmed) and the
  orphaned `custompackages/brozot/laravel-fcm` (73 files) plus its `composer.json` `repositories`
  entry. Did the required residual-reference grep first, found a real textual hit in
  `app/Traits/SendPushNotification.php`, then proved via `class_exists()` that the referenced
  `LaravelFCM\*` classes were never in the generated autoloader even before removal — so no
  characterization test was required under `TODO.md`'s own "if reachable" rule.
- Formally froze the upstream pin at `d12c110` in `UPSTREAM.md`'s Baseline table — no merge of the
  8 outstanding upstream commits until the WP 0A exit gate passes.
- Committed Session 2's own pending doc baseline (`CLAUDE.md`, `CONTEXT.md`, `MEMORY.md`,
  `TODO.md`, `DEPENDENCY_INVENTORY.md`), which had sat uncommitted since that session ended.

**Learned — carry forward**

- **`php artisan test` is broken, independent of anything touched this session.** Installed
  `nunomaduro/collision` v6.4.0 is incompatible with installed PHPUnit 10.5.63
  (`RequirementsException`: "Running PHPUnit 10.x or Pest 2.x requires Collision 7.x"). Worked
  around it by running `vendor/bin/phpunit` directly. Not fixed here — bumping `collision` is a
  separate package change outside UP-005's "targeted only" scope, but it blocks WP 0A item 7 (CI
  workflow) until resolved. Fix it in Session 13 or earlier if it starts costing round-trips.
- **`app/Imports/UsersImport.php`'s `collection()` method is broken for any real import.** It
  dereferences an undefined `$request` variable as soon as `count($rows) > 0` — `"Attempt to
  assign property ... on null"`, a PHP `Error`, not caught by its own `catch (Exception $e)`.
  Discovered while designing UP-005's fixture; deliberately used a **header-only** CSV (zero data
  rows) to characterize the `Excel::import()`/phpspreadsheet boundary without also characterizing
  this unrelated, preexisting crash. Member import is effectively non-functional today for any
  file with actual data. Flagged in `TODO.md` "Decisions awaiting the owner" — not fixed.
- **`app/Traits/SendPushNotification.php`'s FCM push path has been dead since before this fork's
  IFGF work started, unrelated to UP-006.** It imports and instantiates
  `LaravelFCM\Message\OptionsBuilder`/`PayloadNotificationBuilder`, but
  `grep -n "LaravelFCM" vendor/composer/autoload_psr4.php` never matched, and
  `class_exists('LaravelFCM\Message\OptionsBuilder')` returned `false` even before
  `custompackages/brozot/laravel-fcm` was deleted — `brozot/laravel-fcm` was declared as a path
  `repositories` entry but never actually `require`d, so it was never wired into the autoloader.
  Any call to `sendNotification()` has been throwing a fatal `Error` in production. Left untouched
  (fixing it is a real application-behavior change to an upstream-owned file, needs its own
  UPSTREAM.md entry + characterization test) — flagged in `TODO.md`.
- **SQLite `:memory:` is sufficient, and `build.md`-permitted, for tests that don't touch
  MySQL-specific features.** Used it as a stopgap in `phpunit.xml` for UP-005's test since WP 0A
  item 5's disposable MySQL 8.4 fixture database doesn't exist yet. All 93 migrations ran clean
  against sqlite with zero MySQL-only syntax found (`DB::statement`, `->change()`, `ENGINE=`,
  `FULLTEXT` all absent from every migration file, checked before trusting sqlite at all). Item 5
  should replace this, not just add to it, once it lands (Session 12).
- **A header-only fixture (template headers, zero data rows) is a clean way to characterize an
  `Excel::import()`/phpspreadsheet upgrade in isolation** from unrelated business-logic bugs
  downstream of the parse. Worth reusing for the other 4 `Imports` classes
  (`DEPENDENCY_INVENTORY.md`: Attendance, Subscribers, Summary, plus this one) when WP 0A item 6
  reaches them.
- **`withoutMiddleware()` + `actingAs()` is the right scope for a dependency-upgrade
  characterization test**, not the full `web`/`auth`/`churchadmin`/`permission:read-members`
  middleware stack — that coverage belongs to WP 0A item 6's own "authentication, existing roles
  and direct permissions" bullet as its own future test, not bundled into UP-005.

**Open / not done**

- WP 0A item 4a (route + migration inventory) — Session 3, now the literal next action in
  `TODO.md`.
- Three items moved to `TODO.md` "Decisions awaiting the owner": the `UsersImport::collection()`
  crash, `SendPushNotification.php`'s dead FCM imports, and whether/when to fix the
  `collision`/PHPUnit mismatch ahead of Session 13's CI workflow.
- Did not touch `vendor/`, `node_modules/`, `yarn.lock`, or `package-lock.json` beyond what
  `composer remove`/`composer update phpoffice/phpspreadsheet`/`composer update --lock` touched.

---

## 2026-08-10 — Session 3h — Readiness check + prompt library

**Versions re-verified today, not carried forward:** Laravel **13** (min PHP 8.3, supports
8.3–8.5) · PHP **8.4** recommended for production (8.3 loses *active* support 2026-11-23; 8.4 has
security to Dec 2028) · MySQL **8.4 LTS** (8.0 EOL April 2026; **9.x are Innovation releases, not
LTS — do not target**). MySQL 8.4 **disables `mysql_native_password` by default** — check the app
user's auth plugin before upgrading, and dump first, the 8.0→8.4 upgrade is one-way.

**Readiness verdict: versions settled, repository not.** WP 0A ≈ 5 of 15 items. Seven open gates
recorded in `TODO.md`: nothing committed (13 files incl. `PRD.md`), no `ifgf/main` or `deploy`
branch, no ifgf package, no CI, 4 test files, **`php artisan test` throws `RequirementsException`**
(collision/PHPUnit 10), no disposable test DB.

**Created `tools/UPGRADE_PROMPT.md`** — three phases. A: PHP 8.4 installed **side by side** with
8.3, 8.3 stays first on PATH (Laravel 10.50.2 is not PHP 8.4 clean and `composer.json` pins
`platform.php = 8.3.33`); MySQL 8.4 LTS. B: close gates 1–7, ending with a recorded
pass/fail/skip baseline. C: Laravel 10→11→12→13 one major at a time, **stopping if any test that
passed in B now fails** — and explicitly forbidding "fixing" the test to make it pass.
`laravel/legacy-factories` must be removed before touching the framework version.

**Created `tools/PROMPTS.md`** — paste-ready prompts P1–P7 covering WP 0C, WP 0D, and Phases
1A, 1B, 1C.1–1C.2, 1D, 1E.1, in order, at Release 1 scope. **Each prompt carries the findings
already paid for** so no session rediscovers them: the cascade and squashed-migration facts for
P1, the phone-normalisation and dead-column facts for P3, the "60% of FR-04 already exists" list
for P4, the pre-tick-from-last-week idea for P5, the `Lokasi` 1.2% and `Absen-TPE_ZL`-unmaintained
facts for P6, the QR-reissue comms requirement for P7. Owner questions are embedded where they
fall — P3 needs Q8/Q15, P6 needs Q1, P7 needs Q6/Q7/Q10/Q11.

**Estimate to production: 73–90 sessions**, of which 20–24 are the upgrade prompt's phases.

---

## 2026-08-09 — Session 3g — PRD amended (first edits to the approved baseline)

**`PRD.md` edited — +27 / −4 lines. Uncommitted; needs owner review before commit.**

| Location | Change |
|---|---|
| FR-02.2 | Home branch nullable, required at registration/activation only. **Follow-up preference removed from the required minimum** — legacy never captured it (0% filled). |
| FR-02.7 | Six sub-items: QR is an **attendance credential only**, ticket-equivalent, never an auth factor. Payload = `qr_token` 32 chars, nothing else. Rotation semantics. **Sequential IDs prohibited** (forgery cost, not secrecy). **Signed/expiring URLs prohibited** (printed cards outlive signatures). Human-readable fallback on the card. |
| FR-04.1 | Five sub-items: **occurrence context lives on the scanner, never in the member QR.** Usher selects event + branch; persistent on-screen display; occurrence change is audited. **Self-service venue-code check-in explicitly out of scope** for MVP with the replay reasoning. |
| FR-04.4 | Scan endpoint takes occurrence ref + `qr_token`, never username/email/sequential ID, rate-limited. Response returns minimum identity only. |
| §13.1 | Six new default decisions, 18–23: Laravel 13 target; home branch nullable; legacy-vs-PRD escalation + authority hierarchy; no blanket import precedence; scanner-side occurrence context; upstream contribution deferred. |
| §13.2 | Q2, Q3, Q9 marked resolved with pointers to §13.1. Note added pointing at `PRD_OPEN_QUESTIONS.md`. |

**Owner simplification — accepted, and better than my proposal**

I proposed two QRs (member card + occurrence code). The owner simplified: **put the event and
location tagger on the usher's phone web app; the QR carries member identity only.** Correct — it
is fewer moving parts, nothing extra to print, and it is already how upstream's `scan()` works
(`session_id` comes from the leader's open session). **QR-B is now optional convenience, not MVP.**
`ATTENDANCE_QR_DESIGN.md` annotated accordingly.

Owner also framed the member QR as **"like a ticketing QR"** — a useful mental model that is now
in FR-02.7.1: it is an admission credential, not an identity or auth credential, so losing one
exposes no account. That framing settles several downstream questions about how much protection it
actually needs.

**⚠ Process lesson — `PRD.md` line numbers shifted**

Editing the PRD invalidated `EXECUTION_PLAN.md` Appendix A. Section 5 moved from 953–1168 to
953–1183; every section after it shifted ~15 lines. **Appendix A has been re-derived.**
**Re-derive it after every `PRD.md` edit** — a stale line map sends sessions to the wrong range,
costing more than the edit saved. Regenerate with:

```
python3 -c "
lines=open('PRD.md',encoding='utf-8').read().split(chr(10))
secs=[(i,l[3:].strip()) for i,l in enumerate(lines,1) if l.startswith('## ')]
secs.append((len(lines)+1,'EOF'))
for (a,n),(b,_) in zip(secs,secs[1:]):
    ch=sum(len(x)+1 for x in lines[a-1:b-1]); print(f'| {a}-{b-1} | {ch//4} | {n} |')"
```

PRD total is now ~33,900 tokens, up from 32,809. Rule T1 unchanged: never read it whole.

---

## 2026-08-09 — Session 3f — Owner decisions + attendance QR design

**Owner decisions recorded**

- **Laravel target = latest stable (13).** Reinforces WP 0B as a hard blocker — Laravel 10 lost
  security support Feb 2025.
- **Q3 home branch = nullable**, required at activation, malformed row → import exception.
  *PRD edit owed:* L402 is correct, amend L973.
- **FR-05 iCare returns to Release 1.** See correction below.
- **QR = opaque random token, not member ID.** Owner asked "isn't the QR just the member ID?" —
  instinct correct (it *is* a lookup key), specific choice wrong. Decisive argument is **forgery
  cost, not secrecy**: a leaked attendance QR is low harm, but a sequential ID turns the attack
  from "photograph a specific card" into "count" — ID 47 proves 1–46 exist, so the whole roster
  could be forged without seeing a single card. Also `build.md` TECHNICAL BASELINE 7, and rotation
  (FR-02.7) is impossible on a primary key.

**Correction — I deferred FR-05 on a bad inference**

`WORKBOOK_INVENTORY.md` found `Absensi iCare` empty and `PRODUCTION_PATH.md` concluded iCare
attendance was not practised. **Wrong.** iCare groups meet weekly on weekdays, each with a leader,
and attendance is taken per member — the *spreadsheet* was never the tool. **Data absence is not
capability absence. Ask the owner before deferring on that basis.** Both documents corrected;
revised estimate 73–90 sessions to production (was 68–83).

**Attendance QR design — `ATTENDANCE_QR_DESIGN.md`**

Owner asked for event information (Super Sunday, iCare, Christmas) inside the QR. **Not possible in
the member card**: member identity is permanent and printed once, event identity is per-occurrence
and weekly. A card saying "Super Sunday" would need siblings per event type and still could not
distinguish which Sunday.

**Resolved with two QRs:** QR-A member card carries `qr_token` only ("who"); QR-B occurrence code
carries `occurrence_token` ("which event, branch, date"). Leader scans QR-B **once** to open the
occurrence, then QR-A **many times**. This is what upstream already does — `scan()` takes
`session_id` + member identifier — so **QR-B is a shortcut for choosing `session_id`, not a new
mechanism.**

- **Rejected member-scans-event-poster self-service.** A static poster QR can be photographed and
  shared → remote check-in fraud; defending it needs a rotating on-screen code. Also 200 arrivals ×
  phone logins is slower than one leader scanning. **And the legacy data settles it:** the old flow
  asked members to pick their location and **98.8% never did**.
- **iCare needs no QR at all** — ~10 members with a leader present; a tick list pre-filled from
  last week beats scanning.
- Four deltas from upstream's `scan()`: `member_username`→`qr_token`, `session_id`→
  `occurrence_token`, writes via `AttendanceRecorder`, and `avatar_url` off the public disk.

---

## 2026-08-09 — Session 3e — PRD review: QR design + Laravel feasibility

**Done:** `PRD_REVIEW.md` — QR redesign recommendation, per-FR feasibility matrix against the
baseline, and suggested PRD amendments.

**Learned — carry forward**

- **The PRD understates the baseline for FR-04.** `EventAttendanceSession` (`opened_by`,
  `locked_at`, `locked_by`) + `EventAttendee` (`scanned_at`, `scanned_by`) + `Api/AttendanceController`
  (`myEvents`, `openSession`, `scan`, `lock`, `sessionReport`) + `Admin/EventAttendanceController`
  (13 methods incl. `unlock`, `searchMember`, `markAttendee`, `manageManagers`) already implement
  **duplicate-scan 409, lock/unlock, manual fallback, and manager assignment** — FR-04.7, .8, .9,
  .11 and part of .10. Roughly **60% of FR-04 exists**. The Phase 1B estimate in
  `EXECUTION_PLAN.md` is likely too high.
- **Upstream's QR encodes `User.name`.** Better than the GAS (no PII) but not opaque, not
  rotatable, and enumerable. More importantly **`scan()` takes `member_username` as a plain request
  parameter** — nothing proves a QR was seen. The current QR provides *no* security control, so
  this is adding one, not hardening one.
- **Recommended QR design is one indexed column**: `qr_token` `char(32)` unique (`Str::random(32)`),
  `qr_version`, `qr_rotated_at`. Payload is the token alone. Rotation = regenerate + bump version.
  **Explicitly reject signed URLs** — a printed card must live for years, so a temporary signature
  expires and a permanent one cannot be revoked per-member without rotating `APP_KEY` for everyone.
  **Reject JWT/encrypted payloads** — payload size raises QR density, which hurts scanning in poor
  entrance lighting, and buys nothing when the server has a database. Sanctum-style
  hash-plus-encrypted storage is available as later hardening; skip initially.
- **Three real risks, in order:** (1) **FR-11, not FR-14, is the hard one** — `usergroup_id` in 33
  files, each an authorization path needing replacement with no window of broader access.
  (2) **`EventAttendee` is presence-only** — no `status` column, so absent/excused cannot be
  expressed; after the additive migration, a *missing row* must not be read as absence.
  (3) **One session per event per date** — `EventAttendanceSession` keys on `attendance_date`,
  contradicting FR-03.2's multiple same-day occurrences. Confirms WP 0C item 5 is real.
- **`Api/AttendanceController::scan()` returns `avatar_url` from `Storage::disk('public')`** —
  contradicts invariant 30 (private storage, signed access). Upstream-owned compatibility fix.
- **Feasibility verdict: 5 adapt, 3 extend, 1 replace, 6 new. No FR is infeasible**, none requires
  abandoning the fork.
- **Graphify is still not a good code index here**, even after `.graphifyignore` (15 MB → 7.3 MB,
  6,919 nodes). Top files by node count are `package.json`, `_design_reference/*.md`,
  `composer.json`, **`PRD.md`** — prose and manifests outrank source; `app/Models/User.php` is the
  first source file at 48 nodes. It was useful for *orientation* only; every finding came from
  targeted grep into files it named. **Extend `.graphifyignore` to `_ai/`, `_design_reference/`,
  `_code_reference/` and the root planning `.md` files** — indexing `PRD.md` returns requirement
  text when you are searching for implementation.

---

## 2026-08-09 — Session 3d — Legacy Google Apps Script behaviour inventory

**Done:** `GAS_INVENTORY.md` from `church-member-management`. Requirements reference is now
complete — workbook (data) + GAS (behaviour), both rank-1 authority.

**Tool correction — important**

**The file tools (`Read`/`Glob`/`Grep`) reach all four selected folders via their Windows paths;
only the bash sandbox is limited to `church-cms-laravel`.** An earlier session claimed the GAS repo
was inaccessible and asked the owner to upload it. That was wrong. When a sibling repo is needed,
use `Glob`/`Read` on `D:\Users\Ian Joseph\Documents\GitHub\<repo>` directly.

**Learned — carry forward**

- **C1 — the legacy member QR leaks contact data, and cannot be rotated.** `generatePrefilledUrl`
  (`qr-code.js:42`) builds the QR content as a Google Forms prefilled URL carrying the member's
  **email, WhatsApp number, full name and iCare group in plaintext**. Photograph the QR, read all
  four. It is a pure function of the member's own data, so regenerating yields an identical code —
  no token, no expiry, no revocation. Contradicts `build.md` SECURITY 8, TECHNICAL BASELINE 7, and
  PRD FR-02. **Escalated to the owner; recommendation is that the PRD wins.** Consequence:
  **every member QR must be reissued at cutover** and old printed codes stop working — the most
  visible user-facing change in the migration, needs a comms plan in Phase 1E.
- **C3 — `cleanPhoneNumber` has a no-op branch.** Leading-zero local numbers are not normalised
  (`cleaned = cleaned; // Keep as is for now`), so `0912…`, `+886912…` and `886912…` are three
  keys for one person. Legacy duplicate detection matches on **email OR phone**, so it has been
  silently missing phone duplicates for years. WP 0C must normalise to E.164 with an explicit
  default region and **expect duplicates the legacy system never detected**. Email matching is
  sound (`trim()` + `toLowerCase()`).
- **`Absen-TPE_ZL` is unmaintained — mystery solved.** `addWeeklyAttendanceColumns` iterates
  `SPREADSHEET.sheets.ABSEN`, which lists only `Absen-TPE` and `Absen-ZL`. That is exactly why the
  combined sheet stalled at 2026-04-26 while the per-branch sheets run to 2026-08-09. **Exclude it
  from import.**
- **Weekly insert mechanics confirmed:** `insertColumnsAfter(5,2)` → copy `H:I` → `F:G` → merge
  `F6:G6`. Columns A–E are the identity block; newest week is leftmost; each week is an
  Onsite/Online pair under a merged date header in row 6.
- **The birthday Calendar design is better than the PRD assumes** and should be carried forward:
  per-member series ID stored in the sheet (the only 100%-populated roster column), in-place
  `setRecurrence()` with delete-and-recreate fallback, detail-only patching when the date is
  unchanged, name-based cleanup when no ID exists, plus `syncAllBirthdays()` reconciliation. Maps
  directly onto PRD FR-08's same-calendar adoption and tombstone requirements.
- **Legacy identity rule:** `addEditUrlSpreadsheet` searches **bottom-up** for a matching email and
  takes the most recent row lacking an edit URL, falling back to the last row. With 3 duplicate
  names in the roster, WP 0C must reproduce or deliberately supersede this.
- **`config.js` holds live access identifiers** — registration and attendance form IDs and entry
  IDs, birthday Calendar ID, spreadsheet ID, admin email. **Never commit these.** Referenced by
  name only in `GAS_INVENTORY.md`. Feeds WP 0D's secret inventory.

---

## 2026-08-09 — Session 3c — Legacy workbook inventory (structure only)

**Done:** `WORKBOOK_INVENTORY.md` from `Jemaat & Absensi (2).xlsx`. **No member data recorded** —
column names, fill rates, distinct counts, categorical distributions only. Workbook not committed,
not modified.

**Learned — carry forward**

- **Only ~14 of 33 roster columns are alive.** 12 are ≤0.9% filled (2 straggler rows each, residue
  of an older form version) and 3 are 0%. Do not model the dead ones as fields.
- **`Lokasi` is missing on 98.8% of attendance scans** — 40 of 3,227 rows. The GAS deliberately
  leaves location blank for the member to pick at scan time, and members almost never do.
  **Branch attribution for historical attendance cannot come from the scan log.** This is the
  single most consequential migration finding so far.
- **The weekly grid is not derived from the scan log.** 3,227 raw scans cannot populate ~40,000
  grid cells across two branches, so the grid is substantially manual. The two sources **will**
  disagree — which the owner's escalate-every-conflict rule (Q2) now covers, and which makes
  `ifgf_import_conflicts` volume potentially large. WP 0C's dry-run must measure it.
- **Attendance is a wide grid, not rows.** 46 weeks × 216 members × Onsite/Online per branch.
  Newest week is **leftmost** (the GAS inserts two columns after column E each week and merges
  `F6:G6`). The pivot to `AttendanceRecord` rows *is* the migration; column headers in row 6 are
  the only occurrence identifiers.
- **`Absensi iCare` contains a header row and no data.** iCare attendance was designed and never
  collected — PRD FR-05 is **new capability with no history to migrate**, not a port. Similarly
  **online attendance is effectively unused** (3 rows marked `Ya` out of 3,227).
- **Q3 answered empirically.** Branch is 99.5% filled, exactly 2 values (Taipei 112, Zhongli 104).
  The one gap is the single malformed row. Recommendation firmed: **nullable column, required at
  activation, malformed row → import exception.** A `NOT NULL` would fail on exactly one junk row.
- **`PRD.md` L973 requires follow-up preference; the source never captured it** (col 32, 0%
  filled). Either drop it from the required minimum or accept every imported member starts unset.
- Data defects to normalise: duplicate `LINE ID`/`Line ID` columns (case-differing, one dead),
  `Tanggal Lahir` with one date stored as text, `Line ID` with 3 numerics among strings, 3
  duplicate full names, free-text `Profesi`/`Tingkat Pendidikan` (**no MySQL enums** — TECHNICAL
  BASELINE 6).
- **`Absen-TPE_ZL` reports 93 weeks but ends 2026-04-26**, four months before the per-branch
  sheets end. Stale view or different granularity — resolve before trusting it; prefer per-branch.
- Detailed weekly grids start **2025-09-28**; 2022–2024 exists only as year-summary sheets.

**Privacy note:** the analysis probe printed real names and emails into the working session. None
were persisted to any file. Future structure probes should mask value columns from the start.

---

## 2026-08-09 — Session 3b — PRD open-question triage + two governing owner decisions

**Done:** `PRD_OPEN_QUESTIONS.md` — all 16 questions in `PRD.md` §13.2 mapped to the work package
they block, with recommended defaults. Q2 and Q9 resolved; **Q3 is the only remaining blocker**.

**Owner decisions, 2026-08-09 — these govern more than the questions they answered**

- **Authority hierarchy.** The legacy **workbook + Google Apps Script are the authoritative
  reference for required data and main functions** — they describe the program running today.
  `PRD.md` is the agreed articulation of that plus everything new. **Upstream ChurchCMS supplies
  implementation, not requirements.** When upstream behaviour and IFGF requirements disagree,
  **escalate to the owner** — neither "upstream already does it this way" nor "the PRD says
  otherwise" settles it alone. This also resolves Q2: every import conflict is escalated, no
  blanket precedence rule. Consequence: `ifgf_import_conflicts` is a first-class admin workflow
  needing a review UI, not an exception log.
- **`upstream/main` is an active feature source, not a frozen base.** The owner wants to keep
  absorbing functionality upstream builds beyond IFGF's own work. Does not change the current pin
  at `d12c110`, but raises the value of every practice that keeps syncs cheap: package-owned
  behaviour, minimal `UPSTREAM.md` entries, `contrib/*` contribution to retire patches, and the
  CI merge rehearsal — which becomes a **recurring** safety net rather than a one-off gate item.

**Blocker created by the authority decision**

The GAS repo (`church-member-management`) and the workbook are **outside this repository** and not
readable from a session scoped to `church-cms-laravel`. They now outrank the PRD on requirements,
and `build.md` forbids inventing behaviour for an unavailable source. **Before Session 6 (WP 0C
member extraction), either open sessions with both repos in scope, or commit an anonymized extract
of the GAS logic and workbook column inventory into this repo — structure and logic only, no real
member data.**

**Learned — carry forward**

- **`PRD.md` contradicts itself on home branch.** L402 models it as optional (`Branch "0..1"`);
  L973 lists it in the required-field minimum. Q3 exists because of this. Both lines must be
  reconciled whichever way it resolves, or WP 0C and Phase 1A will read the same document and
  build different things. **The PRD is otherwise structurally complete** — one incompleteness
  marker in 1,758 lines, and it is not a gap.
- **Q14 is mis-scheduled by its own wording.** It reads as a Phase 1E hosting question, but the
  storage provider and region are needed at **Phase 1A.5**, where profile media first lands. It
  is the earliest infrastructure decision in the project, and it is inseparable from WP 0D's
  cross-border privacy review — Taiwanese member images, Indonesian operations, two data
  protection regimes. Do not pick a bucket region before that review.
- **Q2 should not be answered yet.** Whether an automatic conflict winner is acceptable depends on
  conflict volume, which nobody has measured. `build.md` WP 0C items 13–14 already require the
  all-row reconciliation *before* any member is committed — so build for manual resolution, let
  the dry-run produce the number, then decide.
- Answering a §13.2 question is a **PRD edit**, not just a note: move it into §13.1 and fix every
  section it contradicts. `PRD.md` is the committed baseline (`e394e74`).

---

## 2026-08-09 — Session 3 — Route and migration inventory (WP 0A item 4a)

**Work package:** 0A item 4a · **Branch:** `codex-PRD` · **HEAD:** `a262771`
**Tool note:** run without PHP (static analysis only). `php artisan route:list` still owes a
cross-check.

**Done**

- `ROUTE_MIGRATION_INVENTORY.md` — 812 routes across 4 files, 93 migrations, authorization
  posture per surface, and the WP 0C landmine list with file and line references.
- Settled the `signedRoute` question left open by Session 2.

**Learned — carry forward**

- **The migration history is not real.** 93 files, **91 creates, 91 drops, ZERO alters**, and 90
  of them share the timestamp `2024_01_01_*`. This is a squashed/regenerated set, not accumulated
  history. Consequence for WP 0C: the migrations **do not describe how production reached its
  current shape**, so migrating an anonymized legacy snapshot may diverge from a fresh-database
  run. The 0C exit gate requires both to work — budget for that divergence rather than assuming
  parity. Only 3 migrations are genuinely recent (2026): `group_posts`, `donations`, and
  `add_online_payment_gateways` (the Stripe work that caused UP-001).
- **`event_attendees.user_id` is `ON DELETE CASCADE` to `users`** — line 20 of
  `2024_01_01_000030`. Directly violates PRD invariant 14. **But `users` already has
  `softDeletes()`**, so the normal delete path never fires the cascade; only `forceDelete()`, raw
  SQL, or a future GDPR erasure does. That means WP 0C item 4 and WP 0D's erasure design are the
  same problem and must be solved together, not sequentially. Line 18 is a second exposure:
  deleting an *event* cascades attendance regardless of user soft deletes.
- **`userprofiles.user_id` has a foreign key but no unique constraint** — confirms WP 0C item 3,
  and the item's own wording implies production already contains duplicates.
- **`role_user` is partially right already**: composite primary `(user_id, role_id, user_type)`
  exists, but WP 0C item 8 wants unique on `user_id + user_type` (one role per user), and there
  is **no foreign key on `user_id` at all** — only on `role_id`.
- **Signed URLs are email-verification only** (`Mail/VerifyEmail.php:62`,
  `Auth/VerificationController.php:36`). The signed-URL-confusion advisory does **not** touch QR
  or attendance. Advisory question closed. Separately: membership-card QR is neither signed nor
  rotatable, which is a Phase 1A design gap, not a security fix.
- **`console.php` defines no scheduled tasks.** Everything the PRD expects on a schedule —
  birthday sync, occurrence generation, retention — is new build with no existing counterpart.
- **Only 11 throttle declarations across 812 routes.** Most paths SECURITY RULE 5 wants
  rate-limited are currently unthrottled.
- **`usergroup_id` appears in 33 files** — that is the concrete replacement surface for WP 0C
  item 9 / INVARIANT 22.
- Do **not** blanket-replace all 13 cascade migrations. Only `event_attendance_sessions` and
  `event_attendees` carry pastoral history; the rest are join/versioning/marketing tables where
  cascade is correct. Blanket replacement is churn against upstream-owned files for no gain.

**Method note**

Route middleware was read from `Route::group` declarations only. Middleware applied in controller
constructors is **not** captured — `VerificationController` proves that pattern is in use, so
characterization must check constructors too.

**Not done**

- Item 4b (auth, roles, attendance, cards, exports, media, storage, queues, scheduler).
- `.graphifyignore` was written and staged by an earlier part of this session but **not committed**.

---

## 2026-08-09 — Session 2 — Baseline committed, dependency inventory complete

**Work package:** 0A item 3 (dependency inventory), plus finishing item 2 (doc baseline commit) ·
**Branch:** `codex-PRD` · **HEAD:** `e394e74` (docs baseline) preceded by `4e25e04` (generic repair)

**Done**

- Committed the two Session-1-blocked commits: `4e25e04` (composer.json/composer.lock/factory
  rename) and `e394e74` (PRD.md, build.md, hosting.md, UPSTREAM.md, EXECUTION_PLAN.md, CLAUDE.md,
  CONTEXT.md, MEMORY.md, TODO.md, .gitignore, tools/). **Gate 1 (documentation baseline
  uncommitted) is now closed.** Recorded the docs-baseline SHA in `CONTEXT.md`.
- Produced `DEPENDENCY_INVENTORY.md` — all 194 Composer packages and 51 npm direct packages
  classified keep/upgrade/replace/remove, and the 49 Composer advisories (13 packages) triaged
  by real exposure (grep'd actual call sites in `app/`), not severity label alone.
- Verified the exact Laravel 13 artisan generator flags against a real
  `composer create-project laravel/laravel "^13.0"` skeleton (13.24.0) and recorded them in
  `CLAUDE.md` — not guessed from memory.

**Learned — carry forward**

- **UP-003's rename never actually reached git's index.** The working-tree file was correctly
  cased (`EventGalleryFactory.php`) but `git status` showed no change at all, because this
  checkout has `core.ignorecase=true`: Windows silently absorbed the case-only rename from
  Session 1b without git noticing. `UPSTREAM.md` had every regression checkbox ticked, but the
  commit would have shipped the broken lowercase filename to the Linux VPS. Fixed by `git mv`
  through a **distinct intermediate filename** (not a case-only intermediate) —
  `EventgalleryFactory.php` → `EventGalleryFactory_tmp_rename.php` → `EventGalleryFactory.php` —
  which forces git to register two real path changes even under `core.ignorecase`. **Any future
  case-only rename on this checkout needs the same two-hop trick with a non-case-only
  intermediate name**, not the same-case-different-case two-step `build.md`/`UPSTREAM.md`
  describe (that version silently no-ops here).
- **This tool is not sandboxed the way Session 1/1b's was.** `php`, `composer`, and `curl` to
  `packagist.org`/`repo.packagist.org` all worked directly with no host relay. `CLAUDE.md`'s "PHP
  does not run in the sandbox" section and `EXECUTION_PLAN.md` §3.4's round-trip-cost model are
  written for whatever ran Session 1/1b (probably a network-sandboxed container) and do not apply
  to Claude Code running directly on this Windows host. Flagged inline in `CLAUDE.md`; did not
  rewrite the cost model itself — re-verify at the start of whichever session reads this.
- **Two findings beyond `EXECUTION_PLAN.md` §1.4's own predictions**, both now in
  `DEPENDENCY_INVENTORY.md`: (1) `botman/botman`+`botman/driver-web` have **zero** call sites
  anywhere in `app/`/`config/`/`routes/` — not just "high risk," dead code, remove outright.
  (2) `custompackages/brozot/laravel-fcm` is an **orphaned path repository** — the directory and
  the `composer.json` `repositories` entry both still exist, but nothing actually requires the
  package anymore (superseded by `laravel-notification-channels/fcm`, already installed). It is
  not one of the 194 resolved packages, so `composer show` won't surface it — only a
  `composer.json`/filesystem cross-check catches it.
- **Real-exposure triage flipped priority away from severity label** in two directions:
  `phpoffice/phpspreadsheet` (9 advisories, 2 critical) has a **confirmed live sink** —
  `ImportMemberController.php:56` calls `Excel::import()` on an admin-uploaded file — so it
  should be fixed before WP 0B, not deferred with the rest of the compatibility work. Conversely
  `dompdf/dompdf` (6 advisories) and `spatie/browsershot` (6 advisories) have **zero** call sites
  anywhere in `app/` or `resources/views/` — present, patchable, but not urgent.
- **npm's 166 vulnerabilities are almost entirely one root cause.** Nearly all critical/high
  entries live inside the `laravel-mix`/`webpack@4` toolchain's own transitive dependencies —
  patching them individually is wasted effort; they leave with the WP 0B Vite migration. The
  npm-specific exceptions worth fixing *now*, independent of that timeline: `axios` (0.18→1.x,
  high, cheap) and `lodash` (high, cheap). `vue-image-lightbox-carousel` is critical **and**
  confirmed still in active use — replace, don't just delete the feature.
- `@dymantic/vue-trix-editor` (high severity, unmaintained) and `@tiptap/*` (current, Vue-3-ready)
  are both actively referenced in the Vue components right now — the app is mid-migration from
  Trix to TipTap. Finish it and drop the Trix wrapper rather than patching it.

**Open / not done**

- WP 0A item 3 exit criteria met for this session's scope; **owner should review
  `DEPENDENCY_INVENTORY.md`'s "Prioritize now" row (phpoffice/phpspreadsheet) before WP 0A
  Session 3 starts**, since it argues for pulling that one upgrade earlier than WP 0B.
- Confirm during WP 0A item 4 (route inventory, Session 3) whether QR/attendance check-in uses
  `temporarySignedRoute` — the one `signedRoute` call site found needs to be checked against the
  `laravel/framework` signed-URL-confusion advisory.
- `custompackages/brozot/laravel-fcm` orphan removal and `botman/*` removal are recommended but
  **not executed this session** — they are dependency changes, out of scope for an inventory-only
  session, and need an explicit commit of their own with the owner's sign-off.
- Did not touch `vendor/`, `node_modules/`, `composer.lock`, or `package-lock.json` — this session
  was inventory and classification only, no upgrades applied.

---

## 2026-08-08 — Session 1b — Toolchain installed, three upstream defects fixed

**Work package:** 0A items 1–2 · **Branch:** `codex-PRD` · **HEAD:** `d8cfe08` (uncommitted work)

**Done — the baseline now installs**

- PHP **8.3.33** at `C:\php\8.3`, all **27 required extensions** loading, no startup warnings.
- Composer **2.10.2**. Node 22.15.0 (wrong line — see below). MySQL present.
- `npm ci` — 1,286 packages installed.
- `composer install` — 194 packages, autoload generated, 21 packages discovered.
- `composer validate --strict` → **valid**. `php artisan about` → **Laravel 10.50.2 / PHP 8.3.33**.
- Framework confirmed **unchanged at 10.x** — WP 0B was not started by accident.
- Logged UP-001, UP-002, UP-003 in `UPSTREAM.md`; created that file.

**Learned — carry forward**

- **Upstream ships a repository that cannot install.** Three independent defects, all present
  identically on `HEAD`, `upstream/main` and `origin/main`:
  - **UP-001** — `composer.lock` desynced from `composer.json`: stripe, kreait/laravel-firebase
    and the fcm channel absent from the lock; `lcobucci/jwt` locked at 4.3.0 against `^5.2`.
  - **UP-002** — three packages locked at **PHP ≥ 8.4-only versions** in a `php: ^8.2` project:
    `symfony/css-selector` v8.0.9, `symfony/filesystem` v8.0.11, `lcobucci/clock` 3.6.0. Symfony
    was split 13 components on 6.x, 3 on 7.x, 2 on 8.x. Masked by UP-001 — the out-of-date-lock
    error fires before Composer reaches the platform check.
  - **UP-003** — `database/factories/EventgalleryFactory.php` declares `class EventGalleryFactory`.
    PSR-4 case mismatch, so the class is **excluded from the autoloader**. Only occurrence in the
    repo; `app/` is clean. Hidden by case-insensitive Windows; will bite on the Linux VPS.
- All three are the residue of partial `composer update` runs committed without a clean-checkout
  verification. **A CI job doing `composer install` from a clean checkout on Linux would have
  caught all three** — argues for pulling WP 0A item 7 much earlier than Session 13.
- **Enumerate, don't discover.** Fixing UP-002 one failed resolution at a time cost three
  round-trips. Scanning the whole lock for PHP-constraint conflicts found all three instantly.
  Prefer a whole-file scan over iterative failure whenever the search space is enumerable.
- Added `config.platform.php = 8.3.33` to `composer.json` so resolution stops depending on the
  developer's local PHP. Also satisfies WP 0B item 14. **Revisit when production moves to 8.4.**
- Fix command that worked:
  `composer update stripe/stripe-php kreait/laravel-firebase laravel-notification-channels/fcm
  lcobucci/jwt lcobucci/clock symfony/css-selector symfony/filesystem --with-dependencies`

**Windows toolchain gotchas — do not rediscover**

- winget's `PHP.PHP.8.3` manifest points at 8.3.32, which has moved to the archives and 404s.
  PHP is downloaded directly from windows.php.net instead (`tools/setup-windows.ps1` probes URLs).
- Composer's winget id is **`GetComposer.Composer`**, not `Composer.Composer`. `Laravel.Herd`
  does not exist in winget.
- `opcache` is a **zend_extension**, not `extension`. `bcmath` and friends have no `php_*.dll` on
  Windows — enumerate `ext\php_*.dll` rather than hardcoding a wishlist (`tools/fix-php-ini.ps1`).
- `Set-ExecutionPolicy -Scope Process` does not survive a new shell. Owner set `CurrentUser`.

**Open / not done**

- **UP-003 not yet fixed** — the factory rename still pending.
- **Node is 22.15.0**, not 16.20.2; `nvm use` did not stick. `npm ci` worked anyway, but
  `npm run production` is where webpack 4 will break.
- **52 Composer advisories / 14 packages**; **166 npm vulnerabilities, 13 critical**. Triage in
  Session 2. Do **not** run `npm audit fix` or unargumented `composer update`.
- `.env` was created from `.env.example` but **`APP_KEY` has not been generated** and no database
  has been configured. Nothing has been committed.

---

## 2026-08-08 — Session 1 — Requirements audit and execution planning

**Work package:** pre-0A · **Branch:** `codex-PRD` · **HEAD:** `d8cfe08`

**Done**

- Audited installed requirements against `composer.json` and `package.json`. Verdict: nothing
  installed — no PHP, Composer, MySQL, `vendor/`, `node_modules/`, or `.env`.
- Confirmed Laravel 13 is the newest stable major on 2026-08-08 and requires PHP ≥ 8.3, as
  `build.md` TECHNICAL BASELINE 2 requires be rechecked at execution time.
- Created `EXECUTION_PLAN.md`, `CONTEXT.md`, `TODO.md`, `MEMORY.md`, `CLAUDE.md`,
  `tools/setup-windows.ps1`.
- Added `/graphify-out` to `.gitignore` per OPERATING CONTRACT 9.

**Learned — carry forward**

- **The assistant sandbox cannot run PHP at all.** No root; `packagist.org`, `php.net`,
  `getcomposer.org`, `api.github.com`, `codeload.github.com` are all outside the network
  allowlist. Only `github.com` and `registry.npmjs.org` resolve. Every PHP command must run on the
  Windows host with its output pasted back. Do not spend tokens re-testing this.
- **`tests/` contains zero files.** The characterization suite required by WP 0A item 6 is being
  written from nothing, not extended. This is the single largest driver of the schedule estimate.
- **Reading `PRD.md` + `build.md` + `hosting.md` whole costs 53,786 tokens** — 36% of a 150k
  session. Measured, not estimated. Use the line index in `EXECUTION_PLAN.md` Appendix A.
- **PHP 8.3 is the only version spanning both ends of the upgrade.** Laravel 10 supports 8.1–8.3;
  Laravel 13 requires ≥8.3. Installing 8.3 now avoids a second PHP install at WP 0B.
- **`laravel/legacy-factories` v1.4.2 is a hard Laravel 13 blocker** — Laravel 10 max. Factories
  must be rewritten. Also flagged: `botman/botman` 2.8.11, `spatie/laravel-medialibrary` 10.15,
  `laravel/sanctum` 3.3.3, `nunomaduro/collision` 6.4, `santigarcor/laratrust` 7.2.1,
  `doctrine/annotations` 2.0.2 (abandoned upstream).
- **Node 22.22.3 is installed but wrong** for laravel-mix 4 / webpack 4. Pin 16.20.2 via
  nvm-windows until the Vite migration lands in WP 0B.
- **Graphify output is stale and noisy** — it indexes compiled `public/js/app.js`, so architecture
  queries return generated bundles. Do not trust graph results until `.graphifyignore` lands
  (WP 0A Session 5).
- Upstream is 8 commits ahead of `HEAD`; newest reviewed upstream commit is `d12c110`
  (2026-08-07, "Added privacy policy page"). No merge rehearsal has ever been run.

**Blocked**

- Documentation baseline is uncommitted, so `build.md` OPERATING CONTRACT 6 blocks all coding.
- No toolchain, so no `composer install`, no `artisan`, no tests.

**Not done — deliberately**

- No dependency install, no upgrade, no schema change, no commit. WP 0A item 2 installs from
  lockfiles without updating; all upgrades belong to WP 0B behind its own approval gate.
