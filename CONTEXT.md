# CONTEXT

> Current state only. Rewritten every session end. Hard cap: 1,500 tokens.
> History belongs in `MEMORY.md`. Next actions belong in `TODO.md`.

**Updated:** 2026-08-21 · **Session:** WP 0A closure Step 2 (characterization suites 9 and 11) ·
**Work package:** WP 0A — item 6 and the exit gate remain. WP 0B complete.

**Exit gate 2026-08-21: 4 of 7 met, 0 partial, 3 not met — UNCHANGED.** Criterion 2 moved 37 → 62
tests (4 of 11 suites) without crossing the line. Criteria 4 and 7 have their review pack
(`WP0A_OWNER_REVIEW.md`, three decisions) and remain owner-gated. **Step 5 (merge to `ifgf/main`)
correctly NOT performed.**

**Test counts, 2026-08-21:** full `Feature` suite **73 passed, 250 assertions, 0 failed, 0 skipped**,
2m41s. 62 characterization + 11 package smoke. Package suite 3 passed, 10 assertions. Suites done: 2 roles+permissions, 4 attendance,
**9 exports**, **11 private media**, plus a cross-cutting date-cast regression file.

**Three findings this session. SEC-003 was FIXED (UP-012); the other two are characterized, NOT
fixed and need an owner decision:**
**SEC-003** (`/admin/changeavatar` accepted any file type, unvalidated, into a webroot-symlinked
disk — **FIXED same day, UP-012**; first rated RCE, corrected to **stored XSS** via `.svg`/`.html`), **REG-001** (`protected $dates` removed in
Laravel 10; **21 columns across 9 models silently uncast**; a WP 0B regression that has left the
attendance CSV export dead since the upgrade), and the **SEC-002 export extension** (the attendance
export never consults `event_managers` either).

**`SCHEMA_SPEC.md` appeared untracked at the repo root** (Cowork, 19 `ifgf_` tables). It is WP 0C
material. Left untracked and unactioned.

## Git

| | |
|---|---|
| Branch | `contrib/laravel-supported-platform` — **30 ahead, 9 behind** `upstream/main` |
| HEAD | `f192b11` (timestamp contract settled on UTC) |
| This session | `aa3d279` schema dump · `7ab95a9` agent instructions + prompt library · `4d8a13d` audit/status/test plan · `f192b11` timezone |
| `ifgf/main` | exists at `aa8194e` (= `main`), local only, **not yet merged into** — Step 5 |
| `main` | `aa8194e` — clean subset of `upstream/main`, no local commits |
| **Reviewed upstream pin** | **`d12c110`** — the SHA `UPSTREAM.md` freezes and the only one reviewed |
| **Local `upstream/main` ref** | **`800c29f`** ("Changes grouplink", 2026-08-11) — the ref has **moved past the pin**. Do not confuse the two. Merging is still blocked until the WP 0A exit gate. |
| `deploy` | does not exist (correct — 1E.3) |
| Pushed? | **Nothing pushed.** All 30 commits are local only. |

## The working tree is now clean

The four-month backlog of uncommitted work is committed. `PROJECT_STATUS.md`, `REVIEW.md` and
`TESTING_PLAN.md` are tracked. `.env` and `.env.testing` remain gitignored and were never staged.

## Timestamp contract — SETTLED, do not reopen

**UTC at rest.** `PRD.md` L859/L877/L909/L1502 and `build.md` TECHNICAL BASELINE 3 now agree; the
2026-08-10 `Asia/Taipei` proposal is **rejected**, with its reasoning preserved in `UPSTREAM.md`
UP-009 "Alternatives considered". `TIMEZONE=UTC`, `'timezone' => '+00:00'` on the MySQL connection.

**Accepted consequence, pinned by a test:** `event_attendance_sessions.attendance_date` is a
`date()` column that never converts, so a service between **00:00 and 08:00 Taipei files under the
previous UTC calendar day**. Services from 08:00 onward — every regular Sunday service — are
unaffected. **Any code deriving a calendar day from an instant must convert to the branch timezone
first**; the 8 `date()` columns are where that rule gets broken.

