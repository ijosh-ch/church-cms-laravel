# Execution Plan and Session Token Budget

**Project:** IFGF Church Member and Activity Management
**Repository:** `church-cms-laravel`
**Plan date:** 2026-08-08
**Governs:** how `build.md` is executed across multiple LLM sessions without losing state
**Does not govern:** product scope (`PRD.md`), production topology (`hosting.md`), or method (`build.md`)

This file answers two questions: *are the requirements installed*, and *how many steps and
tokens does the remaining work take*. It exists so that a session ending at 100% context can be
resumed by the next session at an exact, verifiable checkpoint.

---

## Part 1 — Requirements audit

### 1.1 Verdict — SUPERSEDED 2026-08-10

The original 2026-08-08 verdict was **"nothing is installed."** That is no longer true; the
toolchain landed in Sessions 1b–4. Current state:

| Requirement | Declared | Found 2026-08-10 | Status |
|---|---|---|---|
| PHP | `^8.3` (composer.json), `8.4.24` (`config.platform`) | 8.4.24, first on Machine PATH | **OK** |
| Composer | 2.x | 2.10.2 | **OK** |
| MySQL | 8.4 LTS (hosting.md) | 8.4.11 LTS | **OK** |
| Node | unpinned — no `engines`, no `.nvmrc` | v22.15.0 | **WRONG LINE** — laravel-mix 4 wants 16.20.2; unresolved, and moot if Vite lands |
| npm | — | present | OK |
| `vendor/` | 152 prod + 36 dev packages | installed | **OK** |
| `node_modules/` | 1,286 packages | installed; `npm run production` exits 0 | **OK**, 166 advisories |
| `.env` / `.env.testing` | from `.env.example` | both present, `.env.testing` gitignored | **OK** |
| `tests/` | characterization suite (WP 0A item 6) | **4 files, 1 real test** | **STILL MISSING — the one open blocker** |

Everything in this table except the last two rows is closed. **Do not re-plan installation work.**

### 1.1a Original verdict, 2026-08-08 — historical

"**Nothing is installed.** This is a source checkout only." PHP, Composer, MySQL, `vendor/`,
`node_modules/`, `.env` and `tests/` were all absent. Retained so the Session 1 estimates below
remain interpretable.

### 1.2 Why the assistant cannot install this itself — TOOL-DEPENDENT, see `CLAUDE.md`

**This section is true for Cowork and false for Claude Code.** `CLAUDE.md` § "Whether PHP runs is
TOOL-DEPENDENT" carries the settled matrix; Sessions 2 and 4 ran `php`, `composer`, `artisan` and
the full upgrade directly on the Windows host. The table below describes the **Cowork / sandboxed**
case only, which remains accurate as re-verified 2026-08-10.

The assistant's sandbox is Linux with no root and a network allowlist. Verified:

| Endpoint | Result |
|---|---|
| `github.com`, `registry.npmjs.org` | 200 |
| `repo.packagist.org`, `packagist.org` | blocked |
| `getcomposer.org`, `php.net` | blocked |
| `api.github.com`, `codeload.github.com` | blocked |
| `apt-get`, `sudo` | no privileges |

PHP cannot be installed and Composer cannot resolve metadata. **All PHP-side work runs on the
Windows host and its output is pasted back into the session.** Budget for this — see §3.4.

Run `tools\setup-windows.ps1` from an elevated PowerShell, then paste the version report back.

### 1.3 Version decisions this audit forces

| Decision | Choice | Reason |
|---|---|---|
| PHP for WP 0A | **8.3** | Only line satisfying both Laravel 10 (supports 8.1–8.3) and Laravel 13 (requires ≥8.3). Avoids installing PHP twice. |
| PHP for production (WP 0B) | **8.4**, pending extension audit | Active support to 2026-12-31, security to 2028-12-31. 8.3 loses active support 2026-11-23. Do not pick 8.5 by default (`build.md` TECHNICAL BASELINE 14). |
| Laravel target | **13.x** — confirmed newest stable on 2026-08-08 | `build.md` requires rechecking at execution time. Done. Minimum PHP 8.3. |
| MySQL | **8.4 LTS** | 8.0 reached EOL April 2026; it is migration-source only. |
| Node for the legacy build | **16.20.2** via nvm-windows | laravel-mix 4 / webpack 4 will not build on Node 22. Pin in `.nvmrc` once proven. |

