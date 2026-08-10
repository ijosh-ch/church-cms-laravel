# Production Path — proposed release plan

**Written:** 2026-08-09, after `PRD_REVIEW.md`, `WORKBOOK_INVENTORY.md` and `GAS_INVENTORY.md`
**Proposes:** a change to `EXECUTION_PLAN.md`'s delivery shape. Owner approval required.
**Does not change:** `PRD.md` scope. Everything below still gets built — the question is ordering.

---

## The problem with the current plan

`EXECUTION_PLAN.md` estimates **~100–125 sessions to complete Phase 0 + Phase 1**, and treats all
of Phase 1 as one release. That is a long time before the church gets anything, and it carries the
risk of every big-bang migration: the first real feedback arrives after all the work is done.

The three inventories changed what I know about scope. **Several PRD features have no counterpart
in the running system at all**, which means they cannot be "migrated" — they are net-new product,
and net-new product is the easiest thing to defer.

---

## Evidence for cutting scope

> **Correction, 2026-08-09.** FR-05 iCare was originally deferred here on the basis that the
> `Absensi iCare` sheet is empty. **That inference was wrong.** iCare groups meet weekly on
> weekdays with per-member attendance and a leader per group; the empty sheet means the
> *spreadsheet* was never the tool, not that the practice is absent. **FR-05 is in Release 1.**
> See `ICARE_PHOTO_ATTENDANCE.md`. Data absence is not capability absence — confirm with the owner
> before deferring anything else on this basis.

| PRD feature | What the legacy system actually contains |
|---|---|
| ~~FR-05 iCare~~ | ~~Empty sheet~~ → **in Release 1**, see correction above |
| **FR-09 online / Worship Night** | **3 rows of 3,227** marked online. Effectively unused. |
| **FR-06 CGSL** | Nothing. Not in the workbook, not in the script. |
| **FR-07 Ministries** | Nothing. |
| **FR-13 registration, capacity, waitlist** | Nothing. |
| **FR-14 biometric** | Nothing. Already Phase 2. |

Meanwhile the features the church uses **every week** are: member registration, the member QR,
Sunday attendance per branch, and birthday Calendar sync.

**Deferring the six features above removes no existing capability from the church.** They would
lose nothing on cutover day that they have today.

---

## Proposed releases

### Release 1 — "Replace the spreadsheet" · production target

Everything needed for the church to stop depending on the Google Sheet and Apps Script, and
nothing else.

| FR | Scope in R1 | Why it must be in R1 |
|---|---|---|
| FR-11 Roles + privacy | **Full** | Authorization correctness gates everything; cannot be retrofitted |
| FR-01 Member registry | Full, minus merge UI | 217 members must live somewhere |
| FR-02 Registration + **QR** | Full, with the opaque-token design | The weekly capture path |
| FR-03 Events | **Minimal** — weekly Sunday per branch, no RRULE | Sunday services only |
| FR-04 Attendance | Full, **~60% already exists** | The core weekly workflow |
| FR-08 Birthday Calendar | Full | Actively used; members notice immediately if lost |
| **FR-05 iCare** | **Groups, roster, leader scope, manual tick-list, private photo upload** | **Weekly weekday practice; owner-confirmed as live** |
| FR-10 Reports | **Minimal** — roster + attendance export | Replaces the summary sheets |
| FR-12 Migration | Full | 217 members, 3,227 scans, 46 weeks × 2 branches |

iCare in R1 is **manual attendance only** — no face detection. Photo upload is documentation-only
storage, so it needs no biometric consent and no privacy gate. The detection feature lands in
Phase 2B behind the existing FR-14 gates (`ICARE_PHOTO_ATTENDANCE.md`).

### Release 2 — "The PRD's pastoral layer"

FR-06 CGSL, FR-07 ministries, FR-09 Worship Night and Zoom, FR-13 registration.
All net-new, all buildable on R1's foundations, none blocking the cutover.

### Release 3 — Phase 2 recognition, then Phase 3 ticketing

Unchanged, still separately gated.

---

## Two things that cannot be cut

