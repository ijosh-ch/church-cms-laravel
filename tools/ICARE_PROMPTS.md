# iCare weekly attendance — the two build prompts

**Written 2026-08-21 (Cowork).** Companion to `SCHEMA_SPEC.md` Part 3 (§25), which is the design
these prompts execute. They slot into `tools/CAMPAIGN_PROMPT.md`'s router as the iCare specialization
of rows A2/A4 and B2 — **they do not replace the standing campaign prompts** and neither one changes
between sessions.

---

## 🔴 Read this before pasting either one

**Neither prompt can run today, and both refuse to.** Four preconditions are unmet, in this order:

| # | Precondition | State 2026-08-21 | Who closes it |
|---|---|---|---|
| 1 | **WP 0A exit gate 7/7** | ❌ **4 of 7.** Criterion 2 at 94 tests / **5 of 11 suites** (corrected 2026-08-21 — see below) — count met, coverage not. Criteria 4 and 7 need one owner reading session (`WP0A_OWNER_REVIEW.md`) | Claude Code (suites 7, 8, 10) + **you** |
| 2 | **D7–D11 answered** (§25.7) | ❌ unanswered. **D7 and D8 add columns** — generating migrations first means regenerating them | **you**, one sitting |
| 3 | **WP 0C generation run** — 21 tables + the iCare additions | ❌ 0 `ifgf_` migrations exist | Claude Code (`GENERATION_MANIFEST.md`) |
| 4 | **R1a in production** | ❌ — iCare is **R1b** by the 2026-08-11 release split | **you** (or answer D11 and move it) |

`build.md` OPERATING CONTRACT 10 forbids starting a later work package while an earlier exit gate is
open, and WP 0C's highest-risk items are exactly what characterization exists to make safe. **The
next action for iCare is not an iCare action** — it is suites 7/8/10 and your reading session.

**Precondition 4 is the one to decide consciously.** If you want iCare in R1a, that is decision D11
answered "R1a", and it delays the Sunday cutover; `PRODUCTION_PATH.md` and the campaign router both
need updating to match. Answering it "R1b" costs nothing and these prompts wait.

**✅ Fixed 2026-08-21 — decision D4.** `tools/CAMPAIGN_PROMPT.md` section B (the standing **Cowork**
prompt) ended its design constraints with *"Timezone Asia/Taipei, MySQL connection +08:00"* — the
contract **UP-009 rejected on 2026-08-15**. It is struck, and replaced with the settled contract plus
the derived rule, because a bare "UTC at rest" does not tell a session how to derive a calendar day
and that is the actual trap. `SCHEMA_SPEC.md` §20.0 A0 and §24 D4 are marked resolved.

**Worth keeping, because it is the general lesson:** `CAMPAIGN_PROMPT.md` was written **2026-08-21**,
six days *after* the decision. This was not a stale line surviving a change — it was a **rejected
contract re-entering standing instruction through a brand-new document**. A settled decision is only
settled in the artifacts that were reconciled to it, and a fresh file is not automatically one of
them.

**Suite arithmetic, corrected 2026-08-21.** The figure had been **6 of 11**, and 6 + 3 partial + 3
not started = 12 against a denominator of 11. Resolved against `TESTING_PLAN.md` and the 13 test
files on disk rather than by picking a reading: complete are suites **2, 5, 6, 9 and 11 — five**;
partial 1, 3, 4; not started 7, 8, 10. **5 + 3 + 3 = 11.** Three files are outside the eleven
altogether — the cross-cutting date-cast regression file, `TimezoneCharacterizationTest` (session 6's
UP-009 pin) and `MemberImportCharacterizationTest` (session 2b) — plus the 11 package smoke tests,
which are not characterization at all.

**The numerator was inflated by one, and had been since session 9** (4 complete, reported as 5). Note
the direction: it overstated progress on **the single criterion blocking the exit gate**. It does not
move the verdict — criterion 2 is unmet at 5 or 6 — but a criterion-2 number that drifts upward is
how a gate eventually gets scored met on a proxy. `CONTEXT.md`, `TODO.md` and `WP0A_EXIT_GATE.md`
still carry 6.

---

## A — Claude Code: generation, then verification

Two separate sessions. Row A2 generates and stops; row A4 runs after the Cowork fill pass.