## Environment

| | Installed | Active |
|---|---|---|
| PHP | 8.4.24 only (8.3 deleted) | 8.4.24 — bare `php` resolves correctly in Claude Code |
| Laravel | 13.24.0 | 13.24.0 |
| MySQL | 8.4.11 LTS | **must be started by hand — see below** |
| Composer / Node / PHPUnit | 2.10.2 / 22.15.0 / 11.5.56 | — |

**⚠ MySQL has NO registered Windows service.** `Get-Service` returns only `postgresql-x64-17`.
Nothing listens on 3306 after a reboot and every test errors `SQLSTATE[HY000] [2002]`, which reads
as a code failure and is not. The install is intact — `mysqld.exe`,
`C:\ProgramData\MySQL\MySQL Server 8.4\my.ini`, `D:\MySQL\data` with `churchcms_test_disposable`.
Start it in the background (accepts connections ~4s later); the command is in `TODO.md`.
Registering it permanently needs elevation and is an open owner question.

`mysqldump` **is** on the Machine PATH (`C:\Program Files\MySQL\MySQL Server 8.4\bin`).

## Test and database

- **Disposable DB:** `churchcms_test_disposable`, MySQL 8.4.11, user `iJosh`, scoped to that DB.
  Credentials in `.env.testing` (gitignored). `phpunit.xml` points at it via `APP_ENV=testing`.
- `App\Providers\DatabaseSafetyServiceProvider` prints and asserts environment/driver/host/database
  before `migrate:fresh`/`db:wipe`/`migrate:reset` (`build.md` L517). Both paths verified.
- **`database/schema/mysql-schema.sql` is now committed** (`aa3d279`), so `RefreshDatabase` loads
  one squashed schema instead of replaying 93 migrations. The old ~8-minute-per-class figure
  predates it. The tracked root `mysql-schema.sql` is an unrelated legacy artifact — Laravel reads
  only `database/schema/<connection>-schema.sql`, so they never compete. Question closed.
- **Last recorded runs (2026-08-21):** full `tests/Feature` suite **73 passed, 250 assertions,
  0 failed, 0 skipped, 2m41s**. **Coverage: 11 test files, 73 tests** — 62 characterization + 11
  package smoke. (Was 8 files / 48 tests / 37 characterization on 2026-08-18.)
  **Package's own suite** (runs independently of the application):
  `vendor/bin/phpunit -c custompackages/ifgf/church-operations/phpunit.xml` → **3 passed**,
  10 assertions.

  ⚠ **Target.** `TESTING_PLAN.md` Part 1 lists **eleven** suites (its "seven" heading is stale) and
  targets **80–120 tests**, not the 60–90 quoted in earlier notes.
  **Done: 2 Roles+permissions, 4 Attendance, 9 Exports, 11 Private media**, plus a cross-cutting
  date-cast regression file. **Partial: 1 Auth, 3 Member profile, 4 Attendance.**
  **Not started: 5 QR/card, 6 Groups, 7 Event management, 8 Birthday, 10 Queues.**
  Suite 1 owes password reset, email verification, session lifetime and throttling; suite 3 owes
  create/edit/delete/export behaviour; suite 4 owes `searchMember` and `removeAttendee`.

## ⚠ Admin view tests need `settings.*` config — read before writing one

**Not a defect. A fixture requirement, and it will bite every remaining suite that renders an admin
page.** `AppServiceProvider::boot()` populates `settings.*` from the `church_details` table for
`Church::first()`. A disposable test DB has no such rows, so `config('settings.favicon')` is NULL,
and `resources/views/layouts/admin/layout.blade.php` line 6 does `{{ url(\Config::get('settings.favicon')) }}`.
**`url(null)` returns the UrlGenerator instance** — documented Laravel behaviour, not a version
change — which Blade's `e()` rejects:

```
htmlspecialchars(): Argument #1 ($string) must be of type string,
Illuminate\Routing\UrlGenerator given
```

Every admin page on this layout 500s without it. `MemberProfileCharacterizationTest::seedRuntimeSettings()`
is the pattern: set the config directly, because the provider runs at **boot**, before a test's
fixture rows exist.