**Laravel 10 is out of security support.** Released February 2023; security fixes ended
**February 2025** under Laravel's 2-year policy. The baseline has been unsupported for roughly
eighteen months. `build.md` already states Laravel 10 is not an acceptable production target, and
`hosting.md` agrees. **Work Package 0B is a hard production blocker**, not an improvement — it
cannot be deferred to Release 2.

**FR-11 must come early, not late.** `usergroup_id` appears in **33 files**. Every one is an
authorization path, and there must be no window where a legacy route grants broader access than
the new three-role model. Retrofitting authorization after the member and attendance features are
live means auditing all of it twice. This is the single largest correctness risk in the project,
and the current plan has it buried inside WP 0C.

---

## Revised session estimate

| Stage | Current plan | R1 scope | Change |
|---|---:|---:|---|
| WP 0A remaining (items 4–15) | 11 | **8** | Characterize only what R1 touches |
| WP 0B platform upgrade | 14–16 | **14–16** | Unchanged — hard blocker |
| WP 0C schema + migration | 14–18 | **10–13** | No CGSL, ministry, registration tables |
| WP 0D privacy gates | 3–4 | **3–4** | Unchanged |
| Phase 1A member + QR | 12–15 | **10–12** | Merge UI deferred |
| Phase 1B attendance | 14–18 | **7–9** | FR-04 ~60% exists; no registration/waitlist |
| **Phase 1C.1–1C.2 iCare** | 10–13 | **5–7** | **Added back.** Groups, roster, manual tick-list, photo storage. No CGSL, no ministries, no face detection |
| Phase 1D calendar + reports + import | 13–16 | **8–10** | 1D.1, minimal 1D.3, 1D.4, 1D.5 only |
| Phase 1E production | 8–11 | **8–11** | Unchanged |
| **Total to production** | **~100–125** | **73–90** | **≈ 28% less** |

Release 2 then costs roughly 20–28 sessions on top, so the full PRD still lands around the
original estimate — it just lands *after* production instead of before it.

---

## De-risking: run both systems in parallel

The Apps Script does not need to be switched off on cutover day. `build.md` Phase 1E already
requires a parallel run and reconciliation. Concretely:

1. Keep the GAS registration and attendance forms live.
2. Import into Laravel; run both for 2–4 Sundays.
3. Reconcile weekly — Laravel's attendance counts against the sheet's.
4. Cut over only after a clean reconciliation, with the sheet frozen read-only.

This makes the riskiest part of the project reversible. It also gives a natural moment to reissue
member QRs (escalation C1) — new codes go out while the old path still works.

---

## What I need from you

1. **Approve or reject this release split.** If Release 1 is too small — for example if iCare
   attendance genuinely matters at launch even though it was never recorded — say so and I will
   move it back and re-estimate.
2. **Q3 home branch** — nullable is my recommendation; it is the last blocker on WP 0C schema work.
3. **C1 QR reissue** — confirm the PRD design wins over legacy, and that reissuing every member QR
   at cutover is acceptable.

---

## Immediate next actions, in order

| # | Action | Package |
|---|---|---|
| 1 | Commit Session 3's uncommitted work: `.graphifyignore`, `ROUTE_MIGRATION_INVENTORY.md`, `WORKBOOK_INVENTORY.md`, `GAS_INVENTORY.md`, `PRD_OPEN_QUESTIONS.md`, `PRD_REVIEW.md`, this file, and the memory-file updates | — |
| 2 | Extend `.graphifyignore` to `_ai/`, `_design_reference/`, `_code_reference/`, root planning `.md` — cheap, makes every later search better | 0A item 15 |
| 3 | Apply the PRD amendments in `PRD_REVIEW.md` §2.4 and Part 1.5, plus the Q3 resolution | PRD baseline |
| 4 | Finish WP 0A items 4b–14 at R1 scope | 0A |
| 5 | **WP 0B — the Laravel 13 upgrade.** Largest single block; unsupported framework is the biggest production risk carried today | 0B |

Item 5 is where the real work starts. Everything before it is preparation.
