# Session prompt — paste this at the start of every development session

Use **Opus 5.5 at effort `high`**. The same prompt works for every work package; the state is in
`TODO.md`, not here.

```
Continue building the IFGF member and attendance app in this repository.

Scope: PRD.md version 3.0 and nothing else. That is my 17 requirements of 2026-10-04 —
registration like the Google Form, households, school search, member categories, birthdays and
the birthday calendar, QR attendance with the usher choosing the branch, events, weekly iCare
attendance, leader-proposed changes confirmed by email, the attendance table, the quarterly
review, the workbook and LINE imports — plus teams and divisions with their leaders and members.
ROADMAP.md is future work (face detection, assets, analytics, ticketing, offerings, event
photos, profile photos). Do not read it and do not build anything from it.

1. Branch. Run `git branch --show-current`. It must print `dev`. If it prints anything else,
   stop and tell me. Do not create, switch, rebase or delete any branch, and never push to `main`.
   Everything happens on `dev`.

2. Follow CLAUDE.md exactly — its start-of-session reads, its "generate, do not hand-write" rule,
   and its hard prohibitions. Read nothing beyond what it lists.

3. If `git status --short` shows uncommitted planning documents (PRD.md, EXECUTION_PLAN.md,
   STANDARDS.md, CLAUDE.md, TODO.md, CONTEXT.md, MEMORY.md, ROADMAP.md, SESSION_PROMPT.md, the two
   inventories, design/), show me a commit message for them first and commit after my yes. Work
   package 1 replaces the whole tree and needs them committed.

4. Do the work package named in TODO.md item 1, and only that one. Its steps, generator commands
   and exit gate are in its section of EXECUTION_PLAN.md. If something you need is not in the
   PRD, ask me instead of building it.

5. Before you change anything, tell me in ten lines or fewer: the work package, the steps you
   will take, the exit gate, and anything you need from me. Then wait for my "go".

6. Run every `php artisan make:*` command the work package lists before writing code, then fill
   in the generated files. Use the old code only as a reference, one file at a time, with
   `git show feat/postgresql-multisite:<path>`.

7. Tests are part of the work, not a later step. Follow STANDARDS.md: write the tests in this
   session — a unit test for every rule, a feature test for every requirement, and three access
   tests for every route (signed out, wrong role, someone else's record). Run them on the test
   database `ifgf_app_test`, with output piped through `tail`.

8. Keep the databases apart: `ifgf_app_test` for tests, `ifgf_app` for development, staging and
   production each with their own database and their own role. Never point tests or this machine
   at the production database, and never copy production data anywhere else.

9. Before you call the work package done, run the gate in STANDARDS.md section 5 in order: full
   test suite, dependency audit, the security and privacy checklists against your changes,
   `/security-review` on the pending changes, a local run-through at phone and desktop width if
   you added a screen, and `php artisan ifgf:smoke` against the Cloudflare staging address once
   staging exists. Tell me plainly what passed, what failed, and what you could not check.

10. When the gate passes, or when you have used about 120k tokens, whichever comes first: stop,
    update TODO.md, CONTEXT.md and MEMORY.md, show me the proposed commit message in my daily-log
    format, and wait. Do not stage or commit until I say yes.
```

## Reusing this for a future module

Steps 1, 2 and 5–10 do not change. Replace the scope paragraph with the new module's PRD, and
the database prefix `ifgf_app` with the module's own. `STANDARDS.md` applies as it is.

## When to change the effort

| Work package | Effort | Why |
|---|---|---|
| WP1 schema, WP2 sign-in and gates, WP2S staging, WP7 import | `high` | A mistake here is inherited by everything after it |
| WP3–WP6, WP8 | `high` | Normal feature work with authorisation in it |
| A restyling pass from Stitch exports, translation passes, document-only sessions | `medium` | Mechanical |

`xhigh` and `max` are not worth their cost here: the plan already fixes the design decisions, so
extra reasoning mostly re-derives them.