### 1.4 Blockers visible in `composer.lock` — RESOLVED 2026-08-10 except the frontend

Every prediction below was actioned in WP 0B. Outcomes recorded so nothing is re-litigated.

| Package | Predicted | Outcome 2026-08-10 |
|---|---|---|
| `laravel/legacy-factories` | **Hard blocker.** Laravel 10 max. | **Removed** in `086f33d`, before the framework was touched. Correct call. |
| `botman/botman` + `botman/driver-web` | **High.** Isolation candidate. | **Removed** — UP-006 found zero call sites; there was nothing to isolate. |
| `spatie/laravel-medialibrary` | **High.** Needs v11/v12. | **11.23.5.** |
| `laravel/sanctum` | Needs `^4`. | **4.3.3.** |
| `nunomaduro/collision` | Needs `^8`. | **8.9.5** — and it forced PHPUnit 10 → **11.5.56**, which this table did not predict. |
| `santigarcor/laratrust` | Needs v8+. Cannot be dropped. | **8.5.5.** Its entire public API was renamed; four authorization call sites aliased on import. See UP-007. |
| `custompackages/brozot/laravel-fcm` | Local fork, needs audit. | **Removed** — orphaned path repo, never in `require`, never autoloaded (UP-006). |
| `doctrine/annotations` | Abandoned upstream. | **Still present at 2.0.2**, still abandoned, still transitive. Not yet traced to its parent. Open. |
| **`laracasts/presenter`** | **NOT PREDICTED** | **The actual sole hard blocker for Laravel 13.** 0.2.8 stops at `illuminate/support ^12.0`. Replaced in-house (UP-007). The lesson: constraint-scanning `composer.lock` missed it because 0.2.8 is the *newest* release — a package can be current and still be a dead end. |
| Frontend: Vue 2.6 / laravel-mix 4 / webpack 4 | Vue 2 EOL; drives the Vite decision. | **Unresolved and deferred by approval.** Build still exits 0; 166 npm advisories stand. WP 0B item 8. |

### 1.5 Required PHP extensions

Derived from every `ext-*` in the 183 locked packages, plus the Laravel/MySQL runtime baseline:

```
bcmath ctype curl dom exif fileinfo filter gd hash iconv json libxml mbstring
openssl pcre pdo pdo_mysql phar session simplexml sodium tokenizer xml
xmlreader xmlwriter zip zlib
```

`tools\setup-windows.ps1` checks these against `php -m` and reports the gap.

---

## Part 2 — Step count

`build.md` is counted at three resolutions. All three are used by the session map.

### 2.1 Approval units — 30

An approval unit is a scope that requires owner confirmation *before* the first code change.

| Group | Units |
|---|---|
| Phase 0 work packages (0A, 0B, 0C, 0D) | 4 |
| Phase 1A subpackages (1A.1 – 1A.5) | 5 |
| Phase 1B subpackages (1B.1 – 1B.5) | 5 |
| Phase 1C subpackages (1C.1 – 1C.4) | 4 |
| Phase 1D subpackages (1D.1 – 1D.5) | 5 |
| Phase 1E work packages (1E.1 – 1E.3) | 3 |
| Phase 2 subpackages (2A, 2B, 2C) | 3 |
| Phase 3 | 1 |
| **Total** | **30** |

### 2.2 Exit gates — 15

`0A · 0B · 0C · 0D · 1A · 1B · 1C · 1D · 1E-repository · 1E-staging · 1E-production · 2A · 2B · 2C · 3`

A gate is never split across sessions. If a session cannot finish a gate, it stops *before*
starting it and hands off. `build.md` is explicit: do not mark a gate complete because time ran out.

### 2.3 Discrete required steps — 426

| Scope | Steps | Source |
|---|---|---|
| WP 0A | 15 | `build.md` L207–224 |
| WP 0B | 14 | L236–251 |
| WP 0C | 19 | L262–282 |
| WP 0D | 5 | L294–299 |
| **Phase 0 subtotal** | **53** | |
| Phase 1 (22 subpackages × 16-step slice loop, L183–200) | 352 | |
| Phase 2 (2A 6, 2B 3, 2C 4, other 3) | 16 | L448–484 |
| Phase 3 | 5 | L492–496 |
| **Total** | **426** | |

