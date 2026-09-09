# CONTEXT

> Current state only. Rewritten every session end. Hard cap: 1,500 tokens.
> History belongs in `MEMORY.md`. Next actions belong in `TODO.md`.

**Updated:** 2026-09-09 · **Session 12** · **Branch:** `feat/postgresql-multisite`
**Work package:** WP 0C-class, opened on owner instruction. **WP 0A's gate is still 4 of 7** and
its 94 characterization tests remain a **MySQL** baseline — see the hazard below.

---

## What this application now is

A Laravel 13.24 app on **PostgreSQL 17**, replacing the `church-member-management` Google Apps
Script. Two sites, one codebase:

| Site | Local | For |
|---|---|---|
| `member.ifgf.site` | `/app` | register, own QR, own attendance |
| `attend.ifgf.site` | `/usher` | scan, tick a roster, quarterly report |

All five Apps Script features are ported: registration, birthday → Google Calendar, QR/NFC
attendance, two-branch weekly attendance, quarterly report. Three languages (`id` default, `en`,
`zh_TW`).

## Database

**`ifgf_cms` on PostgreSQL 17** — 104 tables: 93 upstream + **11 `ifgf_`** (plus
`ifgf_import_batches` / `ifgf_import_conflicts` = 13). `.env` is `DB_CONNECTION=pgsql`; the MySQL
config is backed up at `.env.backup-mysql-20260908`.

**Credentials are machine-wide**, in `~/.ifgf/postgres.env`, loaded by `bootstrap/global-env.php`
*before* the framework reads `.env`. Entered once per machine, not per clone; cannot be committed.
`config/database.php` prefers `IFGF_PG_*` over `DB_*`.

**Live data:** 217 members, 21 iCare groups, 102 Sunday services (2025-09-28 → 2026-09-13), 3,318
attendance rows, 217 calendar links, 3,516 raw log rows, 217 QR credentials. 4 unresolved import
conflicts in `ifgf_import_conflicts`.

**Test database:** `ifgf_cms_test`. `tests/IfgfTestCase` builds only `database/migrations/ifgf`,
asserts the name contains "test", and chooses the connection in `createApplication()` — before
`DatabaseTransactions` opens its transaction. Do not move that back into `setUp()`.

## Where IFGF code lives

`custompackages/ifgf/church-operations/src/` — models, services (`MemberCredentialService`,
`AttendanceRecorder`, `BirthdaySyncService`, `QuarterlyReportService`, `MemberRegistryService`,
`WorkbookImporter`, `PhoneNormalizer`, `DemoDataService`), the `CalendarGateway` contract and its
fake, and the artisan commands.

Schema is in **`database/migrations/ifgf/`** (application, not package — two WP 0A gate tests assert
the package holds no migrations). The path is registered from the package provider; the
subdirectory is load-bearing, because Laravel globs a migration path non-recursively.

Controllers: `app/Http/Controllers/{Usher,MemberApp,Ifgf}/`. Views: `resources/views/ifgf/`.

## Commands

```
ifgf:import:workbook <path> [--commit]   # dry run by default
ifgf:credentials:issue                    # QR for anyone lacking one
ifgf:demo:seed | ifgf:demo:purge          # fixture; seed refuses if real members exist
ifgf:calendar:sync | ifgf:calendar:purge-demo
ifgf:demo:preview                         # render pages to static HTML
db:seed --class=IfgfDevArenaSeeder        # usher@ifgf.test / member@ifgf.test
```

## 🔴 Standing hazards

- **AUTH-001 + SEC-001 are unfixed** and now gate **two** public hostnames.
  `routes/web.php:71` reopens the registration `:68` disabled; `RegisterController` hard-codes
  `usergroup_id = 3`, the value `Gate::before` grants every ability. **`EnsureUsher` deliberately
  does not use `Gate`** for that reason — it is containment, not a fix.
- **Prototype mode** (`IFGF_PROTOTYPE_MODE`) opens the usher site with no login. Two locks: the flag
  *and* `APP_ENV=local`, re-checked in the middleware.
- **The WP 0A suite still runs on MySQL** and is a MySQL baseline. The ifgf_ suite runs on
  PostgreSQL. They are deliberately separate.
- **Google Calendar is the in-memory fake** unless `IFGF_CALENDAR_DRIVER=google`. Tests force the
  fake regardless of environment.
- **Every member's old Google-Form QR is dead.** 217 new credentials issued; nobody has been told.

## Licensing

`LICENSE` = **AGPL-3.0** (IFGF-authored work, © 2026 IFGF Taipei Zhongli).
`LICENSE.upstream-MIT` = GegoSoft's MIT notice, byte-identical, **must never be removed** — it is
MIT's only obligation and what makes the AGPL choice lawful. `NOTICE.md` explains the split.

## Environment

PHP 8.4.24 · Laravel 13.24.0 · PostgreSQL 17.10 (Windows service, auto-start) · `pdo_pgsql` enabled
in `C:\php\8.4\php.ini` (backup `php.ini.bak-20260907`). MySQL 8.4 is still installed for the WP 0A
suite and needs a manual start.

**218 tests, 700 assertions, 0 failed.**
