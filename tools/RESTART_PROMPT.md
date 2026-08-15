# Restart prompt — finish IFGF Church CMS in Cowork

**Written 2026-08-10** after verifying repository state directly.
**Use:** paste the fenced block into a fresh Cowork (Opus 5) session with all four GitHub folders
selected.

---

## Verified state — 2026-08-10

**Work Package 0B is complete.** This is a real milestone; the plan documents have not caught up.

| | Was | Now |
|---|---|---|
| Laravel | 10.50.2 | **13.24.0** |
| PHP | 8.3.33 | **8.4.24** pinned in `config.platform` |
| PHPUnit / Collision | 10.5 / 6.4 | **11.5.56 / 8.9.5** |
| Sanctum / Laratrust / Medialibrary | 3.3 / 7.2 / 10.15 | **4.3.3 / 8.5.5 / 11.23.5** |
| `laravel/legacy-factories`, `botman/*` | present | **removed** |
| CI, `ifgf/main`, test database | absent | **present** |

**Branch:** `contrib/laravel-supported-platform`, not yet merged to `ifgf/main`.

### The one thing that did not happen

**`tests/` contains 4 files.** The characterization baseline (upgrade prompt Phase B5) was never
written, so Laravel 10 → 11 → 12 → 13 was crossed with no regression detection. Nothing is known
to be broken — but nothing is known to be *working* either, and there is no baseline to compare
future changes against.

This is now debt, not a risk to avoid. **It is the first thing the restart must repay**, and it is
more urgent than before, because the upgrade already happened underneath it.

### Also still open

- `custompackages/ifgf/church-operations` does not exist (WP 0A item 11) — nowhere for new IFGF
  code to legally live.
- WP 0A exit gate never formally reviewed.
- `tools/SESSION5_PROMPT.md` staged but uncommitted.

---

## Document conflicts found — fix before executing

These contradict the verified state. A session trusting them will make wrong decisions.

| File | Line / area | Says | Should say |
|---|---|---|---|
| `build.md` | L116 | "current application is a Laravel 10 and PHP 8.2 source baseline" | Laravel 13.24.0, PHP 8.4.24, as of 2026-08-10 |
| `build.md` | L229–254 | WP 0B as future work | Complete; retain as historical record |
| `build.md` | L614 | "Recommended Laravel 13 upgrade instruction" | Spent — mark superseded |
| `PRD.md` | L15 | Source baseline "Laravel 10, PHP 8.2+" | Upgraded baseline; keep upstream SHA |
| `PRD.md` | L17 | "Phase 0 must upgrade… before feature delivery" | Gate satisfied 2026-08-10 |
| `PRD.md` | L169 | Evidence row "Laravel 10 and PHP 8.2 source baseline" | Update |
| `PRD.md` | L1599 | Delivery plan describes the upgrade as pending | Mark done |
| `PRD.md` | §13.1.13 (L1722) | "Production security takes precedence **if upstream remains on Laravel 10**" | Resolved — record the downstream patch series in `UPSTREAM.md` |
| `CLAUDE.md` | L16 | `PRD.md` (32.8k) | ~33.9k after the QR amendments |
| `CLAUDE.md` | L84–85 | "`.graphifyignore` lands (WP 0A Session 5); graph indexes `public/js/app.js`" | Landed 2026-08-09; graph rebuilt 15 MB → 7.3 MB |
| `CLAUDE.md` | L65–75 | "PHP does not run in the sandbox — RE-VERIFY" | **Make tool-aware, do not delete.** True in Cowork, false in Claude Code — see below |
| `EXECUTION_PLAN.md` | §1.1, §1.4, §4.2 | "Nothing is installed"; WP 0B pending 14–16 sessions; blocker list | All resolved; subtract from the estimate |
| `PRODUCTION_PATH.md` | estimate table | Includes WP 0B's 14–16 sessions | Spent — revise total |
| `TODO.md` | readiness gates | Gates 2, 4, 6, 7 shown open | Closed |
| `DEPENDENCY_INVENTORY.md` | all versions | Laravel 10-era | Regenerate |
| `UPSTREAM.md` | — | No entry for the 0B series | **`laracasts/presenter` was replaced in-house — that is an upstream-owned change needing an entry** |

### The tool-awareness point matters most

`CLAUDE.md` currently frames "PHP does not run in the sandbox" as possibly-stale. It is not stale —
it is **tool-dependent**, and both halves are true:

