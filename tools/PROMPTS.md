# Prompt library — Release 1 to production

Paste-ready prompts for Claude Code, in execution order. One per work package.

Each is deliberately short. The context lives in `CLAUDE.md`, `CONTEXT.md`, `TODO.md` and
`MEMORY.md` — that is what the memory system is for. What each prompt *does* carry is the
**findings already paid for**, so no session rediscovers them at token cost.

| Order | Prompt | Package | Est. sessions |
|---|---|---|---:|
| — | *(done — `tools/UPGRADE_PROMPT.md`)* | Machine + WP 0B | ✅ 2026-08-10 |
| **0** | **P0 Close WP 0A** | WP 0A items 6, 11, 14 + gate | **6–7** |
| 1 | P1 Schema foundation | WP 0C | 10–13 |
| 2 | P2 Privacy gates | WP 0D | 3–4 |
| 3 | P3 Member registry + QR | Phase 1A | 10–12 |
| 4 | P4 Attendance | Phase 1B | 7–9 |
| 5 | P5 iCare | Phase 1C.1–1C.2 | 5–7 |
| 6 | P6 Calendar, reports, import | Phase 1D | 8–10 |
| 7 | P7 Production | Phase 1E | 8–11 |

**Shared preamble** — prepend to every prompt below:

```
Read CLAUDE.md, CONTEXT.md, TODO.md and the last 5 entries of MEMORY.md.
Read only the build.md and PRD.md line ranges this work package needs
(EXECUTION_PLAN.md Appendix A). Do not read PRD.md, build.md or hosting.md whole.
State the work package, step and exit gate, then ask me to confirm before the first
code change. Use artisan generators rather than hand-writing files. Never commit
without showing staged files and message. Stop at 120k and hand off per CLAUDE.md.
```

---

## P0 — Close Work Package 0A

**Run this next.** Nothing else can safely start until it finishes.

```
Execute Work Package 0A closure only. build.md L202-228. Do NOT start WP 0C.

State check first: WP 0B is complete (Laravel 13.24.0, PHP 8.4.24, MySQL 8.4 LTS).
See PROJECT_STATUS.md. Three items remain: 6 (characterization), 11 (package),
14 (smoke tests), plus the exit gate.

STEP 1 — commit the outstanding work.
~13 modified/staged files including the timezone fix (.env.example,
config/database.php, tests/Feature/Attendance/TimezoneCharacterizationTest.php)
and untracked REVIEW.md, TESTING_PLAN.md, PROJECT_STATUS.md, tools/*.md.
Group into logical commits. Show staged files and message before each.
Do NOT commit .env. Confirm the timezone UPSTREAM.md entry exists — config/database.php
and .env.example are upstream-owned and CLAUDE.md requires a ledger entry.

STEP 2 — characterization tests. THE PRIORITY. 3-4 sessions.
Currently 2 test files. Target 60-90 tests across seven suites; TESTING_PLAN.md
Part 1 lists them with what each asserts. Order: roles/permissions and attendance
FIRST — the 33-file authorization surface WP 0C must replace, and the 60% of FR-04
Phase 1B extends.

Capture what IS, not what should be. If a behaviour looks wrong, assert the wrong
behaviour and note it. Fixing and characterizing in one pass destroys the baseline.

Two assertions to write explicitly:
  - A MISSING attendance row is NOT absence. EventAttendee is presence-only today;
    write this before the status column exists so the migration cannot quietly
    change what "no row" means.
  - A leader with NO assignment is DENIED. Assert the denial directly. Hidden
    navigation is not authorization (build.md SECURITY 11).

Use php artisan make:test. Run the suite and record pass/fail/skip in MEMORY.md.
THIS IS THE BASELINE.

STEP 3 — scaffold custompackages/ifgf/church-operations. WP 0A item 11.
Composer path repository, PSR-4, extra.laravel.providers auto-discovery, committed
lockfile resolution, package test runner path. NO product behaviour.
Record root composer.json and composer.lock as approved upstream-owned touchpoints.
Generate into app/ then git mv and fix the namespace with sed — CLAUDE.md.

STEP 4 — provider smoke tests from a clean checkout. WP 0A item 14.
Package routes, migrations, views, translations, commands, policies load.

STEP 5 — merge contrib/laravel-supported-platform into ifgf/main.
ONLY after step 2 passes. This is the one action here that is hard to undo.
Re-run the full suite after merging.

STEP 6 — upstream merge rehearsal, read-only. No push, no mutation of protected
branches. The owner wants upstream's additional features, so this is recurring
infrastructure, not a one-off.

STEP 7 — WP 0A exit gate review. Report which build.md L226-228 criteria are met
and which are not. Do not mark the gate passed if any criterion fails.

Then STOP. WP 0C needs its own approval (tools/PROMPTS.md P1).
```

