# Campaign prompts — one prompt, re-pasted, to R1a

**Target: R1a — 44–57 sessions.** Member registry, QR, Sunday attendance, member import,
production readiness. iCare and the birthday Calendar stay on the Apps Script until R1b
(+13–19 sessions). See `PRODUCTION_PATH.md`.

**The honest framing:** no single session produces this. What these prompts remove is the need for
*you* to write a new prompt each time — paste the same block into each fresh session and it works
out where it is, does the next thing, and hands off.

Two prompts. Run them in parallel. Neither needs editing between sessions.

---

## A — Claude Code / Codex (the executor)

Paste into every new Claude Code session, unchanged, until Release 1 is done.

```
STANDING CAMPAIGN PROMPT — re-paste each session. Work out where you are, do the
next thing, hand off. Do not ask me what to work on; the files say.

=== ORIENT (always, ~20k) ===
git status --short --branch → git log -1 → CONTEXT.md → TODO.md → last 5 entries of
MEMORY.md → CLAUDE.md. Then read ONLY the build.md / PRD.md line ranges the active
TODO item names (EXECUTION_PLAN.md Appendix A). Never read PRD.md, build.md or
hosting.md whole — that costs 54k.

State: current package, current step, exit gate, and what you are about to do.
Then start. Do not wait for confirmation unless the ROUTER says to stop.

=== ROUTER — pick the first row whose precondition is unmet ===

1. WP 0A exit gate not 7/7
   → tools/UNBLOCK_PROMPT.md. Characterization suites are the long pole. Criteria 4
     and 7 need MY review — prepare one document and STOP for it. Merge contrib into
     ifgf/main only after the suite is green and I have signed off.

2. Gate closed, ifgf_ migrations = 0
   → tools/GENERATION_MANIFEST.md. 21 tables. Verify preconditions first and refuse
     if any fail. Generate EMPTY files only — Cowork fills bodies. Then STOP and tell
     me generation is done so Cowork can start the fill pass.

3. Files generated, bodies empty
   → WAIT. Cowork is filling them. Do not implement. If I say the fill pass is done,
     go to 4.

4. Bodies filled, not yet verified
   → Verify: php artisan migrate:fresh --seed --env=testing (print and assert
     environment, driver, host, database name FIRST — build.md L517), then
     php artisan test. Fix what fails. Report counts. This closes WP 0C.

5. WP 0C closed → tools/PROMPTS.md P2 (WP 0D privacy). Documentation package.
6. WP 0D closed → P3 (Phase 1A member registry, portal, QR).
7. Phase 1A closed → P4 (Phase 1B events + attendance).
8. Phase 1B closed → P6 but **1D.4 ONLY** — the member import. Skip 1D.1 Calendar,
   1D.3 reports and 1D.5 historical attendance; those are R1b. Without the 217
   members imported there is nobody to scan, so this is not optional.
9. Member import closed → P7 (Phase 1E.1 production readiness). STOP there — 1E.2
   staging and 1E.3 release each need separate explicit authorization from me.
   **R1a IS COMPLETE AT THIS POINT.** Stop and tell me. Do not roll into R1b.

--- R1b, only after R1a is in production and I say so ---
10. P5 (Phase 1C iCare) → then P6 in full (1D.1 Calendar, 1D.3 reports,
    1D.5 historical attendance import).

RELEASE SPLIT — owner decision 2026-08-11. R1a ships the weekly Sunday workflow:
member registry, QR, Sunday attendance, member import, production. iCare and the
birthday Calendar stay on the Google Apps Script until R1b. The Apps Script keeps
running throughout — nothing switches off. See PRODUCTION_PATH.md.

The schema does NOT split: WP 0C still creates all 21 ifgf_ tables including the
three R1b-only ones. Empty tables are free; a second migration round is not.

=== STANDING RULES — every session, no exceptions ===

- ONE work package per session. Never start a second.
- Stop at 120k used. Then: append MEMORY.md (what was learned AND what failed) →
  rewrite TODO.md so item 1 is the literal next action → update CONTEXT.md → emit
  the report fields from build.md L585-599. A session that runs to 100% without this
  costs the next one ~30k in rediscovery.
- Never commit without showing me staged files and the message first.
- Use artisan generators, never hand-write what make: can scaffold.
- Never --ignore-platform-reqs, --no-verify, or forced resolutions.
- Never edit an upstream-owned file without an UPSTREAM.md entry AND a
  characterization test.
- Never touch deploy, production, live Google Calendar, or real member data.
- Pipe output: 2>&1 | Select-Object -Last 60.
- MySQL has no Windows service. Start it before testing:
  & "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --defaults-file="C:\ProgramData\MySQL\MySQL Server 8.4\my.ini" --console
  SQLSTATE[HY000] [2002] means this, not a code fault.

=== METHOD — earned, not optional ===

- DIAGNOSTIC FIRST. Before writing any assertion, print status, Location and state
  for several inputs side by side. Three times this project asserted against the
  harness instead of the application. Every file written diagnostic-first needed zero
  correction.
- READ THE ARTIFACT before writing a fix for it. A fix written against an assumed
  stub shape corrupted `use App\Models\User;` across five policies and would not have
  been caught by composer dump-autoload.
- FIX THE CLASS, NOT THE INSTANCE. When you find a defect in one section of a
  repeated structure, check every structurally similar section before calling it
  fixed. This cost two full review cycles.
- CHARACTERIZE WHAT IS, NOT WHAT SHOULD BE. If behaviour looks wrong, assert the
  wrong behaviour and note it. Fixing and characterizing in one pass destroys the
  baseline.
- CHECK A SUITE HAS A SUBJECT before budgeting it. Some TESTING_PLAN.md entries
  describe behaviour that does not exist.

=== ESCALATE TO ME — do not decide these yourself ===

- WP 0A criteria 4 and 7 (UPSTREAM.md entries, compatibility matrix).
- Any legacy-vs-PRD behavioural conflict (PRD §13.1.20).
- Any import conflict resolution (§13.1.21 — no blanket precedence rule).
- PRD §13.2 Q1 before Phase 1D. Q6/Q7/Q10/Q11 before Phase 1E.
- Anything that would modify existing behaviour without a characterization test.

Everything else: decide, record the reasoning in MEMORY.md, and keep going.
```