Every Phase 1+ subpackage runs the same 16-step loop: map to PRD → fetch upstream SHA → query
Graphify → classify file ownership → inspect migrations → failing tests → smallest schema change →
domain service → policies → controllers/routes/UI → focused then full tests → 360px mobile check →
PRD traceability → UPSTREAM.md + CONTEXT.md + MEMORY.md + TODO.md → `graphify update` → merge
rehearsal → present for approval.

---

## Part 3 — Token budget model

### 3.1 Measured document costs

These are real counts from this repository, not estimates:

| File | Tokens | Lines |
|---|---:|---:|
| `PRD.md` | 32,809 | 1,758 |
| `build.md` | 15,590 | 653 |
| `hosting.md` | 5,387 | 287 |
| **All three, read whole** | **53,786** | |

**Reading all three governing documents consumes 36% of a 150k session before any work
starts.** This is the single largest controllable cost in the project. The rule below exists
because of this number.

> **Rule T1 — never read `PRD.md`, `build.md`, or `hosting.md` in full.**
> Read only the line ranges in Appendix A, using `Read` with `offset` and `limit`.

### 3.2 Session budget

Window 200k. Usable 150k (25% reserved for tool output and reasoning headroom).

| Component | Budget | Notes |
|---|---:|---|
| System prompt + tool schemas | ~12k | fixed |
| `CLAUDE.md` | ≤2k | hard cap, enforced |
| `CONTEXT.md` | ≤1.5k | hard cap |
| `TODO.md` (active slice only) | ≤1.5k | archive completed items |
| `MEMORY.md` (last 5 entries only) | ≤2k | read the tail, not the file |
| `build.md` targeted sections | 6–8k | contract + baseline + invariants + one work package |
| `PRD.md` targeted sections | 3–10k | see Appendix A |
| **Preamble subtotal** | **~32k** | |
| **Working budget** | **~118k** | code, tools, tests, iteration |
| Handoff reserve | 20k | **hard stop at 118k used** |
| **Effective work per session** | **~98k** | |

### 3.3 The 80% rule

At **120k tokens used (80% of 150k)** the session stops taking new work, regardless of how it
feels. It then, in this order:

1. Writes the `MEMORY.md` entry for what was completed.
2. Rewrites `TODO.md` so the top item is the exact next action.
3. Updates `CONTEXT.md` (branch, SHA, work package, session number).
4. Emits the end-of-work-package report fields from `build.md` L585–599.

A session that runs to 100% without doing this is a lost session — the next one re-derives state
at ~30k tokens of waste.

### 3.4 Windows round-trip cost — applies to Cowork only

**Tool-dependent.** In **Claude Code on the Windows host** PHP runs inline and Rule T2's
"2 round-trips per session" budget does not apply — verify freely, still piping output. In
**Cowork** every figure below is live and binding. See `CLAUDE.md` § "Whether PHP runs is
TOOL-DEPENDENT". Do not re-test this at session start.

Where the assistant cannot run PHP, each verification cycle costs tokens:

| Round-trip | Typical cost |
|---|---:|
| `composer install` output (success) | 3–5k |
| `composer install` output (failure with conflict tree) | 8–15k |
| `php artisan test` full suite | 5–20k |
| `php artisan migrate:fresh --seed` | 2–4k |
| `npm ci` / `npm run production` | 4–10k |

> **Rule T2 — always request piped output.** `php artisan test 2>&1 | Select-Object -Last 60`,
> `composer install 2>&1 | Select-Object -Last 40`. Never paste a full unfiltered log. Budget
> **2 verification round-trips per session, ~12k total.**

### 3.5 Token-reduction rules

> **Rule T3 — generate, never hand-write.** Owner preference and the largest single saving.
> Every file that Artisan can scaffold is scaffolded, then edited. Writing a model + migration +
> factory + seeder + controller + form request + policy + test by hand costs ~6–8k tokens.
> One `make:model` command costs ~40 tokens and produces the same seven files.
>
> ```
> php artisan make:model Foo --all          # model, migration, factory, seeder,
>                                           # controller, requests, policy
> php artisan make:migration add_x_to_y --table=y
> php artisan make:test  Foo/BarTest        # --unit for unit layer
> php artisan make:policy FooPolicy --model=Foo
> php artisan make:job / :command / :observer / :notification / :rule / :enum
> ```
>
> In **Session 2**, run `php artisan make:model --help` once and record the exact flags Laravel 13
> supports in `CLAUDE.md`. Do not guess flags across sessions — a failed command costs more than
> the lookup.
>
> For `custompackages/ifgf/church-operations`: generate into `app/`, then `git mv` into the package
> and fix the namespace with a single `sed`. Cheaper than writing package stubs by hand.

