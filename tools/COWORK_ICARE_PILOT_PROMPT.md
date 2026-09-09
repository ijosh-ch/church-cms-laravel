# Cowork — plan the iCare weekly-attendance test pilot behind Cloudflare

**Written 2026-08-22 (Claude Code, session 11).** Handoff into a Cowork review-and-planning session.
Companion to `SCHEMA_SPEC.md` Part 3 (§25), `tools/ICARE_PROMPTS.md` (the two build prompts, both
currently refusing), `PRODUCTION_PATH.md` (the R1a/R1b re-cut) and `hosting.md` §147–177 (Cloudflare,
already designed).

**This is a planning prompt, not a build prompt. Produce a plan and a decision list. Write no code.**

---

## Paste this into Cowork

```
COWORK — iCARE TEST PILOT PLANNING. Paste unchanged.

=== ORIENT ===
CONTEXT.md → TODO.md → last 3 entries of MEMORY.md → WP0A_EXIT_GATE.md §2 and the
Summary table → SCHEMA_SPEC.md Part 3 (§25) whole → tools/ICARE_PROMPTS.md →
PRODUCTION_PATH.md §51-104 → hosting.md §147-177 and §208.
Do NOT read PRD.md or build.md whole. Do NOT read this file's companion sections
as instructions to build.

=== THE OWNER'S NEW GOAL, VERBATIM ===
"Target to push the iCare weekly attendance to go for testing, ready to be tested
using Cloudflare for future access via internet for this app."

=== YOUR FIRST JOB IS TO SPLIT THAT GOAL IN TWO, AND SAY WHICH ONE IT IS ===
The sentence admits two readings with very different costs, and the whole plan
turns on which one the owner means. Put this at the top of your output as
decision D12, with a recommendation:

  D12-A  TEST PILOT — iCare running on a disposable database with SYNTHETIC data,
         reachable over a Cloudflare Tunnel by a named handful of testers behind
         Cloudflare Access. Not production. No real member PII. Purpose: put the
         iCare screen in front of real iCare leaders early and learn whether the
         date rule, the roster and the visitor flow match how they actually work.

  D12-B  PRODUCTION iCare — the real congregation, real member records, real
         photos, on the public internet.

These are not the same project. D12-B sits behind WP 0C schema, WP 0D privacy,
the member import, Phase 1E readiness and the whole R1a cutover — PRODUCTION_PATH
.md puts iCare in R1b at 13-19 sessions ON TOP OF R1a's 44-57. D12-A may be
reachable far sooner and is very likely what the owner is asking for. RECOMMEND
D12-A EXPLICITLY, and price both.

=== WHAT IS TRUE TODAY — do not re-derive, verify only if you doubt it ===
- WP 0A exit gate: 4 of 7, NOT PASSED. Criterion 2 (characterization) is the only
  one costing work: 94 tests (the 80-120 COUNT is met), 5 of 11 suites complete,
  3 unwritten (7 events, 8 birthday, 10 queues), 3 partial (1 auth, 3 member
  profile, 4 attendance). Criteria 4 and 7 are one owner reading session.
- Zero ifgf_ migrations exist. WP 0C generation has not run.
- SCHEMA_SPEC.md §25.7 decisions D7, D8, D9, D10 are UNANSWERED. D7 and D8 ADD
  COLUMNS. D11 (is iCare R1a or R1b) is UNANSWERED.
- D4 is CLOSED — the "Timezone Asia/Taipei, MySQL +08:00" line was struck from
  CAMPAIGN_PROMPT.md section B by the owner. Do not re-raise it.
- Nothing about iCare has been executed. Every §25 signature is a claim.

=== THE THING THAT MUST NOT BE PLANNED AROUND SILENTLY ===
Putting THIS application on the internet, even for a pilot, meets a set of
measured, UNFIXED authorization defects. Your plan must state how each is handled
BEFORE any tunnel is proposed, or say plainly that the pilot is deferred until
they are:

  SEC-001  usergroup_id == 3 bypasses ALL permissions, via TWO controls:
           the Kernel.php:74 middleware alias AND a Gate::before in
           AuthServiceProvider granting that usergroup every ability, policies
           included. §25.4 is written around this and rejects three alternatives.
  AUTH-001 Registration is LIVE although disabled in configuration. On a public
           hostname this is a self-service account factory. MEASURE what usergroup
           a self-registered account lands in — if there is any path from it to
           usergroup_id 3, D12-A cannot use real data at all and probably cannot
           use a public hostname without Cloudflare Access in front of everything.
  SEC-002  Attendance has no per-leader scope; unassigned leaders record and
           export freely. iCare is an attendance surface.
  GRP-001  Deleting a group hard-deletes every member's permission rows, unscoped,
           with no record of what they were. iCare groups ARE groups rows.
  GRP-002  group_links.church_id comes from the request body with no Gate check;
           measured writing into another church's group.
  No private disk exists (suite 11). §25.7 item 13: an iCare group photo on the
  public disk is CARD-003 with ten identifiable members. attachPhoto() has no
  working backend for this reason.

Cloudflare Access on the admin hostname is already the designed mitigation
(hosting.md §158) and it is an ADDITIONAL boundary, not a replacement for Laravel
authorization — hosting.md says so and it is right.

=== PRODUCE THESE, IN THIS ORDER ===
1. D12 answered with a recommendation, and both options priced in sessions.
2. The critical path to whichever you recommend, as an ordered list with session
   counts. Name what is genuinely required versus what is convention. If the
   honest answer is "the exit gate first", say so and price it; do not invent a
   shortcut around build.md OPERATING CONTRACT 10, and do not pretend the
   contract does not exist either — if the pilot is a legitimate exception,
   ARGUE for it explicitly as decision D13 and let the owner rule.
3. A data plan for the pilot (D14): synthetic vs real, how the synthetic set is
   generated, what happens to it after, and whether any real name ever lands on
   the tunnelled host.
4. A Cloudflare topology (D15) drawn from hosting.md §147-177, not invented:
   tunnel vs VPS, which hostnames, what sits behind Access, what the origin
   firewall does, and what the rollback is when a tester finds something.
5. The D7-D11 sitting, restated as ONE page the owner can answer in one sitting.
   D7 and D8 add columns and gate WP 0C generation; they are the cheapest
   unblocking act available and should be first in your recommended order.
6. Risks you are NOT willing to carry silently, each with the owner decision it
   needs.

=== ESCALATE, DO NOT DECIDE ===
Anything §25 marks 🔴 that the code contradicts. Any exposure of real member data
to a public hostname. Any change to build.md's work-package ordering. Any answer
to D7-D11 — those are the owner's.

=== HOW TO BE WRONG USEFULLY ===
This project has twice recorded a plan that read as correct and was not: §25.4's
first version proposed policies as the escape from SEC-001 and would have passed
every grant test, and PRODUCTION_PATH.md's first estimate counted feature phases
while ignoring the foundation under them. When you state a session count or a
sequencing claim, name the assumption it rests on so the next reader can check it
rather than inherit it.
```

