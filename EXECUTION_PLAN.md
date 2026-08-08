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

### 1.1 Verdict

**Nothing is installed.** This is a source checkout only.

| Requirement | Declared | Found | Status |
|---|---|---|---|
| PHP | `^8.2` (composer.json), `^8.2` (lock platform) | none | **MISSING** |
| Composer | 2.x | none | **MISSING** |
| MySQL | 8.4 LTS (hosting.md) | none | **MISSING** |
| Node | unpinned — no `engines`, no `.nvmrc` | v22.22.3 | **WRONG LINE** |
| npm | — | 10.9.8 | present |
| `vendor/` | 148 prod + 35 dev packages | absent | **MISSING** |
| `node_modules/` | 13 dev + 39 prod packages | absent | **MISSING** |
| `.env` | from `.env.example` | absent | **MISSING** |
| `tests/` | characterization suite (WP 0A item 6) | **0 files** | **MISSING** |

### 1.2 Why the assistant cannot install this itself

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

### 1.4 Blockers already visible in `composer.lock` (feed Work Package 0B)

Recorded now so WP 0B does not rediscover them at token cost.

| Package | Locked | Laravel 13 risk |
|---|---|---|
| `laravel/legacy-factories` | v1.4.2 | **Hard blocker.** Laravel 10 max. Must be removed and factories rewritten. |
| `botman/botman` + `botman/driver-web` | v2.8.11 / v1.5.3 | **High.** Effectively unmaintained for Laravel 11+. Removal or isolation candidate. |
| `spatie/laravel-medialibrary` | v10.15.0 | **High.** Needs v11/v12. Also not authoritative for IFGF media per `build.md` invariant 35. |
| `laravel/sanctum` | v3.3.3 | Needs `^4`. |
| `nunomaduro/collision` | v6.4.0 | Needs `^8`. |
| `santigarcor/laratrust` | v7.2.1 | Needs v8+. Physical model for roles — invariant 9. Cannot be dropped. |
| `custompackages/brozot/laravel-fcm` | 1.0.0 (path repo) | Local fork. `build.md` WP 0B item 4 requires an explicit audit. |
| `doctrine/annotations` | 2.0.2 | Marked **abandoned** upstream. |
| Frontend: Vue 2.6 / laravel-mix 4 / webpack 4 | — | Vue 2 is EOL. Drives the Vite decision, WP 0B item 8. |

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
| WP 0B | 14 | L234–249 |
| WP 0C | 19 | L260–280 |
| WP 0D | 5 | L292–297 |
| **Phase 0 subtotal** | **53** | |
| Phase 1 (22 subpackages × 16-step slice loop, L183–200) | 352 | |
| Phase 2 (2A 6, 2B 3, 2C 4, other 3) | 16 | L446–482 |
| Phase 3 | 5 | L490–494 |
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
4. Emits the end-of-work-package report fields from `build.md` L585–597.

A session that runs to 100% without doing this is a lost session — the next one re-derives state
at ~30k tokens of waste.

### 3.4 Windows round-trip cost

Because the assistant cannot run PHP, each verification cycle costs tokens:

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

| Scope | Approval units | Steps | E (sessions) | Dominant cost |
|---|---:|---:|---:|---|
| WP 0B — platform upgrade | 1 | 14 | 14–16 | 3 Laravel majors + 183-package audit + Vue2/Mix4 → Vite |
| WP 0C — schema + migration foundation | 1 | 19 | 14–18 | 93 legacy migrations, `usergroup_id` removal, import pipeline |
| WP 0D — privacy gates | 1 | 5 | 3–4 | Documentation-heavy, low tool cost |
| **Phase 0 total** | **4** | **53** | **43–52** | |
| Phase 1A — member registry + portal | 5 | 80 | 12–15 | |
| Phase 1B — events + attendance | 5 | 80 | 14–18 | Largest Phase 1 epic |
| Phase 1C — iCare, CGSL, ministries | 4 | 64 | 10–13 | |
| Phase 1D — calendar, reports, imports | 5 | 80 | 13–16 | Google Calendar adapter + import commit |
| Phase 1E — production readiness | 3 | 48 | 8–11 | Two gates need external authorization |
| **Phase 1 total** | **22** | **352** | **57–73** | |
| **MVP total (Phase 0 + 1)** | **26** | **405** | **100–125** | |
| Phase 2 — hardening + recognition | 3 | 16 | 22–28 | Separate Python edge repository |
| Phase 3 — ticketing | 1 | 5 | 7–9 | |
| **Full programme** | **30** | **426** | **129–162** | |

**Estimate basis:** 668 PHP files · 172 controllers · 79 models · 93 migrations · 317 Blade
templates · **0 existing tests** · 183 Composer packages · 52 npm packages. The zero-test baseline
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

### `PRD.md` (32,809 tokens total)

| Lines | ~Tokens | Section |
|---|---:|---|
| 22–75 | 1,374 | 1. Executive Summary |
| 76–216 | 3,184 | 2. Evidence and Existing-System Inventory |
| 217–263 | 780 | 3. Product Scope and Personas |
| 264–952 | 9,582 | 4. Domain Model — **split by subsection; never read whole** |
| 953–1168 | 6,497 | 5. Functional Requirements (FR-01 … FR-13) |
| 1169–1308 | 1,130 | 6. Key Workflows |
| 1309–1481 | 3,009 | 7. Application Architecture |
| 1482–1513 | 1,007 | 8. Non-Functional Requirements |
| 1514–1579 | 771 | 9. Migration Plan |
| 1580–1613 | 882 | 10. Delivery Plan |
| 1614–1664 | 1,860 | 11. Acceptance Test Matrix |
| 1665–1690 | 776 | 12. Engineering Rules for the Implementing LLM |
| 1691–1733 | 1,139 | 13. Decisions and Open Questions |
| 1734–1758 | 560 | 14. External References |

### `build.md` (15,590 tokens total)

| Lines | Section | Read when |
|---|---|---|
| 28–45 | Operating Contract | every session |
| 46–92 | Required Startup Sequence | every session |
| 114–140 | Technical Baseline | every session |
| 141–178 | Architectural Invariants | every session |
| 179–201 | Implementation Strategy | every Phase 1+ session |
| 202–228 | Work Package 0A | 0A only |
| 229–254 | Work Package 0B | 0B only |
| 255–285 | Work Package 0C | 0C only |
| 286–302 | Work Package 0D | 0D only |
| 303–330 | Phase 1A | 1A only |
| 331–357 | Phase 1B | 1B only |
| 358–380 | Phase 1C | 1C only |
| 381–405 | Phase 1D | 1D only |
| 406–437 | Phase 1E | 1E only |
| 438–483 | Phase 2 | 2A/2B/2C only |
| 484–499 | Phase 3 | 3 only |
| 500–533 | Test and Quality Commands | any session running tests |
| 534–548 | Production Release to `deploy` | 1E.3 only |
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
