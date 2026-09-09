# TODO

> Item 1 is always the literal next action. Rewritten every session end.
> Hard cap: 1,500 tokens — archive completed items to `MEMORY.md`, do not accumulate them here.

**Branch:** `feat/postgresql-multisite` · **Updated:** 2026-09-09 (session 12)
**Active:** WP 0C-class — PostgreSQL migration and the IFGF application. Opened on owner
instruction while WP 0A's exit gate stands at **4 of 7**; the override is recorded in
`PG_MIGRATION_PLAN.md` §0.

**Plan:** `PG_MIGRATION_PLAN.md` · **Walkthrough:** `USER_GUIDE.md` · **Licensing:** `NOTICE.md`

---

## Pre-flight

Nothing manual. PostgreSQL 17 is a Windows service and auto-starts; credentials are machine-wide in
`~/.ifgf/postgres.env`. **MySQL only needs starting for the WP 0A suite**, which still runs on it:

```
& "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --defaults-file="C:\ProgramData\MySQL\MySQL Server 8.4\my.ini" --console
```

---

## Now

1. **🔴 Fix AUTH-001 and SEC-001.** They now gate **two** public hostnames, and everything below is
   blocked behind them for anything internet-facing. `routes/web.php:71` calls `Auth::routes()`
   again and reopens the registration `:68` disabled; `RegisterController::create()` hard-codes
   `usergroup_id = 3`, the value `Gate::before` grants every ability to, and additionally grants
   every `Permission` row. `EnsureUsher` avoids `Gate` entirely as containment — that is not a fix.

2. **Resolve the 4 import conflicts** with the owner. `select * from ifgf_import_conflicts` —
   one unparseable birthday (`Daftar Jemaat!53`), two cross-format duplicate phones (`!83`, `!166`,
   pairs the legacy system could never detect), one Onsite+Online double-mark (`Absen-TPE!213`).
   These are pastoral judgements, not code fixes.

3. **Plan the QR cutover communication.** All 217 members hold a NEW credential and their old
   Google-Form QR is dead by design — it carried email, phone, name and iCare in readable text.
   Nobody has been told. This is the most visible user-facing change in the whole migration.

## Next

4. **Add a test that `EnsureUsher` fails closed** for an authenticated member without the role.
   The middleware is written and the `usher` role is seeded; nothing yet asserts it refuses rather
   than admits.

5. **Google / Apple sign-in** (P2, `PG_MIGRATION_PLAN.md` §6). Members currently have no login —
   `ifgf_members.user_id` is nullable and waiting. Apple needs a paid developer account (US$99/yr);
   Google alone is a smaller first step.

6. **Port the 6 `DATE_FORMAT()` files to `to_char()`** — the only real MySQL coupling left in app
   code. All 93 upstream migrations already run on PostgreSQL unchanged.

7. **Decide the WP 0A baseline question** (`PG_MIGRATION_PLAN.md` §10, Q1): finish the
   characterization suites on MySQL, or re-pin them against PostgreSQL. Leaving it undecided means
   the exit gate's 4-of-7 describes a driver the application no longer uses.

## Later

8. Member profile editing; QR rotation from the UI (`MemberCredentialService::rotate` exists,
   no button).
9. iCare weekly attendance — the schema supports it (`ifgf_service_occurrences.group_id`), only
   Sunday services are generated.
10. Real Google Calendar: `composer require google/apiclient`, service account in `~/.ifgf/`,
    then `IFGF_CALENDAR_DRIVER=google`. 217 event series ids are already carried and marked
    `pending` for `ifgf:calendar:sync` to confirm.
11. Google Forms ingestion adapter — `ifgf_form_ingestions` and its JSONB/GIN index exist and hold
    the 3,516 legacy log rows; nothing writes new Forms responses into it yet.
12. NFC tags — decide pilot vs all 217 (~US$65). The `nfc_tag` credential type works today.
13. Owner decisions Q2–Q6 in `PG_MIGRATION_PLAN.md` §10.

## Do not

- **Do not seed the demo fixture into `ifgf_cms`.** It shares branches and Sunday dates with the
  imported data; it once put 29 invented rows into the real Q3 report. `seed()` now refuses, and
  `--force` exists only for the test that proves the purge does not over-reach.
- **Do not move the connection/migration setup out of `IfgfTestCase::createApplication()`** into
  `setUp()`. It runs before `DatabaseTransactions` opens its transaction; moving it back silently
  disables test isolation on PostgreSQL and re-introduces transactional-DDL rollback.
- **Do not use `migrate:fresh --path=…`** on a shared database. It drops every table, not the
  path's.
- **Do not remove `LICENSE.upstream-MIT`.** It is the single obligation MIT imposes.