```
ICARE BUILD — CLAUDE CODE. Paste unchanged. Verify preconditions, then do exactly
one of the two jobs below. Do not do both in one session.

=== ORIENT ===
git status --short --branch → git log -1 → CONTEXT.md → TODO.md → last 5 entries of
MEMORY.md → CLAUDE.md → SCHEMA_SPEC.md Part 3 (§25, ~1,100 lines — read it whole,
it is the spec you are executing). Do NOT read PRD.md or build.md whole.

=== REFUSE IF ANY OF THESE IS UNMET — report which, and stop ===
1. WP 0A exit gate is 7/7 (WP0A_EXIT_GATE.md).
2. SCHEMA_SPEC.md §25.7 decisions D7, D8, D9, D10 are answered in writing.
   D7 and D8 ADD COLUMNS. Generating before they land means regenerating.
3. The owner has said iCare is in scope now (D11 / the R1a-R1b split).
Refusing is the correct outcome if any is open. Do not "make a start".

=== JOB 1 — GENERATION (only if 0 ifgf_ migrations exist) ===
Run tools/GENERATION_MANIFEST.md in full — all 21 tables, not an iCare subset.
Empty tables are free; a second migration round is not.

Then the iCare additions, which the manifest does NOT yet cover. Fold these in as
Section 7 while you are in that file:

  php artisan make:migration add_group_id_to_ifgf_event_definitions_table -n
  php artisan make:migration add_note_to_ifgf_attendance_details_table -n
  php artisan make:migration add_visitor_columns_to_ifgf_attendance_details_table -n
  php artisan make:controller Ifgf/IcareAttendanceController -n
  php artisan make:request SaveIcareRosterRequest -n
  php artisan make:request AddIcareVisitorRequest -n
  php artisan make:request QuickAddVisitorRequest -n
  php artisan make:request UploadIcarePhotoRequest -n
  php artisan make:middleware EnsureIcareLeader -n
  php artisan make:test Ifgf/IcareDateResolutionTest --unit -n
  php artisan make:test Ifgf/IcareRosterTest -n
  php artisan make:test Ifgf/IcareAttendanceWriteTest -n
  php artisan make:test Ifgf/IcareVisitorTest -n
  php artisan make:test Ifgf/IcareAuthorizationTest -n
  php artisan make:test Ifgf/IcareFinalizationTest -n
  php artisan make:test Ifgf/IcareConcurrencyTest -n

IcareAttendanceService and IcareAuthorizer have no artisan generator — create the two
empty class files by hand in the package's src/Services/, matching §25.1 and §25.4
signatures. Bodies stay EMPTY. Cowork fills them.

Controller, requests and middleware move into the package per the manifest's Section 6
allow-list pattern. Tests stay in the root tests/ so CI picks them up. Requests are the
largest blast radius in the repo (85 upstream classes) — allow-list by BaseName, never
a wholesale move.

Then STOP and tell the owner generation is done so Cowork can start. Do not implement.

=== JOB 2 — VERIFICATION (only after Cowork reports the fill pass done) ===
Pre-flight both mandatory steps first:
  - MySQL: check for an EXISTING listener on 3306 before starting one. A second
    mysqld fails with "InnoDB: ibdata1 must be writable" — that is a lock conflict,
    not a broken install (measured 2026-08-21).
  - php artisan cache:clear --env=testing. Before EVERY measurement, not once.

  php artisan migrate:fresh --env=testing     (print and assert environment, driver,
                                               host and database name FIRST — build.md L517)
  SHOW CREATE TABLE on ifgf_event_definitions and ifgf_attendance_details.
    Rule 0 is verified here or not at all: group_id and visitor_from_group_id must be
    INT UNSIGNED (upstream groups.id), event_attendee_id BIGINT UNSIGNED.
    unsignedInteger where unsignedBigInteger belonged migrates CLEANLY and truncates
    silently at 4.29 billion. Read the actual output; do not infer from the source.
  php artisan test --filter=Icare      then the full suite.

Report counts. Fix what fails. These are Phase 1A/R1b feature tests — they do NOT
count toward the 80-120 WP 0A characterization target and must not be reported as if
they did.

=== THE FOUR THINGS MOST LIKELY TO GO WRONG, ALL MEASURED ===
1. Gate::before in AuthServiceProvider grants usergroup_id == 3 EVERY ability,
   policies included. iCare authorization must never touch the Gate — not can(),
   not authorize(), not the can: middleware. §25.4 has the corrected design and the
   three rejected alternatives. This is the failure that passes every grant test.
2. Route-file ordering silently shadows routes (api → web → admin; suite 6 found a
   whole permission surface dead this way). Assert /icare/* resolves to the package
   controller.
3. AttendanceDetail::$fillable = [] is deliberate. If a body works around the
   MassAssignmentException by assigning properties directly, the sole-write-path
   guarantee is gone with nothing failing to say so. Check this by reading, not by
   running the suite.
4. Any test rendering an admin view must seed settings.* first, or every admin page
   500s on "htmlspecialchars(): ... UrlGenerator given" and looks like an app bug.

=== ESCALATE, DO NOT DECIDE ===
Anything §25 marks 🔴 that the code contradicts. Any D7-D11 answer that turns out
not to fit. Any change to an upstream-owned file without an UPSTREAM.md entry AND a
characterization test.
```