**~~MEM-001~~ — WITHDRAWN 2026-08-15.** This was briefly recorded as "the member admin UI is broken
on Laravel 13", a suspected upgrade regression. **It was wrong.** With settings seeded, `/admin/members`,
`/admin/member/add` and `/admin/member/edit/{name}` all return **200**. The lesson, which cost a
commit: **a 500 in a test environment is a fixture question until proven otherwise — read the actual
failing line before attributing a failure to the framework.**

`GET /admin/member/show/{name}` **does** still 500, on missing **imagick**. That one is real,
survives the fixture, and is already owned by UP-008.

## WP 0C item 3 confirmed with data — `userprofiles` allows duplicate rows per user

`userprofiles.user_id` has a foreign key but the index is **`Non_unique = 1`**, and a second row for
the same user **inserts cleanly** (verified, not inferred). Architectural invariant 4 requires
exactly one row per user. Every `$user->userprofile` accessor silently picks **one** row, so a
duplicated member can show different names, birthdays or membership types by row order. **WP 0C must
dedupe before adding the unique key, and "which row wins" is a pastoral data decision needing the
owner, not a technical one.**

Also pinned: `/member/show/{name}` and `/member/edit/{firstname}` key on **name**, and `users.name`
is **not unique within a church** — those URLs are ambiguous. FR-02.7 prohibits sequential IDs in
URLs; the answer is its specified opaque token, not a name.

## SEC-002 — attendance has NO per-leader scope

An `event_managers` table **exists** (`id, event_id, user_id`) and `EventAttendanceController`
manages it via `manageManagers`/`storeManager`/`removeManager`. But **`openSession`, `markAttendee`,
`lock` and `unlock` never consult it.** Their only guards are the permission middleware and
`abort_unless($session->church_id === Auth::user()->church_id, 403)`.

So **any user holding `create-attendance` can record attendance for any event in their church**,
including events they were never assigned to. Assigning event managers today is organisational
bookkeeping, **not authorization**. PRD invariant 7 requires leader access limited to assigned
scope — that control **does not exist yet**; FR-11 must build it, not adjust it.

This is an **absence** of a control, not a broken one, which is why reading the attendance code
never reveals it. Pinned by `test_documents_defect_unassigned_leader_can_record_attendance`, which
asserts the current (wrong) 200. **Replace it with the denial when FR-11 lands; do not delete it.**

Church isolation **does** work (403 cross-church) and is asserted separately, so a refactor cannot
remove the one scope control that exists while the suite stays green.

Also recorded: **`unlock` records no reason and there is nowhere to put one** — no
`unlock_reason`/`reopen_reason`/`unlocked_by` column exists, and the endpoint takes no input. FR-04
requires a mandatory reopen reason. Reopening attendance is exactly what a pastoral audit trail is
for, and today it leaves none.

## Attendance semantics — pinned before WP 0C, which is the whole point

`event_attendees` is **presence-only**: `session_id, church_id, event_id, user_id, scanned_at,
scanned_by`. **No `status`, no `participation_mode`, no `capture_method`.** A row means "recorded
present". **No row means NOT RECORDED — not "absent".** A member whose scan failed, a session nobody
opened, and a member who stayed home are all represented identically: by nothing.

**Therefore an FR-04 backfill writing `'absent'` for every member without a row would invent
pastoral data that was never observed** — a wrong answer to "who has stopped coming?", which is
exactly what FR-10's inactive-risk report asks. Pinned by
`test_documents_event_attendees_is_presence_only_and_has_no_status`.

Two constraints also pinned, and they interact:
- `event_attendees` UNIQUE `(session_id, user_id)` — the duplicate-scan guard is a **database
  constraint**, not controller logic. FR-04's `AttendanceRecorder` must not drop it.
- `event_attendance_sessions` UNIQUE `(event_id, attendance_date)` — one session per event per
  calendar day, so an event held twice on a Sunday cannot be represented (FR-03).
  **`attendance_date` is a `date()` column and the app now stores UTC**, so a 00:00–08:00 Taipei
  service lands on the previous UTC day: an early service and the main Sunday service can get
  **different** dates on the same Taipei day, and a Saturday-evening and Sunday-early service can
  **collide** on the same one. FR-03's occurrence model must resolve both together.

