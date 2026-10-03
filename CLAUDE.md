# CLAUDE.md — repository operating rules

> Read in full every session. Hard cap: 2,000 tokens. These rules are stricter than defaults and
> override them. Scope lives in `PRD.md` (3.0); build order in `EXECUTION_PLAN.md`; the quality
> bar every work package must pass — tests, databases, security, privacy — in `STANDARDS.md`.

## What this repository is

A **clean Laravel 13 app** for IFGF Taipei–Zhongli — members, teams, attendance. PHP 8.4,
PostgreSQL 17, Blade + Tailwind + Alpine through Vite. No SPA, no API, no passwords.

**Until WP1 lands, the working tree is still the GegoSoft ChurchCMS fork.** Do not build on it and
do not repair it — WP1 replaces it. Afterwards the fork exists only as a read-only reference on
`feat/postgresql-multisite`.

Run on **Opus 5.5, effort `high`**.

## Branch — `dev` only

All work happens on `dev`. **Never create, switch, rebase or delete a branch.** `main` is the
owner's release branch; never push to it. If `git branch --show-current` is not `dev`, stop.

## Start of session — exactly this, nothing more

`git branch --show-current` → `git status --short` → `git log -1` → `CONTEXT.md` → `TODO.md` →
the newest entry of `MEMORY.md` (top of the file) → this file → `STANDARDS.md` → the section of `EXECUTION_PLAN.md` for the work
package in TODO item 1 (plus its "Rules for every session") → the PRD sections that WP names.
Then state the work package, steps and exit gate in ten lines or fewer, and wait for "go".

## Never read whole

`MEMORY.md` (top entry only) · `composer.lock` · `package-lock.json` · `graphify-out/graph.json` ·
anything under `vendor/` or `node_modules/` · the legacy workbook · `GAS_INVENTORY.md` and
`WORKBOOK_INVENTORY.md` beyond the section a WP names. `PRD.md` is under 7k tokens and may be read
whole once.

## Generate, do not hand-write

Owner preference and the largest token saving. Anything Artisan can scaffold, scaffold — then fill
the generated file in. Each WP lists its commands; run them first, in the order given.

```
make:model Foo -mf[s]          migration, factory, [seeder]      (-a adds policy, controller, requests)
make:migration add_x_to_y --table=y   |   create_x_table --create=x
make:controller A/B [--invokable] [--model=Foo --requests]
make:request · make:policy --model=Foo · make:middleware · make:observer --model=Foo
make:mail Foo --markdown=mail.foo · make:job · make:command Foo --command=ifgf:foo
make:class Services/Foo · make:interface Contracts/Foo · make:enum Enums/Foo --string
make:view a.b · make:component · make:test A/FooTest [--unit]
```

Laravel conventions throughout: model `Member` → table `members`, pivot `member_team`. No table
prefix, no `$table` overrides. Fixed lists are string columns backed by PHP enums — never a
database enum, never a lookup table.

## Old code is a reference

`git show feat/postgresql-multisite:<path> | sed -n 'A,Bp'` — one named file at a time, only the
files the WP lists. Never check that branch out. Never copy an upstream (GegoSoft) file.

## Database — PostgreSQL

Credentials are **machine-wide** in `~/.ifgf/postgres.env`, loaded by `bootstrap/global-env.php`
before the framework reads `.env`; `config/database.php` prefers `IFGF_PG_*`. Databases:
**`ifgf_app`** (development) and **`ifgf_app_test`** (tests). `ifgf_cms` belongs to the retired
fork — leave it alone. PostgreSQL 17 is a Windows service and starts by itself.

## Whether PHP runs is tool-dependent — settled, do not re-test

| Tool | php / composer / artisan |
|---|---|
| **Claude Code**, Windows host | Runs directly; bare `php` is 8.4. Verify inline. |
| **Cowork** | Does not run. Write and analyse freely; hand every command to the owner, batched. |
| **Codex** in this workspace | Use `C:\php\8.4\php.exe` explicitly. |

Always pipe output: `2>&1 | tail -40`.

## Search order

1. `graphify query "<≤12 words>" --budget 2500`, then open only the returned lines.
2. `Grep` with a `glob` filter.
3. Never a blind sweep. Rebuild the graph after WP1 and after any large move.

## Hard prohibitions

- **Never stage or commit without the owner's yes.** Show the message, wait. Never amend a pushed
  commit, force-push, or skip hooks. Never `--ignore-platform-reqs` or `--no-verify`.
- Never start a second work package in a session, or build something the PRD does not list —
  that is a question for the owner. `ROADMAP.md` is future modules, not scope; do not read it
  during a work package and never build from it.
- Never add a public route beyond PRD §5.1, never add `routes/api.php`, never put a sequential
  id in a URL or QR.
- Never output with `{!! !!}` except a generated QR SVG.
- Never add a Composer or npm package the WP does not name without asking.
- Never run `migrate:fresh` or `db:wipe` without first printing and asserting the environment,
  driver, host and database name — and never against `ifgf_cms`.
- Never point tests or this machine at a production database, and never copy production data
  into another environment. Test, development, staging and production each have their own
  database and role (`STANDARDS.md` §1).
- Never touch production, the live Google Calendar, the live Apps Script, or send real mail.
  Outside production the mailer is `log` and the calendar driver is the fake.
- Never commit member data, the workbook, LINE recaps, secrets, `.env*`, or Graphify output.
- Never remove `LICENSE.upstream-MIT`.

## Licence

`LICENSE` is **AGPL-3.0** (© 2026 IFGF Taipei Zhongli); `LICENSE.upstream-MIT` keeps GegoSoft's
notice because this repository's history derives from their fork. New PHP files carry one line,
`// SPDX-License-Identifier: AGPL-3.0-or-later`, added by script at the gate — not typed by hand.

## End of session — at the gate or at 120k tokens, whichever is first

Add an entry at the top of `MEMORY.md` (what was learned, what failed) → rewrite `TODO.md` so item 1 is the literal
next action → rewrite `CONTEXT.md` → show the commit message in the owner's daily-log format →
wait. A session that runs out without this costs the next one ~30k tokens of rediscovery.
