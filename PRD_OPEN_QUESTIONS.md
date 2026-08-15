# PRD §13.2 — Open Question Tracker

**Purpose:** convert the sixteen open questions in `PRD.md` §13.2 (L1713–1730) from a flat list
into a scheduled, decision-ready set, so each is answered shortly before the work package that
needs it rather than all at once now.

**Status:** 3 of 16 resolved, plus 2 governing decisions recorded · **Updated** 2026-08-11

`PRD.md` is the committed approved baseline at `9120af9`. Answering a question here does **not**
silently amend it — each resolution produces a specific PRD edit, reviewed and committed as a
documentation change. `build.md` OPERATING CONTRACT 1 and 6.

---

## Resolved PRD consistency defect — home branch

**`PRD.md` contradicts itself on home branch.**

| Line | Says |
|---|---|
| L402 | `Branch "0..1" --> "many" UserProfile : optional home branch` — **optional** |
| L973 | "The initial minimum is full name, birthday, one contact method, **home branch**, privacy consent, and follow-up preference" — **required** |

Resolved 2026-08-09. The schema remains nullable; self-service registration and activation require
a branch; imported and unclaimed records may remain branch-neutral and a source row without a
branch becomes an import exception. PRD FR-02 and Section 13.1.19 now agree with the domain model.

---

## Schedule — answer each shortly before its work package

| # | Question | Blocks | When to answer |
|---|---|---|---|
| ~~3~~ | ~~Home branch mandatory or optional?~~ | — | **Resolved** — nullable schema, required at registration and activation |
| ~~2~~ | ~~Which source wins on disagreement?~~ | — | **Resolved** — escalate every conflict |
| 15 | Who may upload/replace a profile image? | Phase 1A.5 | Before Phase 1A |
| 14 | Which provider/region for member images? | Phase 1A.5 | Before Phase 1A |
| 8 | Which church-information pages at launch? | Phase 1A.4 | Before Phase 1A |
| 4 | CGSL stage completion thresholds? | Phase 1C.3 | Before Phase 1C |
| 1 | Who owns the shared Google Calendar? | Phase 1D.1 | Before Phase 1D |
| 5 | Zoom CSV only, or API in phase 1? | Phase 1D.2 | Before Phase 1D |
| 10 | Which Git account owns `deploy` protection? | Phase 1E.1 | Before Phase 1E |
| 11 | VPS release or container deployment? | Phase 1E.1 | Before Phase 1E |
| 6 | Which Indonesian provider and region? | Phase 1E.2 | Before Phase 1E |
| 7 | Who owns patching, monitoring, restore drills? | Phase 1E.2 | Before Phase 1E |
| 12 | Fund RGB+IR/depth hardware if RGB fails gates? | Phase 2A | Before Phase 2 |
| 13 | Approved max false-match rate and margin? | Phase 2A | Before Phase 2 |
| 16 | Any under-18 biometric enrollment? | Phase 2A | Before Phase 2 |
| ~~9~~ | ~~Contribute generic fixes upstream?~~ | — | **Resolved** |

**Scheduling note:** Q14 (storage provider and region for member images) reads like a Phase 1E
hosting question but is actually needed at **Phase 1A.5**, because private S3-compatible storage
is where profile media lands. It is the earliest infrastructure decision in the project. Do not
let it sit in the 1E pile.

---

## Escalations — legacy vs PRD conflicts, raised 2026-08-09

Under the authority hierarchy below, these disagreements go to the owner rather than being
auto-resolved. Full detail in `GAS_INVENTORY.md` §3.

### C1 — The legacy member QR leaks contact data. **Decide before Phase 1A.4.**

The QR encodes a Google Forms prefilled URL containing the member's **email, WhatsApp number, full
name and iCare group in plaintext**. Anyone who photographs a member's QR reads all four. It is
also **not rotatable** — the URL is a pure function of the member's own data, so there is no
revocation path short of changing their email or phone.

Contradicts `build.md` SECURITY 8 ("never reveal contact or iCare data through QR payloads"),
TECHNICAL BASELINE 7, and PRD FR-02's "opaque, rotatable member QR".

**Recommendation: the PRD wins.** This is a data-exposure defect, not a behaviour worth preserving,
and the PRD already specifies the fix independently.