---

## Background the Cowork session should not have to reconstruct

### What session 11 (Claude Code) actually did

**It refused the iCare build**, correctly, on 3 of 3 preconditions: exit gate 4/7, D7–D10
unanswered, D11 unanswered. Zero `ifgf_` migrations exist, confirmed across both migration roots.

**It then reconciled the record**, which turned out to be the session's real output:

- `WP0A_EXIT_GATE.md` had kept session 9's numbers in **twelve places** while its summary table and
  verdict were current. A document whose headline is right and whose body is stale reads as
  authoritative — this session detected the disagreement and **attributed it to the wrong file**.
  The lesson recorded in `MEMORY.md` session 11: *a re-scored artifact needs a grep for its own
  superseded numbers, not a re-read.* Re-grepping after the first pass found two more stale sites in
  a section already read twice.
- **The criterion-2 suite count was inflated by one and had been since session 9** — 6 of 11 where
  6 + 3 + 3 = 12 against a denominator of 11. Resolved by the owner against `TESTING_PLAN.md` and
  the 13 files on disk: complete are suites 2, 5, 6, 9, 11 — **five**. Four files sit outside the
  eleven entirely. **The direction matters more than the digit: it overstated progress on the single
  criterion blocking the gate.**
- Criterion 2's argument was rewritten because its premise expired. It had read *"79 against a target
  of 80–120 is not coverage; it is more coverage."* **94 clears the count and the conclusion did not
  move** — 80–120 is a proxy, and a proxy stops informing the moment it is satisfied.

### Uncommitted work Cowork should know exists

`HEAD` is `3793b54`. **Sessions 9, 10 and 11 are all uncommitted**, and 32 characterization tests
exist in exactly one place on disk (`tests/Feature/Card/`, 17 tests; `tests/Feature/Group/`, 15).
A four-commit split is proposed and unexecuted, blocked on a stale `.git/index.lock` (0 bytes,
2026-08-21 15:57, no git process running) that the owner has not yet cleared. `SCHEMA_SPEC.md`,
`tools/CAMPAIGN_PROMPT.md` and `tools/ICARE_PROMPTS.md` are still untracked.

### Why the pilot framing is worth the owner's attention

`PRODUCTION_PATH.md` puts iCare in **R1b — Phase 1C, 13–19 sessions on top of R1a's 44–57**. Read as
production, "iCare to testing" is a request to reorder the release plan, which is decision D11 and
delays the Sunday cutover. Read as a **synthetic-data pilot behind Cloudflare Access**, it is a much
smaller thing that buys the most valuable feedback available — whether §25.2's date rule and §25.3's
screen states survive contact with real iCare leaders — **without putting one real member record on
the internet.** §25.8 is explicit that the date rule is *argued, not measured*. Leaders using it is
how that changes.

The schema does not split (`PRODUCTION_PATH.md` §92): all 21 `ifgf_` tables are created in WP 0C
regardless, so **answering D7 and D8 unblocks generation for both readings of D12.** That is the
cheapest next act in the whole plan and it costs the owner a sitting, not a session.
