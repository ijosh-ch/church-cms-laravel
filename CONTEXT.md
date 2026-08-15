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
- **Last recorded run:** `TimezoneCharacterizationTest` — **6 passed, 0 failed, 0 skipped**,
  11 assertions, **1.96s**. `MemberImportCharacterizationTest` not rerun since Session 2b.

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