**Consequence to plan for:** every existing member QR must be reissued at cutover. Previously
printed or saved codes stop working by design. This is the most visible user-facing change in the
migration and needs a member communication plan — Phase 1E cutover, not an afterthought.

### C2 — Attendance branch is member-selected and almost never provided

`Lokasi` is populated on 40 of 3,227 scans (1.2%). Recommendation: the PRD's leader-scoped
attendance model resolves this structurally — the occurrence a leader is assigned to determines the
branch, so the member is never asked.

### C3 — Legacy phone normalisation is broken; duplicate detection depends on it

`cleanPhoneNumber` has a no-op branch for leading-zero local numbers, so one person's number in
three formats is three keys. Legacy duplicate detection matches on email **or** phone, so it has
been silently missing phone duplicates. WP 0C must normalise to E.164 and expect duplicates the
legacy system never caught. Email matching is sound.

---

## Authority hierarchy — owner decision, 2026-08-09

This was not one of the sixteen questions, but it governs how several of them resolve.

**The legacy spreadsheet and Google Apps Script are the initial authoritative reference for
required data and main functions.** They describe the program that is actually running today.
Upstream ChurchCMS supplies *implementation* — models, tables, controllers, authentication — but
it does **not** define requirements.

| Rank | Source | Authority over |
|---|---|---|
| 1 | Legacy workbook + Google Apps Script | **What the system must do, and what data it must hold** |
| 2 | `PRD.md` | The agreed articulation of 1, plus everything new (roles, privacy, recognition, ticketing) |
| 3 | `upstream/main` ChurchCMS | Implementation substrate and compatibility anchors only |

**When upstream behaviour and IFGF requirements disagree, escalate to the owner. Do not
auto-resolve in either direction.** Neither "upstream already does it this way" nor "the PRD says
otherwise" settles it by itself.

### Consequence — a source the sessions cannot currently read

`build.md` SOURCE EVIDENCE names these paths:

```
D:/Users/Ian Joseph/Documents/GitHub/church-member-management     (Google Apps Script)
D:/Users/Ian Joseph/Downloads/Jemaat & Absensi (2).xlsx           (historical workbook)
```

Neither is reachable from a session working in `church-cms-laravel` alone. Since they now outrank
the PRD on requirements, **any session doing requirements work needs access to them** — and
`build.md` is explicit that unavailable sources must not have their behaviour invented.

**Action:** before Session 6 (WP 0C member extraction), either open the sessions with both repos
in scope, or produce a committed, anonymized extract of the GAS logic and workbook column
inventory inside this repository so it is readable without the originals. The extract must contain
**no real member data** — structure and logic only.

---

## Upstream policy — owner decision, 2026-08-09

**`upstream/main` is an active feature source, not a frozen base.** The intent is to keep
absorbing functionality upstream builds beyond IFGF's own work.

This does not change the current pin — freeze at `d12c110` until the WP 0A exit gate, then sync —
but it changes how much the surrounding discipline is worth:

| Practice | Why it matters more now |
|---|---|
| Keep IFGF behaviour in `custompackages/ifgf/church-operations` | Every upstream-owned file edited is a merge conflict on every future sync, forever |
| Minimise `UPSTREAM.md` entries, prefer sidecars | Same reason — each `carry` entry is a patch replayed at each sync |
| CI merge rehearsal (WP 0A item 13) | Becomes a **recurring safety net**, not a one-off gate item |
| `contrib/*` upstream contribution | Retires downstream patches permanently — the only way an entry stops costing |
| `origin/main` stays a clean mirror of `upstream/main` | Makes each sync a fast-forward rather than a reconciliation |

Practical note: the fork is currently **8 commits behind**. That gap grows while WP 0A runs, and
the first sync after the gate will be the largest one. Consider a mid-WP-0A read-only merge
rehearsal — no push, no merge — purely to measure whether the gap is diverging faster than
expected.

*PRD edit required:* add to §13.1 as a default decision.

---

## Resolved

### Q3 — Is a home branch mandatory? → **Nullable, owner-approved 2026-08-09**

Nullable column, required at activation, malformed row becomes an import exception. Confirmed by
the workbook: branch is 99.5% filled with exactly 2 values, and the one gap is a junk row.