> **Rule T4 — query Graphify, do not grep the tree.** `graphify-out/graph.json` is 15MB.
> Never read it. Use `graphify query "<≤12 vocabulary tokens>" --budget 2500`, then open only the
> exact `source_location` files and lines it returns. A blind `Grep` across 668 PHP files and 317
> Blade templates can cost 20k+ tokens for one answer.

> **Rule T5 — `.graphifyignore` before anything else.** The graph currently indexes compiled
> `public/js/app.js`, which makes every architecture query return generated bundles instead of
> source. This is WP 0A item 15 and is scheduled early (Session 5) precisely because it lowers the
> cost of every session after it.

> **Rule T6 — one work package per session.** Never carry two work packages in one context.
> `build.md` OPERATING CONTRACT 10 forbids it anyway; the token model makes it impossible.

> **Rule T7 — `_ai/` and `_code_reference/` are reference, not context.** Read on demand only.

---

## Part 4 — Session map

`E` = estimated sessions. Phase 0A is enumerated concretely because it starts now; later phases are
scoped at work-package resolution and re-enumerated when their approval gate opens.

### 4.1 Work Package 0A — baseline and safety net (E: 12–14)

**Progress 2026-08-10:** sessions 1–6 and 12 are **done**; 13 is **half done** (CI green, merge
rehearsal never run); 14 is **half done** (`ifgf/main` created locally and unpushed, `deploy`
correctly absent, exit gate never formally reviewed). Sessions **7 (package scaffold)** and
**9–11 (characterization)** are **not started** and are the whole remaining critical path.

| # | Session | `build.md` items | Preamble | Work | Exit condition |
|---|---|---|---|---:|---:|
| 1 | Toolchain + doc baseline commit | 1, 2 | 30k | 40k | Versions recorded; PRD/hosting/build committed; SHA in `CONTEXT.md` |
| 2 | Dependency inventory | 3 | 32k | 70k | 183 packages classified: keep / upgrade / replace / remove |
| 3 | Route + migration inventory | 4a | 32k | 90k | 5 route files and 93 migrations mapped |
| 4 | Runtime inventory | 4b | 32k | 90k | Auth, roles, attendance, cards, exports, media, storage, queues, schedule |
| 5 | `.graphifyignore` + graph rebuild | 15 | 30k | 50k | Queries return source, not `public/js/app.js` |
| 6 | Upstream pin + `UPSTREAM.md` | 9, 10 | 32k | 60k | Baseline SHA, divergence counts, ownership map |
| 7 | Package scaffold | 11 | 32k | 80k | `custompackages/ifgf/church-operations` loads, zero behavior change |
| 8 | Provider smoke tests | 14 | 32k | 70k | Routes, migrations, views, translations, commands, policies from clean checkout |
| 9 | Characterization: auth + roles | 6a | 32k | 95k | Auth, existing roles, direct permissions |
| 10 | Characterization: member + groups + events | 6b | 32k | 95k | Profile, group access, event management, session opening |
| 11 | Characterization: QR + attendance + media | 6c | 32k | 95k | Member QR, check-in, birthday routes, exports, queues, private media |
| 12 | Testing DB + fixtures | 5, 8 | 30k | 60k | Disposable MySQL 8.4, anonymized fixtures, zero real data |
| 13 | CI + merge rehearsal | 7, 13 | 32k | 80k | CI green from clean checkout; upstream rehearsal passes |
| 14 | Branch topology + exit gate | 12, gate | 32k | 60k | `main`/`ifgf/main`/`deploy` protected; compatibility matrix reviewed |

### 4.2 Remaining scope

**Revised 2026-08-10.** WP 0B is spent — it cost **1 session**, not the 14–16 estimated. That is
the single largest estimate error in this plan and is worth understanding rather than celebrating:
the upgrade was fast because item 6 (characterization tests) was skipped by owner directive, and
that work has not disappeared, it has moved. The ±40% confidence band on WP 0B was also wrong in
the wrong direction — dependency resolution turned out to be the *easy* part; the unpredictable
part was a single abandoned 60-line package no constraint scan flagged.

