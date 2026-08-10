# Production Path — proposed release plan

**Written:** 2026-08-09, after `PRD_REVIEW.md`, `WORKBOOK_INVENTORY.md` and `GAS_INVENTORY.md`
**Proposes:** a change to `EXECUTION_PLAN.md`'s delivery shape. Owner approval required.
**Does not change:** `PRD.md` scope. Everything below still gets built — the question is ordering.

---

## The problem with the current plan

*(Written 2026-08-09, before WP 0B landed. The 100–125 figure below is the original full-PRD
estimate; `EXECUTION_PLAN.md` §4.2 now carries **83–109 remaining**, and the R1-scoped table at the
bottom of this file is the one to plan against.)*

`EXECUTION_PLAN.md` estimated **~100–125 sessions to complete Phase 0 + Phase 1**, and treats all
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
cannot be deferred to Release 2. **Closed 2026-08-10:** the source is on Laravel 13.24.0 / PHP
8.4.24 / MySQL 8.4.11 LTS. The framework risk described in this section is retired; the
*verification* risk it created is not — see the estimate note below.

**FR-11 must come early, not late.** `usergroup_id` appears in **33 files**. Every one is an
authorization path, and there must be no window where a legacy route grants broader access than
the new three-role model. Retrofitting authorization after the member and attendance features are
live means auditing all of it twice. This is the single largest correctness risk in the project,
and the current plan has it buried inside WP 0C.

---

## Revised session estimate

**Revised 2026-08-10 — WP 0B is spent.** It cost one session, not 14–16, because characterization
(item 6) was skipped by owner directive. That work moved into the WP 0A row rather than vanishing,
and it is now the critical path. The Vite decision (item 8) is separately deferred and is broken
out below instead of being buried inside 0B.

| Stage | Current plan | R1 scope | Change |
|---|---:|---:|---|
| WP 0A remaining — package seam, characterization, provider smoke tests, merge rehearsal, exit gate | 11 | **6–9** | Characterize only what R1 touches. **Characterization is the whole gate now** |
| ~~WP 0B platform upgrade~~ | ~~14–16~~ | **0 — spent 2026-08-10** | Laravel 13.24.0 / PHP 8.4.24 / MySQL 8.4.11 landed in one session |
| Vite migration (was WP 0B item 8) | — | **4–6** | Deferred by approval; broken out so it is not silently dropped |
| WP 0C schema + migration | 14–18 | **10–13** | No CGSL, ministry, registration tables |
| WP 0D privacy gates | 3–4 | **3–4** | Unchanged |
| Phase 1A member + QR | 12–15 | **10–12** | Merge UI deferred |
| Phase 1B attendance | 14–18 | **7–9** | FR-04 ~60% exists; no registration/waitlist |
| **Phase 1C.1–1C.2 iCare** | 10–13 | **5–7** | **Added back.** Groups, roster, manual tick-list, photo storage. No CGSL, no ministries, no face detection |
| Phase 1D calendar + reports + import | 13–16 | **8–10** | 1D.1, minimal 1D.3, 1D.4, 1D.5 only |
| Phase 1E production | 8–11 | **8–11** | Unchanged |
| **Total to production** | **~100–125** | **55–70** | **≈ 45% less than the original plan** |

Release 2 then costs roughly 20–28 sessions on top, so the full PRD still lands around the
original estimate — it just lands *after* production instead of before it.

**Read the 73–90 → 55–70 drop carefully.** It is almost entirely WP 0B coming in under estimate,
and WP 0B came in under estimate partly because it shipped without its safety net. The remaining
number assumes the characterization debt is repaid in the WP 0A row. If it is deferred again, the
total does not shrink further — it moves into Phase 1 as rework at a worse exchange rate.

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

*(Rewritten 2026-08-10. Items 1–3 and 5 of the original list are done; the order below is what
actually remains.)*

| # | Action | Package |
|---|---|---|
| 1 | **Characterization tests for the Release 1 surface** — auth, roles and direct permissions, member profile, member QR / membership card, attendance session open/scan/lock/unlock, group access, exports. Nothing else should start first: it is the only thing that can tell you whether the Laravel 13 upgrade preserved behaviour | 0A item 6 / gate 5 |
| 2 | Fix the 8-minute suite (`schema:dump`, needs `mysqldump` on PATH) **before** writing those tests, or every run costs hours | 0A item 5 |
| 3 | Scaffold `custompackages/ifgf/church-operations` — nothing IFGF-specific can legally land until it exists | 0A item 11 |
| 4 | Provider smoke tests from a clean checkout; then the upstream merge rehearsal, which has never run | 0A items 13, 14 |
| 5 | Formally review and close the WP 0A / WP 0B exit gate; merge `contrib/laravel-supported-platform` into `ifgf/main`; push (nothing is pushed) | 0A gate |
| 6 | Resolve `imagick` — `/` returns 500 without it. Pre-existing, but it blocks the server redeploy | — |
| 7 | Then WP 0C (`tools/PROMPTS.md` P1) | 0C |

Item 5 is where the real work starts. Everything before it is preparation.