---

## P1 — WP 0C schema and migration foundation

```
Execute Work Package 0C only. build.md L255-285.

Findings already established — do not re-derive:
- 93 migrations, 91 creates, 91 drops, ZERO alters. Squashed set, 90 dated 2024_01_01.
  The migrations do NOT describe how production reached its current shape, so a fresh
  database and an anonymized legacy snapshot may diverge. Budget for that.
- event_attendees.user_id is ON DELETE CASCADE to users (2024_01_01_000030 line 20).
  users already has softDeletes(), so the normal path never fires it — only
  forceDelete(), raw SQL, or a future erasure flow does. WP 0C item 4 and WP 0D's
  erasure design are therefore the SAME problem. Solve them together.
  Line 18 is a second exposure: deleting an event cascades attendance regardless.
- userprofiles.user_id has a foreign key but NO unique constraint. Item 3.
- role_user has composite primary (user_id, role_id, user_type) but item 8 needs
  unique on user_id + user_type, and there is NO foreign key on user_id.
- EventAttendanceSession keys on attendance_date — one session per event per day,
  contradicting FR-03.2. This is item 5, confirmed real.
- EventAttendee is presence-only. No status column. After the additive migration a
  MISSING ROW must not be read as absence — write a test that asserts this.
- usergroup_id appears in 33 files. Item 9. This is the largest correctness risk in
  the project. Inventory all 33 before changing any.
- Only event_attendance_sessions and event_attendees carry pastoral history among the
  13 cascade migrations. Do NOT blanket-replace the other 11.

Home branch is nullable (PRD 13.1.19). Import conflicts are escalated, never
auto-resolved (13.1.21) — ifgf_import_conflicts is a first-class review workflow.

Scope: Release 1 only. Create ifgf_member_profiles, ifgf_branches, ifgf_event_types,
ifgf_event_definitions, ifgf_event_occurrences, ifgf_attendance_details,
ifgf_group_memberships, and the five import tables. Do NOT create CGSL, ministry or
registration tables — deferred to Release 2 (PRODUCTION_PATH.md).

Deliver the member dry-run reconciliation (items 13-14) accounting for every non-empty
source row. Do not commit imported members.
```

## P2 — WP 0D privacy gates

```
Execute Work Package 0D only. build.md L286-302. Read hosting.md whole — it is 5.4k
and this is the package that needs it.

Context:
- Taiwanese member images, Indonesian operations. Two regimes: Indonesia Law
  No. 27/2022 and Taiwan PDPA. Cross-border by construction.
- PRD 13.2 Q14 (storage provider, region, replication, backup region) is due HERE,
  not Phase 1E. Private S3-compatible storage is needed at Phase 1A.5.
- Secret inventory must include the legacy Google Apps Script config.js: registration
  and attendance form IDs and entry IDs, birthday Calendar ID, spreadsheet ID, admin
  email. These are live access identifiers and must never be committed.
- Erasure design must resolve the event_attendees CASCADE from WP 0C item 4.
- Record biometric processing as separately gated. Group-photo iCare attendance
  (ICARE_PHOTO_ATTENDANCE.md) is Phase 2B and needs its own consent type — distinct
  from profile-display consent.

Produce the data-flow inventory, consent versioning, and secret recovery
documentation. Do not load any real personal data.
```

## P3 — Phase 1A member registry, activation, portal, QR

