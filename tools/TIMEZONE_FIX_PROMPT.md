# Timezone fix — Claude Code prompt

Paste the fenced block into Claude Code, in this repository.

Small, self-contained task. Should fit comfortably in one session.

---

```
Read CLAUDE.md, CONTEXT.md and TODO.md. Then read TESTING_PLAN.md's
"Timezone defect" section in full — it has the analysis and the owner decision.

TASK: fix the application timezone. Owner decision 2026-08-10: Asia/Taipei.

Background you do not need to re-derive:
- `php artisan about` reports Timezone = Asia/Kolkata. Upstream ChurchCMS is an
  Indian project and .env inherited it from .env.example.
- .env.example has TWO timezone variables. config/app.php:71 reads
  env('TIMEZONE','UTC'), so line 62 TIMEZONE=Asia/Kolkata wins and line 92
  APP_TIMEZONE=UTC is dead and never read.
- config/database.php sets NO timezone on the mysql connection, so MySQL uses the
  server default.
- The schema mixes column types: 23 timestamp() (MySQL converts using the SESSION
  timezone), 10 dateTime() (stores literally, no conversion), 8 date().
  event_attendees.scanned_at and event_attendance_sessions.locked_at are timestamp;
  event_attendance_sessions.attendance_date is date.
- Therefore if PHP and MySQL disagree, timestamp columns shift and dateTime columns
  do not — half the data looks correct. Both halves of the fix are required.

STEP 1 — configuration. Both changes, not one.
  a. .env and .env.example:  TIMEZONE=Asia/Taipei
  b. config/database.php, inside the 'mysql' connection array:
       'timezone' => '+08:00',
     Use the fixed offset, NOT 'Asia/Taipei'. Named zones need MySQL's timezone
     tables loaded, which a default install usually lacks. Taiwan has no daylight
     saving, so a fixed offset is exactly correct and cannot drift.
  c. Remove or reconcile the dead APP_TIMEZONE=UTC line in .env.example. Leaving a
     variable that looks authoritative but is never read is a trap.

STEP 2 — UPSTREAM.md entry. Required, do not skip.
  config/database.php and .env.example are upstream-owned. CLAUDE.md's hard
  prohibitions forbid editing one without an UPSTREAM.md entry and a
  characterization test. Add UP-00N covering:
    - what changed and why
    - that this is an APPROVED DEVIATION from build.md TECHNICAL BASELINE 3, which
      specifies UTC storage
    - the rationale: Taiwan has no DST so UTC+8 is constant year-round; both
      branches are in one zone; the legacy Apps Script manifest already uses
      Asia/Taipei so imported timestamps map 1:1 with no conversion step to get
      wrong
    - revisit condition: a branch outside UTC+8 is ever added
    - alternatives rejected: UTC storage with per-branch display conversion
  Also add a one-line note to build.md TECHNICAL BASELINE 3 pointing at the entry,
  so a future session does not "correct" this back to UTC.

STEP 3 — characterization assertions. Put them where a regression hurts most: the
attendance surface. Use `php artisan make:test` rather than hand-writing.
  a. config('app.timezone') === 'Asia/Taipei'
     and date_default_timezone_get() === 'Asia/Taipei'
  b. The live MySQL session timezone is +08:00. Assert with a raw query
     (SELECT @@session.time_zone), not by reading config — the point is to catch
     config and reality disagreeing.
  c. ROUND-TRIP: in one request, write the same Carbon instant into a `timestamp`
     column and a `dateTime` column, read both back, assert identical wall-clock.
     This is the assertion that actually catches a PHP/MySQL mismatch; (a) and (b)
     alone would both pass while data silently shifts. Pick a real column pair if
     one exists, otherwise create a temporary table inside the test.

STEP 4 — verify and report.
  php artisan config:clear
  php artisan about --only=environment
  php artisan test 2>&1 | Select-Object -Last 40
  Report the timezone line and the test counts.

STEP 5 — no data migration should be needed. Confirm it: there is no real member
data yet and the test database is disposable. If you find persisted rows with
timestamps written under Asia/Kolkata, STOP and tell me before touching them —
that changes this from a config fix into a data correction.

RULES
- Show me staged files and the commit message before committing. Do not commit
  .env (it is gitignored and holds secrets).
- Do not run migrate:fresh or db:wipe without printing and asserting environment,
  driver, host and database name first (build.md L517).
- Stop and report if any existing test fails after the change — that is a real
  finding, not noise to fix past.
```

---

## Why this is worth a dedicated session

The config change is two lines. The value is in steps 2 and 3.

**Step 2** stops a future session from reverting it. `build.md` says UTC storage; this deviates.
Undocumented, that reads as a bug to someone six weeks from now.

**Step 3c** is the only assertion that catches the actual failure mode. Checking that config says
`Asia/Taipei` and `+08:00` proves the *files* are right. It does not prove MySQL and PHP agree at
runtime — and when they disagree, `timestamp` columns shift while `dateTime` columns do not, so
half the data looks correct. The round-trip test is what fails loudly instead.

## Sequencing

Do this **before WP 0C**. Right now it is a config edit. After 3,227 legacy attendance scans are
imported, it becomes a data migration correcting timestamps that are wrong at rest.