## AUTH-001 — registration is live although explicitly disabled

`routes/web.php:68` passes `['register' => false]` to `Auth::routes()`. `routes/web.php:71` then
calls `Auth::routes()` **again with no arguments**, re-registering the default set and reopening
registration. `GET /register` returns **200**. This is the public account-creation surface of a
church member database; whether it is exploitable depends on what `RegisterController` does with
`usergroup_id`/`church_id` on an unauthenticated POST — **not yet characterized, check before any
public deployment.** The duplicate `Auth::routes()` call is also a plausible contributor to the
unexplained route-count gap (730 vs 812). Pinned by
`test_documents_defect_register_route_is_reachable_despite_being_disabled`.

Also recorded: `['verify' => true]` is configured and the `email_verified_at` column exists, but
**nothing blocks an unverified account from logging in.** Relevant to FR-02's activation flow.
- ✅ The earlier "role-mediated resolution may be broken" question is **RESOLVED — it is not
  broken.** The fixture used usergroup 1, so the first gate redirected the request before the
  permission middleware ran. Cause was the two-gate ordering below.

## The authorization surface has TWO legacy gates, in order — read before writing any auth test

1. **`churchadmin` → `App\Http\Middleware\MustBeChurchAdmin`** (`Kernel.php:71`). usergroup **3 or
   4 pass**; usergroup **1 is redirected to `/portal`**; anything else **aborts 403**. Runs **first**.
2. **`permission` → `App\Http\Middleware\AdminOrPermission`** (`Kernel.php:74`) — SEC-001 below.

**A fixture in usergroup 1 never reaches gate 2.** Use **usergroup 4** to test permissions: it
clears gate 1 and is not gate 2's bypass value. **Denial is `401`**, not 403 — `config/laratrust.php`
sets `handling => abort`, `abort.code => 401`. An authorized request currently returns **500**: it
clears both gates and then `UserController@index` itself fails, a separate pre-existing defect owned
by suite 3.

This cost a wrong baseline once. The first version of the roles test used usergroup 1, so every
"permission" assertion was actually observing the `/portal` redirect, and one test was left
incomplete on a false suspicion that Laratrust role resolution was broken.

## SEC-001 — the legacy authorization bypass is ONE LINE

`app/Http/Kernel.php:74` aliases `'permission'` to `App\Http\Middleware\AdminOrPermission` instead
of Laratrust's own middleware, and that class admits **any** `usergroup_id == 3` account to **every**
`permission:*` route with no role, no direct grant and no audit record
(`AdminOrPermission.php:16`). Severity high. It is a single alias, not the 33 scattered call sites
earlier notes implied — easier to fix, much easier to miss. **It cannot be removed before FR-11**
maps legacy groups onto the three approved roles; removing it today locks out every church admin.
Pinned by `test_documents_defect_usergroup_id_three_bypasses_all_permission_checks`, which must be
**replaced, not deleted**, when FR-11 lands.

Two related behaviours are recorded but not fixed, both needing their own `UPSTREAM.md` entries:
`routes/web.php:182` guards the `/admin/*` group with `permission:*` but **not** `auth`, and the two
denial paths disagree — **401** for a guest, **302 redirect** for an authenticated user without the
permission.

## Known debt

- **Characterization coverage is 62 tests against an 80–120 target, 4 of 11 suites.** WP 0A item 6 /
  gate 5. Three Laravel majors were crossed without a behavioural baseline, and **REG-001 is the first
  proof that broke something** (21 date columns silently uncast). **Still the largest open risk and
  the reason WP 0A cannot close.** Eleven suites are specified in `TESTING_PLAN.md` Part 1.
- **0 `ifgf_` tables. 0 of 14 FRs complete.** The package seam now exists but is empty by design.

## ✅ Merge rehearsal RUN for the first time — 2026-08-15, and the merge is CLEAN

