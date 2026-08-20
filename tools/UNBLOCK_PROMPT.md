# Unblock prompt — close the WP 0A exit gate

**Supersedes P0 in `tools/PROMPTS.md`**, which is now partly stale: the IFGF package scaffold and
provider smoke tests are already done.

This is the only work between here and running `tools/GENERATION_MANIFEST.md`.
**3–4 sessions.** Re-paste to resume; `CONTEXT.md` and `TODO.md` track position.

---

```
Read CLAUDE.md, CONTEXT.md, TODO.md and the last 5 entries of MEMORY.md.
Then read WP0A_EXIT_GATE.md in full — it is the scorecard this session closes.

Goal: take the WP 0A exit gate from 4/7 to 7/7, then merge to ifgf/main.
Do NOT start WP 0C. Do NOT run tools/GENERATION_MANIFEST.md.

STEP 1 — clear the staged work. (~5 minutes)
.graphifyignore has been staged and uncommitted for several turns. Commit it with
the message you proposed. Then commit the untracked tools/*.md — GENERATION_MANIFEST.md,
GRAPHIFY_UPDATE_PROMPT.md, UNBLOCK_PROMPT.md, and any others. Show staged files and
message first. This clears exit-gate precondition 3.

STEP 2 — criterion 2, characterization. THE REAL WORK. 3-4 sessions.
37 tests today against an 80-120 target. 2 of 11 suites complete.

NOT STARTED, in this order:
  9  Exports        — touches member PII, wholly uncharacterized
  11 Private media  — touches member PII, wholly uncharacterized
  5  QR / membership card
  6  Groups
  7  Event management
  8  Birthday
  10 Queues

PARTIAL, finish these too:
  1  Auth           — owes password reset, email verification, session lifetime, throttling
  3  Member profile — owes create/edit/delete/export behaviour
  4  Attendance     — owes searchMember, removeAttendee

METHOD — standing, not a suggestion. Before writing any assertion, run a diagnostic
printing status, Location and state for several inputs SIDE BY SIDE. Three times this
project has asserted against the harness instead of the application; every file written
diagnostic-first has needed zero correction.

TRAPS that already cost commits — do not rediscover:
  - Any test rendering an admin view must seed settings.* first. Copy
    MemberProfileCharacterizationTest::seedRuntimeSettings(). Otherwise every admin
    page 500s on "htmlspecialchars(): ... UrlGenerator given", which looks exactly
    like an application bug.
  - Every /admin/* route sits behind BOTH legacy gates — RouteServiceProvider applies
    ['web','auth','churchadmin'] to all of routes/admin.php. Use usergroup 4. Denial
    is 401, not 403. Read RolePermissionCharacterizationTest's docblock first.
  - Check each planned suite HAS A SUBJECT before budgeting it. Several TESTING_PLAN.md
    entries describe FR-11 behaviour that does not exist and cannot be characterized.
    Report those rather than inventing them.
  - Assert environment-dependent behaviour against the environment, not one machine's
    defaults.
  - MySQL has no registered Windows service. Start it before testing:
    & "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --defaults-file="C:\ProgramData\MySQL\MySQL Server 8.4\my.ini" --console
    Accepts connections ~4s later. SQLSTATE[HY000] [2002] means this, not a code fault.

Capture what IS, not what SHOULD BE. If a behaviour looks wrong, assert the wrong
behaviour and note it in MEMORY.md. Fixing and characterizing in one pass destroys
the baseline's value.

Two assertions to write explicitly:
  - A MISSING attendance row is NOT absence. EventAttendee is presence-only today;
    write this before WP 0C adds the status column, so the migration cannot quietly
    change what "no row" means.
  - A leader with NO assignment is DENIED. Assert the denial directly. Hidden
    navigation is not authorization (build.md SECURITY 11).

Record pass/fail/skip counts in MEMORY.md after each run. THIS IS THE BASELINE.

STEP 3 — criteria 4 and 7. OWNER REVIEW, not your work.
Criterion 4 (UPSTREAM.md + ownership map) and 7 (compatibility matrix) both need my
sign-off, and they close together in one sitting. Prepare a single review document:
  - every UPSTREAM.md entry, one line each, with what it changed and why
  - the unclassified files — RouteServiceProvider, phpunit.xml — with your proposed
    classification
  - the compatibility matrix, as-is
Then ASK ME to review it. Do not mark either criterion met yourself.

STEP 4 — merge contrib/laravel-supported-platform into ifgf/main.
ONLY after step 2 is green and step 3 is signed off. 49 commits. This is the single
hardest action here to undo. Re-run the full suite after merging and report counts.

STEP 5 — re-score WP0A_EXIT_GATE.md. Report which criteria are met and which are not.
Do NOT mark the gate passed if any criterion fails. Then STOP.

Next session runs tools/GENERATION_MANIFEST.md. Do not start it.
```

---

## What Cowork does in parallel

Cowork does not need to wait for this. The next Cowork session writes the
**schema specification** — every column, type, constraint, index and foreign key for all 19
`ifgf_` tables, plus the model relations.

That work depends only on `PRD.md`, `WORKBOOK_INVENTORY.md`, `GAS_INVENTORY.md` and the existing
schema. All are stable. It touches nothing the exit gate is verifying.

Doing it now means that when generation runs, the fill pass is transcription rather than design —
and the schema gets reviewed while it is still a document, which is the cheapest moment to catch a
wrong column type.

**Sequence:**

| # | Who | Work |
|---|---|---|
| 1 | **Claude Code** | This prompt — close the gate, merge to `ifgf/main` |
| 1b | **Cowork, in parallel** | Schema specification for the 19 tables |
| 2 | **Claude Code** | `tools/GENERATION_MANIFEST.md` — generate ~74 empty files |
| 3 | **Cowork** | Fill every body from the spec |
| 4 | **Claude Code** | Migrate, test, fix, commit |

Step 3 is the long Cowork pass. Step 1b makes it fast.