| Scope | Approval units | Steps | E (sessions) | Dominant cost |
|---|---:|---:|---:|---|
| ~~WP 0B — platform upgrade~~ | ~~1~~ | ~~14~~ | **SPENT — 1** | Done 2026-08-10. Items 6 and 8 carried forward below, not re-estimated here |
| WP 0A closeout — package seam, provider smoke tests, merge rehearsal, exit gate | 0 (already approved) | 4 | 2–3 | Merge rehearsal is the unknown |
| **WP 0A item 6 — characterization tests (carried from 0B)** | 0 (already approved) | 1 | **3–5** | 8-minute suite until `schema:dump` lands; 7 behaviour surfaces; every run is an owner round-trip in Cowork |
| Vite migration (WP 0B item 8, deferred) | 1 | 1 | 4–6 | Separate approved decision; must not mix with a framework upgrade |
| WP 0C — schema + migration foundation | 1 | 19 | 14–18 | 93 legacy migrations, `usergroup_id` removal, import pipeline |
| WP 0D — privacy gates | 1 | 5 | 3–4 | Documentation-heavy, low tool cost |
| **Phase 0 remaining** | **2** | **34** | **26–36** | Was 43–52 including WP 0B |
| Phase 1A — member registry + portal | 5 | 80 | 12–15 | |
| Phase 1B — events + attendance | 5 | 80 | 14–18 | Largest Phase 1 epic |
| Phase 1C — iCare, CGSL, ministries | 4 | 64 | 10–13 | |
| Phase 1D — calendar, reports, imports | 5 | 80 | 13–16 | Google Calendar adapter + import commit |
| Phase 1E — production readiness | 3 | 48 | 8–11 | Two gates need external authorization |
| **Phase 1 total** | **22** | **352** | **57–73** | |
| **MVP remaining (Phase 0 + 1)** | **24** | **386** | **83–109** | Was 100–125 |
| Phase 2 — hardening + recognition | 3 | 16 | 22–28 | Separate Python edge repository |
| Phase 3 — ticketing | 1 | 5 | 7–9 | |
| **Full programme remaining** | **28** | **407** | **112–146** | Was 129–162 |

These are full-PRD figures. `PRODUCTION_PATH.md` carries the Release-1-scoped numbers, which are
lower and are the ones to plan against.

**Estimate basis:** 668 PHP files · 172 controllers · 79 models · 93 migrations · 317 Blade
templates · **1 existing test** (`MemberImportCharacterizationTest`) · 188 Composer packages ·
52 npm packages. The near-zero-test baseline
is the biggest driver: Phase 0A writes characterization coverage from nothing, and every Phase 1
slice writes its tests before its code.

**Confidence:** WP 0A ±15%. WP 0B ±40% — dependency resolution across three Laravel majors is the
least predictable work in the plan. Phase 1 ±30%. Phase 2 ±60%, and it should be re-estimated at
its approval gate rather than trusted now.

---

## Part 5 — Resume protocol

### 5.1 Every session starts with exactly this

1. `git status --short --branch` and `git log -1 --format="%H %ai"`
2. Read `CONTEXT.md` — full (capped at 1.5k)
3. Read `TODO.md` — full (capped at 1.5k)
4. Read the **last 5 entries** of `MEMORY.md` (`tail`, not the whole file)
5. Read `CLAUDE.md` — full (capped at 2k)
6. Read only the `build.md` and `PRD.md` line ranges named by the active TODO item (Appendix A)
7. State the work package, the step being resumed, and the exit gate — then ask for confirmation

Steps 1–6 cost ~20k. Anything beyond this before doing work is budget leaking.

### 5.2 Every session ends with exactly this

At 120k used, or when the current step completes and the next does not fit:

1. `MEMORY.md` — append one dated entry: what was done, what was learned, what surprised you
2. `TODO.md` — rewrite so item 1 is the literal next action, with its `build.md` item number
3. `CONTEXT.md` — branch, HEAD SHA, upstream SHA, work package, session number, gate status
4. Report: files changed with ownership, tests run (pass/fail/skip), migrations and rollback,
   upstream status, remaining gate items, proposed commit message
5. **Do not commit.** `build.md` OPERATING CONTRACT 7 — stage named files, present, wait