Against `upstream/main` = **`800c29f`** (40 ahead / 9 behind). **No conflicts** —
`git merge-tree --write-tree` exits 0 with no conflict output, and the real merge on a throwaway
branch applied cleanly. **UP-007's prediction that `laracasts/presenter`'s removal would be the
likeliest conflict did NOT materialise.** Upstream touches only 8 files and **no** `composer.json`,
migrations or config, so there is no dependency churn.

On the merged tree: `composer validate --strict` clean, package suite 3/3,
characterization suite **47 of 48**. The single failure is
`test_documents_defect_member_show_fails_on_missing_imagick` going red **because upstream fixed the
defect** — it deleted the `format('png')` QR call from `idcard.blade.php`. A documenting test doing
exactly its job. **UP-008's scope drops from 8 call sites to 7**, and that file must NOT be
hand-edited; take upstream's version.

Now automated: the **`merge-rehearsal` job** in `.github/workflows/ci.yml`. Read-only by
construction — `contents: read`, upstream added fetch-only with its push URL disabled, merge on a
throwaway branch in an ephemeral runner, nothing ever pushed. It runs alongside `test`, not as a
gate on it, so a red rehearsal never blocks unrelated work. **It is supposed to be able to fail** —
red means upstream changed something this fork has pinned. Read the diff before touching the test.
- `/` returns 500 — `imagick` absent, pre-existing, resolved by decision to `format('svg')` (UP-008,
  not yet landed).
- 166 npm vulnerabilities; Vue 2 EOL. `npm run production` still builds (exit 0). Never `npm audit fix`.
- `route:list` = 730 vs the inventory's 812; ~70 unexplained, no pre-upgrade baseline to diff.
- Pre-existing and unfixed: `UsersImport::collection()`, `SendPushNotification.php`,
  `FeedbackMessage.php`'s missing presenter.

## ✅ The IFGF package seam EXISTS — WP 0A items 11 and 14 closed

