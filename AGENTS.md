# AGENTS.md — repository operating rules

> Read in full every session. Hard cap: 2,000 tokens. These rules are stricter than defaults and
> override them. Method lives in `build.md`; scope in `PRD.md`; session budgeting in
> `EXECUTION_PLAN.md`.

## Start of session — exactly this, nothing more

`git status --short --branch` → `git log -1` → `CONTEXT.md` → `TODO.md` → last 5 entries of
`MEMORY.md` → this file → **only** the `build.md` / `PRD.md` line ranges the active TODO item
names (`EXECUTION_PLAN.md` Appendix A). Then state the work package, step, and exit gate, and ask
for confirmation. Cost of this preamble: ~20k. Anything more is leaking budget.

## Never read whole

`PRD.md` (32.8k), `build.md` (15.6k), `hosting.md` (5.4k), `graphify-out/graph.json` (15MB),
`composer.lock` (490KB), `package-lock.json` (630KB), `yarn.lock`, `mysql-schema.sql`,
`public/js/app.js`. Use `Read` with `offset`/`limit`, or query the file with a script.

The everyday `build.md` load is L28–92 + L114–201 ≈ 6.1k tokens. That is the whole preamble.

## Generate, do not hand-write

Owner preference and the largest single token saving. Anything Artisan can scaffold, scaffold.

```
php artisan make:model Foo --all      # model + migration + factory + seeder
                                      # + controller + requests + policy
php artisan make:migration add_x_to_y --table=y
php artisan make:test Foo/BarTest     # --unit for the unit layer
php artisan make:policy FooPolicy --model=Foo
php artisan make:{job,command,observer,notification,rule,enum,middleware,provider}
```

Hand-writing that file set costs ~6–8k tokens; the command costs ~40. For
`custompackages/ifgf/church-operations`, generate into `app/`, then `git mv` and fix the namespace
with one `sed`.

**Verified Laravel 13 generator flags — Session 2, 2026-08-09.** Confirmed against a real
`composer create-project laravel/laravel "^13.0"` skeleton (Laravel 13.24.0), not documentation.
This repo is still Laravel 10.50.2 (WP 0B upgrades it) — do not assume these exist yet on `HEAD`.

- `make:model Foo -a/--all` → migration, seeder, factory, policy, resource controller, form
  request classes. **Does not include a test** — add `--test`/`--pest`/`--phpunit` explicitly.
  Other flags: `-c` controller, `-f` factory, `-m` migration, `-p`/`--pivot`, `--morph-pivot`,
  `--policy`, `-s`/`--seed`, `-r`/`--resource`, `--api`, `-R`/`--requests`, `--force`.
- `make:migration name --create=table|--table=table [--path=] [--realpath]` (`--fullpath`
  deprecated).
- `make:test name [-u/--unit] [--pest] [--phpunit] [-f/--force]`.
- `make:policy name [-m/--model=] [-g/--guard=] [-f/--force]`.
- `make:controller name [-r/--resource] [--api] [-i/--invokable] [-m/--model=] [-p/--parent=]
  [-R/--requests] [-s/--singleton] [--creatable] [--type=] [--test|--pest|--phpunit] [--force]`.
- `make:factory name [-m/--model=]`. `make:seeder name` (no options besides the global ones).
- `make:job|make:command|make:observer|make:notification|make:rule|make:middleware|make:provider`
  all take `-f/--force`; `make:job` adds `--sync`/`--batched`; `make:command` adds
  `--command=`; `make:observer`/`make:request` are otherwise bare; `make:notification` adds
  `-m/--markdown=`; `make:rule` adds `-i/--implicit`; `make:enum` adds `-s/--string`/`-i/--int`.
- Every generator above also accepts `--test`/`--pest`/`--phpunit` (except `make:policy`,
  `make:factory`, `make:seeder`, `make:provider`, `make:rule`, `make:enum` — no test flags there).
- Global flags on every command: `-h/--help`, `--silent`, `-q/--quiet`, `-V/--version`,
  `--ansi`/`--no-ansi`, `-n/--no-interaction`, `--env=`, `-v|vv|vvv/--verbose`.

## PHP does not run in the sandbox — RE-VERIFY, this may be stale

This claim was written for whatever tool ran Session 1/1b. **Session 2 (Codex, 2026-08-09)
ran `php`, `composer`, and `curl` to `packagist.org`/`repo.packagist.org` directly and successfully**
— no host relay, no network block, no round-trip needed. If a future session is also Codex
running directly on the Windows host, treat every round-trip-cost figure in `EXECUTION_PLAN.md`
§3.4 (Rule T2, "2 verification round-trips per session") as **obsolete for this tool** — test that
assumption once at session start rather than trusting this section. Still always pipe output
(`2>&1 | tail -60` / `Select-Object -Last 60`) — full unfiltered logs are large regardless of
round-trip cost. If a *different* tool (e.g. Codex CLI in a network-sandboxed container) resumes
this project, the original constraint below may still hold for it.

## Search order

1. `graphify query "<≤12 vocabulary tokens>" --budget 2500`, then open only the returned
   `source_location` lines.
2. Targeted `Grep` with a `glob` filter.
3. Never a blind sweep — 668 PHP files and 317 Blade templates cost 20k+ tokens per answer.

Graph results are unreliable until `.graphifyignore` lands (WP 0A Session 5): the graph currently
indexes compiled `public/js/app.js`.

## Hard prohibitions

- **Never commit.** Stage named files, show the proposed message, wait. (`build.md` CONTRACT 7)
- Never amend published commits, force-push, or skip hooks.
- Never use `--ignore-platform-reqs`, `--no-verify`, or forced dependency resolutions.
- Never edit a historical upstream migration. Expand → backfill → verify → contract.
- Never touch `deploy`, production, live Google Calendar, real members, or biometric data.
- Never commit real member data, secrets, or Graphify output.
- Never edit an upstream-owned file without an approved `UPSTREAM.md` entry and a
  characterization test.
- Never run `migrate:fresh` / `db:wipe` without first printing and asserting the environment,
  driver, host, and database name. (`build.md` L515)
- Never start a second work package in one session, or begin an exit gate that will not fit.

## Ownership

New IFGF behavior goes in `custompackages/ifgf/church-operations`. New physical tables use the
`ifgf_` prefix. Upstream model names and paths are preserved exactly — `Events`, `Userprofile`,
`EventAttendanceSession`, `EventAttendee`, `GroupLink`. PRD names are aliases, not replacements.

## End of session — at 120k used, stop and hand off

Append to `MEMORY.md` (what was learned and what failed) → rewrite `TODO.md` so item 1 is the
literal next action → update `CONTEXT.md` → emit the report fields from `build.md` L583–597.
A session that runs to 100% without this costs the next session ~30k in rediscovery.