### 5.3 Handoff smell tests

A handoff is bad if the next session has to ask any of these:

- *Which branch?* → `CONTEXT.md` is stale
- *What was I doing?* → `TODO.md` item 1 was not specific enough
- *Did this already fail?* → `MEMORY.md` entry omitted the negative result
- *Which PRD section?* → the TODO item omitted its line range

---

## Appendix A — Targeted read index

Never read these files whole. Read the range.

### `PRD.md` (~34,100 tokens total) — **line map re-derived 2026-08-10 after the WP 0B reconciliation**

| Lines | ~Tokens | Section |
|---|---:|---|
| 22–75 | 1,374 | 1. Executive Summary |
| 76–216 | 3,203 | 2. Evidence and Existing-System Inventory |
| 217–263 | 780 | 3. Product Scope and Personas |
| 264–952 | 9,582 | 4. Domain Model — **split by subsection; never read whole** |
| 953–1183 | 7,418 | 5. Functional Requirements (FR-01 … FR-14) |
| 1184–1323 | 1,130 | 6. Key Workflows |
| 1324–1496 | 3,009 | 7. Application Architecture |
| 1497–1528 | 1,007 | 8. Non-Functional Requirements |
| 1529–1594 | 771 | 9. Migration Plan |
| 1595–1630 | 940 | 10. Delivery Plan |
| 1631–1681 | 1,860 | 11. Acceptance Test Matrix |
| 1682–1707 | 776 | 12. Engineering Rules for the Implementing LLM |
| 1708–1758 | 1,663 | 13. Decisions and Open Questions |
| 1759–1783 | 560 | 14. External References |

> **Re-derive this table after every `PRD.md` edit.** A stale line map sends sessions to the wrong
> range, which costs more than the edit saved. One command:
> `python3 -c "..."` on `^## ` headings — see `MEMORY.md` 2026-08-09 Session 3g.

### `build.md` (15,914 tokens total) — **line map re-derived 2026-08-10**

| Lines | Section | Read when |
|---|---|---|
| 28–45 | Operating Contract | every session |
| 46–92 | Required Startup Sequence | every session |
| 114–140 | Technical Baseline | every session |
| 141–178 | Architectural Invariants | every session |
| 179–201 | Implementation Strategy | every Phase 1+ session |
| 202–228 | Work Package 0A | 0A only |
| 229–256 | Work Package 0B — **marked complete 2026-08-10**; read for the exit-gate checklist only | 0A/0B closeout |
| 257–287 | Work Package 0C | 0C only |
| 288–304 | Work Package 0D | 0D only |
| 305–332 | Phase 1A | 1A only |
| 333–359 | Phase 1B | 1B only |
| 360–382 | Phase 1C | 1C only |
| 383–407 | Phase 1D | 1D only |
| 408–439 | Phase 1E | 1E only |
| 440–485 | Phase 2 | 2A/2B/2C only |
| 486–501 | Phase 3 | 3 only |
| 502–535 | Test and Quality Commands | any session running tests |
| 536–550 | Production Release to `deploy` | 1E.3 only |
| 585–599 | End-of-Work-Package Report | every session end |
| 611–623 | Laravel 13 upgrade instruction — **spent, do not issue** | never |
| 549–566 | Security and Data Rules | any session touching data or media |
| 567–582 | Decision and Blocker Policy | when blocked |
| 583–598 | End-of-Work-Package Report | every session end |

The everyday preamble — L28–92, L114–201 — is about **6.1k tokens**. That is the correct
`build.md` load. Reading all 653 lines instead costs 15.6k and buys nothing.

### `hosting.md` (5,387 tokens total)

Small enough to read whole, but only in WP 0D, Phase 1E, and any session touching backups,
secrets, storage, or capacity.

---

## Appendix B — Immediate next actions

1. Run `tools\setup-windows.ps1` elevated; paste the version report back. *(blocks everything)*
2. Commit the documentation baseline — `PRD.md`, `hosting.md`, `build.md`, `.gitignore`,
   and this plan — and record the SHA in `CONTEXT.md`. *(`build.md` OPERATING CONTRACT 6)*
3. Confirm Work Package 0A scope, then start Session 2.

`graphify-out/` stays untracked (`build.md` OPERATING CONTRACT 9). Already handled in `.gitignore`.