*PRD edit required:* reconcile **L402** (`Branch "0..1"`, optional) and **L973** (required-field
minimum). L402 is correct; amend L973 to note branch is required for self-service registration and
at activation, not for imported or unclaimed records.

### Q-new — Laravel target → **latest stable, owner-confirmed 2026-08-09**

Laravel 13 (13.24.0 verified). Recheck the release table when WP 0B actually begins, per
`build.md` TECHNICAL BASELINE 2. Laravel 10 lost security support in **February 2025**, so this is
a production blocker rather than a preference.

### Q2 — Which source is authoritative when the workbook and the IFGF export disagree?

**Answered 2026-08-09: escalate every conflict to the owner. No automatic winner.**

The workbook and Google Apps Script are the initial reference for required data and functions.
Where a specific record disagrees with the IFGF export, the owner decides case by case rather than
a blanket precedence rule.

This matches the recommendation made before the answer: build WP 0C's pipeline for manual
resolution, let the dry-run report actual conflict volume, and adopt an automatic rule later
**only if** the volume proves unmanageable — and only as an explicit, separately approved PRD
change.

*Implementation consequence:* `ifgf_import_conflicts` is a first-class workflow, not an exception
log. It needs an admin review UI, not just a table. WP 0C item 14's all-row reconciliation is the
gate: it must account for every non-empty source row as insert, update, merge, skip-with-reason,
conflict, or error before any member is committed.

*PRD edit required:* move Q2 into §13.1, and state the escalation rule in §9 Migration Plan.

### Q9 — May generic fixes be contributed publicly to ChurchCMS?

**Answered 2026-08-08: not yet.** Fix locally, record each as a maintained downstream patch in
`UPSTREAM.md` with disposition `carry`, and revisit nearer the WP 0A exit gate. Four entries
already qualify — UP-001 through UP-004 — all generic, none IFGF-specific.

*PRD edit required:* move to §13.1 as a default decision, noting it is revisitable.

---

## Awaiting decision — Phase 1A

### Q15 — Who may upload or replace a profile image at launch?

PRD default is **admin-only** (L1729), and Phase 1A.5 is scoped around it. Confirming the default
is the no-change path. Member self-service adds a moderation queue, a pending-image state and
notification plumbing, plus the impersonation risk the PRD already flags.

A third option worth naming: **no profile images in the MVP at all.** That removes quarantine,
sanitization, variants, retention and consent work from Phase 1A.5 — the single largest scope
reduction available in Phase 1A. Phase 2 recognition would need it reinstated, so it trades
Phase 1 speed for Phase 2 rework.

### Q14 — Which provider, region, replication and backup region for member images?

Taiwanese member images with Indonesian operations — this is a cross-border personal-data flow
under both Indonesia Law No. 27/2022 and Taiwan's PDPA, and `build.md` WP 0D exists to review
exactly this. **The provider decision and the privacy review are the same decision.** Do not pick
a bucket region before WP 0D's data-flow inventory.

### Q8 — Which church-information pages at launch?

No list exists anywhere in the PRD, so Phase 1A.4 currently has no content scope. Options: a
minimal set (service times, contact); reuse whatever upstream already publishes — sermons,
bulletins, gallery, events — behind authentication; or defer entirely so the portal ships with
own-profile and own-QR only.

Reusing upstream's pages inherits upstream's page behaviour **and its bugs**, and those pages are
currently unauthenticated public routes — moving them behind auth is a real change requiring
characterization first.

---

## Later phases — no action yet

Q4 (CGSL thresholds), Q1 (Calendar ownership), Q5 (Zoom CSV vs API), Q10/Q11 (release topology
and mechanics), Q6/Q7 (provider, region, operational ownership), Q12/Q13/Q16 (recognition hardware,
accuracy targets, under-18 eligibility).

Q16's PRD default is **no** — under-18 members are not eligible for biometric enrollment. Q12 and
Q13 cannot be answered before Phase 2A produces measurements; they are gates, not choices.

---

## How to close a question

1. Record the decision and its rationale here.
2. Make the corresponding `PRD.md` edit — move the item into §13.1 as a default decision, and fix
   any section the answer contradicts.
3. Commit as a documentation change with the PRD baseline SHA updated in `CONTEXT.md`.
4. Note it in `MEMORY.md` so later sessions do not reopen a settled question.
