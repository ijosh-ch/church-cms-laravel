# CONTEXT

> Current state only. Rewritten every session end. Hard cap: 1,500 tokens.
> History belongs in `MEMORY.md`. Next actions belong in `TODO.md`.

**Updated:** 2026-08-15 · **Session:** WP 0A closure Step 1 (commit the baseline) · **Work package:**
WP 0A — items 6, 11, 13, 14 and the exit gate remain. WP 0B complete.

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
- **Last recorded runs:** `TimezoneCharacterizationTest` — **6 passed**, 11 assertions, 1.96s.
  **Full `tests/Feature` suite: 36 passed, 97 assertions.** Every test verified today.
  **Coverage: 7 test files, 36 tests.**

  ⚠ **Corrected target.** `TESTING_PLAN.md` Part 1 lists **eleven** suites (its "seven" heading is
  stale) and targets **80–120 tests**, not the 60–90 quoted in earlier notes.
  **Done: 1 Auth (partial), 2 Roles+permissions, 3 Member profile (partial), 4 Attendance.**
  **Not started: 5 QR/card, 6 Groups, 7 Event management, 8 Birthday, 9 Exports, 10 Queues,
  11 Private media.** Suite 1 still owes password reset, email verification, session lifetime and
  throttling; suite 4 owes `searchMember` and `removeAttendee`.

## 🔴 MEM-001 — the member admin UI is BROKEN on Laravel 13

**The first concrete evidence of an actual 10 → 13 upgrade regression**, and exactly what WP 0B
item 6 would have caught had it not been skipped by owner directive (`MEMORY.md` 2026-08-10).

```
GET /admin/members            500  ViewException: htmlspecialchars(): Argument #1
GET /admin/member/add         500  ($string) must be of type string,
GET /admin/member/edit/{name} 500  Illuminate\Routing\UrlGenerator given
GET /admin/member/show/{name} 500  imagick missing (SEPARATE cause, UP-008 owns it)
GET /admin/members/find       200  (JSON)
```

**NOT PROVEN a regression** — a type error reaching Blade's `e()` is the *shape* of a framework
behaviour change, but there is no pre-upgrade baseline to diff, the same gap as the 730-vs-812 route
count. **To settle it: check out `086f33d`, hit the same route, compare.** Do not record it as a
regression until then.

Render behaviour for suite 3 cannot be characterized while the views throw. What is pinned instead:
the authorization decisions (a 500 proves the request cleared both gates *and* the permission
middleware; 401 proves it did not), the data-layer invariants, and the two failures themselves.

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

- **Characterization coverage is 2 test files against a 60–90 test target.** WP 0A item 6 / gate 5.
  Three Laravel majors were crossed without a behavioural baseline. **The largest open risk, and the
  reason WP 0A cannot close.** Seven suites are specified in `TESTING_PLAN.md` Part 1.
- `custompackages/ifgf/church-operations` **still not scaffolded** (item 11, gate 3). Nothing
  IFGF-specific can legally land until it exists. **0 `ifgf_` tables. 0 of 14 FRs complete.**
- Merge rehearsal **never run** (item 13). Provider smoke tests absent (item 14).
- `/` returns 500 — `imagick` absent, pre-existing, resolved by decision to `format('svg')` (UP-008,
  not yet landed).
- 166 npm vulnerabilities; Vue 2 EOL. `npm run production` still builds (exit 0). Never `npm audit fix`.
- `route:list` = 730 vs the inventory's 812; ~70 unexplained, no pre-upgrade baseline to diff.
- Pre-existing and unfixed: `UsersImport::collection()`, `SendPushNotification.php`,
  `FeedbackMessage.php`'s missing presenter.

## Open gates

1. **WP 0A characterization gate** — 7 suites essentially unwritten. Blocks everything.
2. **WP 0A package gate** — no `custompackages/ifgf/church-operations`.
3. **WP 0A CI gates** — no frontend build, no provider smoke tests, no merge rehearsal.
4. `contrib/laravel-supported-platform` unmerged into `ifgf/main`, gated on gate 1.
5. Upstream merge blocked until the exit gate passes; reviewed pin `d12c110`, ref at `800c29f`.