`custompackages/ifgf/church-operations` is scaffolded and loading. **Nothing IFGF-specific was
legally placeable before this; now it is.** Composer **path repository** + `require ^0.1.0` in the
root manifest; PSR-4 `Ifgf\ChurchOperations\` → `src/`; registration via
`extra.laravel.providers` **auto-discovery**, so the root `app.php` provider array is untouched.
Recorded as **UP-010**. Verified: `composer validate --strict` clean, provider in
`bootstrap/cache/packages.php`, `php artisan ifgf:ping` prints the merged config, route registered.

**No product behaviour, and two tests guard that** — one in each suite, both asserting the package's
`database/migrations/` is empty. Schema is WP 0C's, which needs its own approval.

Two gotchas worth knowing before adding another path package: a path package with **no `version`**
resolves to `dev-<branch>` and fails `minimum-stability: stable`; and `"*"` fails
`composer validate --strict` as an unbound constraint. Neither is obvious from the error text.

⚠ **The seam's failure mode is silent.** A merge that drops the `repositories` or `require` entry
does not error — the package stops loading and IFGF behaviour disappears while the app keeps
serving. `tests/Feature/Package/PackageProviderSmokeTest.php` is the alarm, and the **merge rehearsal
(item 13) must run it**.

## 🟢 CI IS LIVE — first executions ever, 2026-08-17

Branch **pushed** to `origin/contrib/laravel-supported-platform` (43+ commits; nothing had ever been
pushed). Three runs so far.

| Job | Result |
|---|---|
| `test` | ✅ **GREEN** — every step, incl. `npm ci` + `npm run production`. 48 passed, 108 assertions *(the suite at that run; it is 71/238 as of 2026-08-21 and CI has not re-run since)*. |
| `merge-rehearsal` | ✅ **GREEN** — clean merge against `800c29f`, characterization + package suites pass on the merged tree. |

**Fully green as of run `32090487802`, 2026-08-18.** It took four runs; three failed for real
reasons (see `WP0A_EXIT_GATE.md` criterion 5).

**Two real defects found by running CI, both invisible locally:**

1. **CI's `.env.testing` had no `APP_KEY`** — 22 `MissingAppKeyException` failures on run 1.
   `.env.testing` *replaces* `.env`, it does not merge. Fixed: copy the complete env, override in
   place, and a verification step now fails loudly if a `sed` matches nothing.
2. **`test_documents_defect_member_show_fails_on_missing_imagick` was pinning the dev machine.**
   GitHub runners ship `imagick`; this box does not, so the route is 200 there and 500 here. Rewritten
   environment-aware. **Assert environment-dependent behaviour against the environment.**

## ✅ UP-011 (CLOSED 2026-08-18) — `npm run production` had ALWAYS been broken on Linux

`app.js` imports `./components/payaccount/` but git records **`Payaccount/`**. Windows resolves it;
Linux does not — and production is a Linux VPS. **The "build still works (exit 0, 290s)" note carried
since the C5 audit is true only on Windows.** Convention was 34 lowercase dirs to 1 capitalised, so the fix
was renaming the directory — done 2026-08-18 via a two-step `git mv` (`core.ignorecase = true`
here makes a direct case-only rename a silent no-op). A case-sensitive sweep of every live
`require('./components/…')` against `git ls-files` now finds **zero** mismatches and **no**
capitalised component directory. Second Windows-passes/Linux-fails defect after UP-003.
**Confirmed fixed in CI run `32090487802` — the first Linux `npm run production` success in this
project's history.** The class is not exhausted: anything resolved by string path at build or
autoload time is exposed, and `core.ignorecase = true` here means a case-only `git mv` is a silent
no-op — do it in two steps and verify with `git ls-files`, never a directory listing.

## ❌ WP 0A exit gate: REVIEWED 2026-08-17 — **NOT PASSED**

Full assessment in **`WP0A_EXIT_GATE.md`**. **Re-scored 2026-08-21: 4 of 7 met, 0 partial, 3 not met — unchanged from 2026-08-18.**

| # | Criterion (`build.md` L227) | Verdict |
|---|---|---|
| 1 | Installs reproducibly / documented blocker | ✅ **met** — proven on a clean Ubuntu checkout |
| 2 | **Critical behavior has characterization coverage** | ❌ **the real blocker** — 62 tests, 4 of 11 suites complete |
| 3 | Package seam loads without changing behavior | ✅ met |
| 4 | `UPSTREAM.md` + ownership map reviewed | ❌ review pack ready (`WP0A_OWNER_REVIEW.md`); owner review outstanding |
| 5 | CI runs from a clean checkout | ✅ **met** — fully green incl. frontend build |
| 6 | Upstream merge rehearsal passes | ✅ **met** — `merge-rehearsal` job green in CI |
| 7 | Upgrade compatibility matrix reviewed | ❌ artifact assembled; owner review outstanding (`WP0A_OWNER_REVIEW.md` Part 3) |

**Criteria 4 and 7 are now a single owner reading session** — `WP0A_OWNER_REVIEW.md` carries both.
**Characterization is the only remaining work** — 5 of 11 suites plus 3 partials, 2–3 sessions.

**WP 0C MUST NOT BEGIN.** `build.md` OPERATING CONTRACT 10 forbids starting a later work package
while an earlier exit gate is incomplete — and WP 0C's highest-risk items (the `usergroup_id`
replacement across 33 files, cascade deletes, the `userprofiles` dedupe) are exactly what
characterization coverage exists to make safe.

## Open gates

1. **WP 0A characterization gate** — 5 suites unwritten, 3 partial. Blocks everything.
2. ~~**WP 0A package gate**~~ — **CLOSED 2026-08-15.** Package scaffolded (item 11), smoke tests
   written (item 14). See UP-010.
3. **WP 0A CI gates** — **no frontend build.** ~~merge rehearsal~~ and ~~package suite in CI~~ both
   **closed 2026-08-15**. The frontend build (`npm ci` + `npm run production`) is the last CI gap.
4. `contrib/laravel-supported-platform` unmerged into `ifgf/main`, gated on gate 1.
5. Upstream merge blocked until the exit gate passes; reviewed pin `d12c110`, ref at `800c29f`.