---

## B — Cowork: the fill pass

```
ICARE FILL PASS — COWORK. Paste unchanged.

=== ORIENT ===
CLAUDE.md, CONTEXT.md, TODO.md, last 5 entries of MEMORY.md, then SCHEMA_SPEC.md
Part 3 (§25) IN FULL. §25 is the spec; you are transcribing it, not redesigning it.
Do not read PRD.md, build.md or hosting.md whole.

YOU ARE COWORK: you WRITE .php files, you never RUN anything. No composer, no
artisan, no tests, no migrate. Hand every command to the owner. ("No PHP" in the
standing prompt means no execution — writing PHP source IS the fill pass.)

Claude Code is working the same repository in parallel. Never touch tests/ that it
has open, and nothing TODO.md shows in progress.

=== REFUSE IF ===
The generated files do not exist yet, or D7-D11 are unanswered. Writing bodies for
files that do not exist guarantees a conflict; writing against unanswered column
decisions guarantees a rewrite.

=== ORDER — dependency-correct, report after each batch ===
1. The three iCare migrations (A4 group_id, A6 note, §8c visitor columns).
   RULE 0: FK to upstream groups/users/events → unsignedInteger + explicit foreign().
   foreignId() creates BIGINT and fails errno 150 against an INT parent.
2. Model deltas: EventDefinition::group(), Group ↔ GroupProfile,
   AttendanceDetail::visitorFromGroup(). casts() method, never $casts, NEVER $dates
   (REG-001: $dates has been inert since Laravel 10 and left 21 columns silently
   returning strings).
3. RecordAttendance DTO fields FIRST (§25.0 A5: isVisitor, visitorFromGroupId, note),
   then AttendanceRecorder's paired validation rule, then IcareAttendanceService.
4. IcareAuthorizer + EnsureIcareLeader middleware (§25.4 as CORRECTED — read the
   correction block, not just the method list).
5. IcareAttendanceController + the four form requests + route registration.
6. Test bodies, in this order: IcareDateResolutionTest FIRST — it is a unit test,
   needs no database, and is the one file whose absence lets a seven-day date error
   ship. Then roster, write, visitor, authorization, finalization, concurrency.
7. attachPhoto() LAST, and only after a private disk exists. There is no private
   disk in this application today; all four are public and uploads is rooted at
   public_path(). A group selfie there is CARD-003 with ten identifiable members.

=== THE FIVE RULES THAT ARE NOT NEGOTIABLE ===
- Every attendance write goes through AttendanceRecorder. This service never touches
  event_attendees or ifgf_attendance_details directly. $fillable = [] is the guard —
  do not work around the MassAssignmentException, and do not populate that array.
- A MISSING attendance row is NOT absence. finalize() on a closed roster is the only
  thing in Release 1 permitted to turn one into the other, and it snapshots
  rosterAsOf(occurrence local date), never the roster today.
- Derive every calendar day in the BRANCH timezone. Never config('app.timezone') —
  it is UTC by decision, so it is a wrong answer wearing a default's clothes. Never
  date(), strtotime(), Carbon::today() or a bare now(). UTC at rest is settled
  (UP-009); do not reopen it.
- Never the Gate for iCare authorization. Gate::before grants usergroup 3 everything.
- Adding a visitor creates NO group membership. With the one-active-primary partial
  unique index, writing one could silently END their real membership.

=== STANDING ===
Never commit; present files, the owner commits. Never write real member data into any
file. Stop at 120k and hand off: MEMORY.md → TODO.md → CONTEXT.md. Escalate
legacy-vs-PRD conflicts rather than deciding (§13.1.20).
```

---

## What this actually costs

| Step | Who | Sessions |
|---|---|---|
| WP 0A criterion 2 — suites 7, 8, 10 | Claude Code | 1–2 |
| WP 0A criteria 4 + 7 — one reading session | **you** | — |
| D7–D11 | **you** | — |
| WP 0C generation, all 21 tables + iCare | Claude Code | 1 |
| Fill pass — the whole package, iCare included | Cowork | 2–3 |
| Verification, migrate:fresh + suites | Claude Code | 1 |

iCare is a **slice of WP 0C plus its own fill batch**, not a standalone feature build. That is why
these prompts are specializations of the campaign router rather than a third campaign: the tables,
the recorder, the policies and the roster service all get built once, for everything, and iCare is
the first consumer.