```
Execute Phase 1A. build.md L303-330. PRD FR-01 and FR-02.

The QR design is settled — PRD FR-02.7 sub-items 1-6:
- Attendance credential only. Ticket-equivalent. Never an auth factor.
- Payload is ifgf_member_profiles.qr_token, char(32) unique, Str::random(32).
  Nothing else. No name, email, phone, iCare, branch, sequential ID, or URL.
- qr_version and qr_rotated_at. Rotation regenerates and bumps; old code dies
  immediately with no grace period.
- Sequential IDs prohibited. Signed/expiring URLs prohibited — printed cards outlive
  signatures.
- Printed card carries display name + short human-readable code for manual fallback.

Migration consequence: EVERY existing member QR is reissued at cutover. Old printed
codes stop working by design. Coordinate with the Phase 1E comms plan.

Findings:
- Workbook has 217 members, ~14 live columns of 33. Twelve are <1% filled (older form
  version) — treat as import-exception category, do not model as fields.
- Duplicate LINE ID / Line ID columns, case-differing, one dead.
- Profesi and Tingkat Pendidikan are free text with 7 values each including 'bisnis',
  'Toko', '倉管'. Normalise on import. NO MySQL enums (TECHNICAL BASELINE 6).
- Legacy phone normalisation is broken — cleanPhoneNumber has a no-op branch for
  leading zeros, so 0912/+886912/886912 are three keys. Legacy duplicate detection
  matched on email OR phone, so it has been missing phone duplicates for years.
  Normalise to E.164 with an explicit default region; expect duplicates never caught.
- Email matching is sound: trim() + toLowerCase().
- Legacy identity rule: addEditUrlSpreadsheet searched bottom-up for matching email,
  most recent row lacking an edit URL. 3 duplicate names exist. Reproduce or
  deliberately supersede.

Scope: Release 1. Merge UI deferred. Ask me Q8 (which church-information pages) and
Q15 (who uploads profile images) before building 1A.4 and 1A.5.
```

## P4 — Phase 1B events and attendance

```
Execute Phase 1B. build.md L331-357. PRD FR-03, FR-04.

IMPORTANT — roughly 60% of FR-04 already exists. Do not rebuild it. Extend it.

Existing: EventAttendanceSession (church_id, event_id, attendance_date, opened_by,
locked_at, locked_by), EventAttendee (session_id, church_id, event_id, user_id,
scanned_at, scanned_by), EventManager.
Api/AttendanceController: myEvents, openSession, scan, lock, sessionReport.
Admin/EventAttendanceController: sessions, openSession, showSession, lock, unlock,
export, manageManagers, storeManager, removeManager, checkin, searchMember,
markAttendee, removeAttendee.

Already correct and must be preserved: duplicate scan returns 409 with the existing
record (FR-04.7); locked session returns 403 (FR-04.8); manual search fallback
(FR-04.9); EventManager assignment scope (FR-04.11); church scoping on every path.

Four changes to the scan endpoint:
1. member_username -> qr_token. Removes the enumerable identifier.
2. session_id -> occurrence reference. Occurrence context is selected on the USHER'S
   phone, never in the member QR (PRD 13.1.22, FR-04.1).
3. Route writes through AttendanceRecorder, not directly to EventAttendee.
   The two call sites are Api/AttendanceController::scan() and
   Admin/EventAttendanceController::markAttendee().
4. avatar_url currently comes from Storage::disk('public') — contradicts invariant 30.
   Move to short-lived signed access.

Add status (present/absent/excused), participation_mode (onsite/online), and
capture_method. Write a test asserting a MISSING attendance row is not read as absence.

Scope: Release 1. No registration, capacity or waitlist — deferred to Release 2.
Events are weekly Sunday per branch; no RFC 5545 RRULE needed yet.
```

## P5 — Phase 1C.1 and 1C.2 iCare

```
Execute Phase 1C.1 and 1C.2 only. build.md L358-380. PRD FR-05.

Do NOT execute 1C.3 (CGSL) or 1C.4 (ministries) — Release 2.

Context: iCare small groups meet weekly on weekdays, each with a leader, attendance
per member. 22 groups across 217 members, so roughly 10 per group. The legacy
Absensi iCare sheet is EMPTY — the practice exists, the tooling never did. This is
new build with no history to migrate.

Build:
- iCare groups on ChurchCMS Group with type icare. Group, GroupCategory, GroupLink,
  GroupsController and GroupLinksController already exist — adapt them.
- Effective-dated membership in ifgf_group_memberships, with
  projected_group_link_id as the unique nullable projection to group_links.
- One active primary iCare, enforced with a MySQL-compatible generated nullable
  unique key plus transactional locking. Transfer locks the member row, closes the
  old membership, opens the new, and audits — one transaction.
- Mobile tick-list attendance through AttendanceRecorder. NO QR — with ~10 members and
  a leader present, a tick list beats scanning.
- PRE-TICK the roster from the previous occurrence's attendance. Small groups are
  highly regular, so last week is a strong prior. This may deliver most of the time
  saving the owner wants from photo detection, at zero privacy cost. Measure it.
- Private documentation-photo upload. NO processing, NO face detection, NO biometrics
  in Release 1. Storage only, so no consent gate is required.

Face detection is Phase 2B behind WP 0D. See ICARE_PHOTO_ATTENDANCE.md.
```