- **Cowork** (this session): bash sandbox has no PHP or Composer; `packagist.org` and
  `getcomposer.org` are outside the network allowlist. Re-verified 2026-08-10. But file tools
  (`Read`/`Glob`/`Grep`/`Write`/`Edit`) reach **all four** selected folders by Windows path.
- **Claude Code** on the Windows host: runs `php`, `composer`, `artisan` and tests directly.

Rewrite that section to state both, so no future session re-tests it or wrongly concludes it can
run PHP.

---

## The prompt

```
You are resuming the IFGF Church Member and Activity Management project in Cowork.
Read CLAUDE.md, CONTEXT.md, TODO.md, the last 5 entries of MEMORY.md, and
tools/RESTART_PROMPT.md. Do not read PRD.md, build.md or hosting.md whole.

TOOL BOUNDARY — establish this once, do not re-test:
Your bash sandbox has no PHP or Composer and cannot reach packagist. Your file tools
DO reach all four selected folders by Windows path. So you can read, analyse, design,
and write files freely — but every php/composer/artisan/test command must either run
in Claude Code on the Windows host, or be given to me to run and paste back. Plan the
work so verification batches into as few round-trips as possible.

TASK 1 — Reconcile the documents. (own commit)
Work Package 0B completed on 2026-08-10 and the plan documents still describe the
Laravel 10 baseline. tools/RESTART_PROMPT.md lists every conflict with file and line.
Fix them all. Two require judgement rather than a find-and-replace:
  - CLAUDE.md's "PHP does not run in the sandbox" section must become TOOL-AWARE,
    stating both the Cowork and Claude Code cases, not deleted.
  - UPSTREAM.md needs an entry for the WP 0B series, including that
    laracasts/presenter was replaced in-house — an upstream-owned change.
Also re-derive EXECUTION_PLAN.md Appendix A after any PRD.md edit; the regeneration
command is in MEMORY.md 2026-08-09 Session 3g.

TASK 2 — Repay the characterization debt. THIS IS THE PRIORITY.
tests/ has 4 files. Three Laravel majors were crossed without a regression baseline.
Write characterization tests for the Release 1 surface only: authentication, roles and
direct permissions, member profile, member QR / membership card, event attendance
session open / scan / lock / unlock, group access, exports. Capture behaviour as it is
NOW — these tests define the baseline everything after them is measured against.
Do NOT write tests for CGSL, ministries, registration or Worship Night — Release 2 per
PRODUCTION_PATH.md.
I will run the suite and paste results. Batch your requests.

TASK 3 — Close the remaining WP 0A gates. (own commits)
  - Scaffold custompackages/ifgf/church-operations with Composer path loading and
    Laravel package auto-discovery. No product behaviour. WP 0A item 11.
  - Provider smoke tests from a clean checkout. WP 0A item 14.
  - Merge contrib/laravel-supported-platform into ifgf/main after the suite passes.
  - Run the upstream merge rehearsal. The fork is behind upstream/main; the owner
    wants upstream's additional features, so this is recurring infrastructure.
  - Formally review the WP 0A exit gate and report which criteria are met.

TASK 4 — Then continue with tools/PROMPTS.md P1 (Work Package 0C) and onward.

STANDING RULES
- Never commit without showing me staged files and the message first.
- Use artisan generators rather than hand-writing files; flags are in CLAUDE.md,
  verified against a real Laravel 13.24.0 skeleton.
- New IFGF behaviour goes in the package; new tables use the ifgf_ prefix.
- Never touch deploy, production, live Google Calendar, or real member data.
- The workbook and Google Apps Script outrank the PRD on requirements; upstream
  ChurchCMS supplies implementation, not requirements. Escalate conflicts to me.
- Stop at 120k tokens and hand off per CLAUDE.md.

Start with Task 1. Show me the reconciliation diff before committing.
```

---

## Why this order

Task 1 first because **every subsequent decision reads those documents.** A session that trusts
`build.md` L116 will plan an upgrade that already happened.

Task 2 before Task 3 because the characterization tests are the only thing that can tell you
whether the Laravel 13 upgrade preserved behaviour. Scaffolding a package on top of an unverified
application compounds the uncertainty rather than reducing it.

Task 3's merge to `ifgf/main` is gated on Task 2 passing, deliberately — merging an unverified
platform series into the integration branch is the one action here that is genuinely hard to undo.

## Revised estimate

WP 0B's 14–16 sessions are spent. Remaining to Release 1 production: **roughly 55–70 sessions**,
against the 73–90 estimated on 2026-08-09.
