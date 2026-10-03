# CONTEXT

> Current state only. Rewritten every session end. Hard cap: 1,500 tokens.
> History belongs in `MEMORY.md`. Next actions belong in `TODO.md`.

**Updated:** 2026-10-04 · **Branch:** `dev` (created 2026-10-04 from `feat/postgresql-multisite`
at `51613f8`) · **Stage:** planning finished, no code of PRD 3.0 written yet.

---

## Where things stand

The owner re-scoped the project on 2026-10-04. `PRD.md` 3.0 lists the only features in scope;
`EXECUTION_PLAN.md` splits them into ten work packages, one per session. `STANDARDS.md` is the
quality bar for all of them and for every later module: four separate databases (test,
development, staging, production), tests written with the code, a security and privacy
checklist at every gate. Deployment target: a self-hosted Ubuntu PC behind a Cloudflare Tunnel.

**The working tree is still the GegoSoft ChurchCMS fork** (Laravel 13.24, 93 upstream tables, 13
`ifgf_` tables, about 1,000 PHP and Blade files). WP1 replaces it with a fresh Laravel 13 skeleton
and a 12-table schema. The fork then survives only as a reference on `feat/postgresql-multisite`.

## Why the fork is being retired

A security review on 2026-09-24 found every critical problem inside the upstream code: a live web
installer, an unauthenticated `/api/users`, open registration that creates a full admin, stored
XSS in the admin feedback pages, the debug bar in `require`. None of the features in PRD 3.0 uses
upstream code — the IFGF part was about 85 files whose only tie to upstream was the login table.

## What the target app is

One website, phone first. Everyone is a `Member`; sign-in is an email link or Google; there are no
passwords. Access comes only from team membership: a team with `ability = scan` (ushers), one with
`ability = admin` (IT), or the `leader` role in an iCare team.

Twelve tables: `branches, teams, member_team, households, institutions, members, events,
occurrences, attendances, headcounts, change_requests, import_batches`.

## Facts a session will need

- **Category** is a formula over occupation and education, not age (PRD FR-5).
- **Legacy branch rule:** a scan before 13:00 is Zhongli, after is Taipei. Used only by the import;
  from now on the usher picks the branch.
- **Quarterly review:** monthly = average of weekly, rounded up; quarterly = average of the three
  months, rounded down.
- **Registration form:** 13 fields on two pages, "just visiting" ends after page 1
  (`GAS_INVENTORY.md` §7). 27 iCare groups.
- **Workbook (4):** 233 members, 3,693 scans, 434 headcount rows (`WORKBOOK_INVENTORY.md` §6).
- The legacy system (Google Form + Apps Script) is still the live one. Nothing here is deployed.

## Environment

Windows 11 · PHP 8.4.24 (`php` on PATH) · Composer · Node 22 · PostgreSQL 17.10 as a service ·
credentials machine-wide in `~/.ifgf/postgres.env`. Databases to create in WP1: `ifgf_app`,
`ifgf_app_test`. `ifgf_cms` and `ifgf_cms_test` belong to the fork — untouched.

## Standing hazards

- The fork's holes are real but **not deployed**. Do not spend a session fixing them.
- The workbook holds real member data. It is read from `Downloads`, never copied into the repo.
  Its `URLs Absensi` sheet caches contact details inside formula text — never print formula
  fallbacks from that sheet.
- An untracked `database/seeders/LaratrustSeeder.php` (an upstream stub) sits in the tree; WP1
  asks before removing it.

## Licensing

`LICENSE` = AGPL-3.0 (© 2026 IFGF Taipei Zhongli). `LICENSE.upstream-MIT` stays — the repository's
history derives from GegoSoft's MIT fork. `NOTICE.md` explains the split.