## P6 — Phase 1D calendar, reports, import

```
Execute Phase 1D subpackages 1D.1, 1D.3 (minimal), 1D.4 and 1D.5. build.md L381-405.
Skip 1D.2 (Worship Night / Zoom) — Release 2, online attendance was 3 rows of 3,227.

Calendar (1D.1) — the legacy design is BETTER than the PRD assumes. Carry it forward:
- Per-member recurring series ID stored on the member. In the workbook this is the
  only 100%-populated column, so adoption should be near-total.
- On edit: attempt setRecurrence() in place, fall back to delete-and-recreate.
- Patch details only when the date is unchanged.
- Clean up by name when no ID exists.
- syncAllBirthdays() full reconciliation.
This maps directly onto FR-08's same-calendar adoption, wrong-calendar recreation and
tombstones. Ask me Q1 (which account owns the calendar) before starting.
Event content is allowlisted: display name + birthday label. Never birth year, age,
contact, iCare or pastoral data.

Import (1D.4, 1D.5) — findings:
- Lokasi is populated on 40 of 3,227 scans (1.2%). Branch attribution for historical
  attendance CANNOT come from the scan log. Decide the attribution rule with me.
- The weekly grid is NOT derived from the scan log — 3,227 scans cannot fill ~40,000
  grid cells. The grid is substantially manual. The two sources WILL disagree; every
  conflict escalates (PRD 13.1.21).
- Grid layout: columns A-E are identity (No., Full name, Email, iCare, Kategori);
  newest week is LEFTMOST; each week is a merged Onsite/Online pair under a date
  header in row 6. 46 weeks per branch, 2025-09-28 to 2026-08-09.
- Absen-TPE_ZL is UNMAINTAINED — the weekly job only iterates Absen-TPE and Absen-ZL.
  It stalled at 2026-04-26. EXCLUDE it from import.
- Detailed grids start 2025-09-28. 2022-2024 exists only as year summaries — use for
  reconciliation counts, not row conversion (FR-12.5).

Reports (1D.3) minimal: roster export and attendance export with permission and audit
checks. No dashboards or inactive-risk in Release 1.
```

## P7 — Phase 1E production

```
Execute Phase 1E.1 only — repository readiness. build.md L406-437. Read hosting.md.

Do NOT provision infrastructure. Do NOT create or push deploy. 1E.2 staging and
1E.3 production release each require separate explicit authorization.

Deliver: deployment configuration, infrastructure templates, backup and restore
scripts, monitoring definitions, load-test scenarios for Sunday check-in, runbooks,
protected-environment rules, and a CI release workflow that can deploy only from
deploy.

Cutover plan must include:
- QR REISSUE. Every member QR changes; old printed codes stop working by design.
  This is the most visible user-facing change in the migration and needs a member
  communication plan, not a footnote.
- Parallel run: keep the Google Apps Script forms live, import into Laravel, run both
  for 2-4 Sundays, reconcile weekly, cut over only after a clean reconciliation with
  the sheet frozen read-only.
- Calendar compensation manifest that can delete newly created events or restore
  changed allowlisted content.

Ask me Q6, Q7, Q10, Q11 (provider, region, operational owner, VPS vs container)
before writing the deployment configuration.
```

---

## Notes

**Run them in order.** Each assumes the previous package's exit gate passed. `build.md` forbids
starting a package while an earlier gate is incomplete, and the token model makes it impractical
anyway.

**Each prompt is one work package, not one session.** A package spans several sessions; the
handoff protocol in `CLAUDE.md` carries state between them. Re-paste the same prompt to resume —
`CONTEXT.md` and `TODO.md` tell the new session where it actually is.

**Owner questions are embedded where they fall.** P3 needs Q8 and Q15, P6 needs Q1, P7 needs
Q6/Q7/Q10/Q11. `PRD_OPEN_QUESTIONS.md` tracks all of them.

**Release 2** — FR-06 CGSL, FR-07 ministries, FR-09 Worship Night and Zoom, FR-13 registration —
gets its own prompts once Release 1 is in production and the deferral has been validated against
real use.