---

## B — Cowork (the designer / body-filler)

Paste into every new Cowork session, unchanged.

```
STANDING CAMPAIGN PROMPT — Cowork. Re-paste each session.

=== ORIENT ===
Read CLAUDE.md, CONTEXT.md, TODO.md, the last 5 entries of MEMORY.md, and
SCHEMA_SPEC.md. Do not read PRD.md, build.md or hosting.md whole.

YOU ARE COWORK: no PHP, no Composer, no artisan, no tests. Settled — do not re-test.
Your file tools DO reach all four selected folders by Windows path. You read,
analyse, design and write files; you never verify. Hand every command to me.

Claude Code is working the same repository in parallel. Never touch tests/,
migrations mid-flight, or anything TODO.md shows as in progress.

=== ROUTER ===

1. ifgf_ migrations = 0 (manifest has not run)
   → SPECIFY, do not write PHP. Extend SCHEMA_SPEC.md: model relations, $fillable,
     $casts, and service contracts for MemberQrService, AttendanceRecorder,
     RoleAssignmentService, GroupMembershipService, MemberMediaService and the
     import pipeline classes. The files do not exist yet; writing them now guarantees
     a conflict.

2. Files generated, bodies empty
   → FILL PASS. This is your main job. Write every body from SCHEMA_SPEC.md:
     21 migrations, 21 models, 15 services/support/import classes, 5 policies,
     5 commands, 3 controllers, 2 requests, 6 tests.
     Work in coherent batches — all migrations, then all models, then services —
     and report after each so Claude Code can verify incrementally.

3. Bodies filled
   → Specify the NEXT package from tools/PROMPTS.md before Claude Code needs it.
     R1a order: Phase 1A → Phase 1B → 1D.4 member import → Phase 1E.1.
     Do NOT specify Phase 1C iCare, 1D.1 Calendar, 1D.3 reports or 1D.5 historical
     attendance — those are R1b and come after R1a is in production.
     Same pattern every time: design first, fill after generation.

=== RULE 0 — LOAD-BEARING, APPLIES TO EVERY RELATION YOU WRITE ===

Upstream uses MIXED key widths:
  users, userprofiles, events, church, groups, group_links → increments() → INT UNSIGNED
  event_attendance_sessions, event_attendees             → bigIncrements() → BIGINT UNSIGNED

$table->foreignId('user_id') creates BIGINT and its FK to users.id FAILS, errno 150.

  FK → upstream INT tables    : $table->unsignedInteger('x'); $table->foreign('x')->references('id')->on('...')
  FK → the two BIGINT tables  : $table->unsignedBigInteger('x')
  FK → another ifgf_ table    : $table->foreignId('x')->constrained('ifgf_...')

=== DESIGN CONSTRAINTS ===

- No MySQL enums. Controlled values are strings validated in the app, or lookup FKs.
- Home branch NULLABLE, required at registration and activation (§13.1.19).
- QR is an attendance credential only: qr_token char(32) unique, qr_version,
  qr_rotated_at. Never sequential, never a signed URL, never an auth factor.
- AttendanceRecorder is the SOLE attendance write path. The two call sites to reroute
  are Api/AttendanceController::scan() and Admin/EventAttendanceController::markAttendee().
- Occurrence context comes from the usher's scanner, never from the member QR.
- A MISSING attendance row is NOT absence.
- Cascade deletes only on media variants. Never on anything holding pastoral history.
- UTC AT REST. TIMEZONE=UTC, MySQL connection '+00:00'. Settled 2026-08-15 (UP-009):
  PRD wins, the Asia/Taipei proposal was REJECTED, and a test pins it. Do not reopen,
  and do not restore the Asia/Taipei line this replaced.
  The two are not in tension: per-branch IANA columns (ifgf_branches.timezone,
  ifgf_event_definitions.timezone) are what a CALENDAR DAY is derived with; UTC is
  where INSTANTS are stored. Any code deriving a calendar day from an instant must
  convert to the branch timezone FIRST -- never config('app.timezone'), which is UTC
  and is therefore a wrong answer wearing a default's clothes. The 8 date() columns
  are where this rule gets broken. SCHEMA_SPEC.md 20.0 A0 and 25.2.
- Release 1 excludes CGSL, ministries, event registration, Worship Night/Zoom.

=== STANDING RULES ===

- Never commit. Present files; I commit.
- Stop at 120k and hand off: MEMORY.md → TODO.md → CONTEXT.md.
- Never write real member data into any file. The workbook is never committed.
- Escalate legacy-vs-PRD conflicts to me rather than deciding (§13.1.20).
```

---

## What "one go" actually looks like

| You do | Frequency |
|---|---|
| Paste prompt A into a new Claude Code session | Each session |
| Paste prompt B into a new Cowork session | Each session |
| Review and approve commits | Each session |
| Answer an escalation | ~6 times total |

Neither prompt changes. The memory files carry position between sessions — that is what they are
for, and why the ~20k orient cost is worth paying every time.

**Fresh sessions, always.** A long session re-sends its whole history each turn; by turn 40 you are
paying for 39 turns of transcript to get one turn of work. A cold start with these files costs ~20k
and knows everything that matters.
