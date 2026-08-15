# Testing plan — Release 1

**Written 2026-08-10** · Scope: Release 1 (`PRODUCTION_PATH.md`)
**Current state: 2 real test files.** `tests/Feature/Admin/MemberImportCharacterizationTest.php`
and the untracked `tests/Feature/Attendance/TimezoneCharacterizationTest.php`. The latter verifies
configuration consistency, not the attendance-session workflow.

CI already runs `composer validate --strict`, `composer audit`, a PSR-4 check,
`migrate:fresh --seed`, and `php artisan test` on Ubuntu with PHP 8.4. **The harness is ready;
there is almost nothing in it.**

---

## Part 1 — The debt: seven characterization suites

Laravel 10 → 11 → 12 → 13 was crossed without a behavioural baseline. These suites establish, after
the fact, what the application actually does now. **Write them before any new feature work.**

Characterization means *capture what is, not what should be*. If a behaviour looks wrong, write a
test asserting the wrong behaviour and open a note — do not fix it here. Fixing and characterizing
in the same pass destroys the baseline's value.

| # | Suite | Asserts | Priority |
|---|---|---|---|
| 1 | **Auth** | Login, logout, password reset, email verification (the only signed-URL path), session lifetime, throttling | Highest — everything else assumes it |
| 2 | **Roles + permissions** | Laratrust 8 role checks, `permission:*` middleware on admin routes, `usergroup_id` legacy paths **as they behave today**, direct `permission_user` grants | Highest — 33 files, biggest WP 0C risk |
| 3 | **Member profile** | Create, edit, view, search, soft delete; `Userprofile` relations; export | High |
| 4 | **Attendance session** | `openSession`, `scan` 409 on duplicate, `lock` 403 on locked, `unlock`, `markAttendee`, `searchMember`, `removeAttendee`, church scoping, `EventManager` assignment scope | **Highest — 60% of FR-04 lives here** |
| 5 | **Member QR / membership card** | Card render, current payload shape (pre-redesign), admin vs member access | High |
| 6 | **Groups** | Group CRUD, `GroupLink` membership, leader visibility | Medium |
| 7 | **Event management** | Event CRUD, manager assignment, recurring-event behavior, church scoping | High |
| 8 | **Birthday routes** | Current route authentication, role behavior, and any management/read-only split | High |
| 9 | **Exports** | Member export, attendance export, permission checks | Medium |
| 10 | **Queues and notifications** | Dispatch, serialization, retry-facing behavior, and unavailable-channel failures | Medium |
| 11 | **Private media and storage** | Current disks, authorization, URL exposure, upload/delete behavior | High |

**Target: ~80–120 tests across the eleven.** Not a coverage percentage — coverage rewards touching
lines, and this needs to pin behaviour. The test that matters is the one that fails when someone
changes attendance scoping by accident.

**Sequencing:** 2 and 4 first. They are the highest-risk surfaces (authorization replacement, and
the code Phase 1B extends) and the ones where a silent regression would be least visible.

### Two assertions worth writing explicitly

```
- A MISSING attendance row is NOT absence.
  EventAttendee is presence-only today. After the status column lands, "no row" must
  keep meaning "not recorded" and never be read as "absent". Write this before the
  column exists so the migration cannot quietly change its meaning.

- A leader with no assignment cannot record attendance for an occurrence.
  Assert the denial, not just the grant. Navigation hiding is not authorization
  (build.md SECURITY 11).
```

---

## Part 2 — Test layers per phase, going forward

`build.md` L521–532 requires ten layers. Scoped to Release 1:

| Layer | Applies from | Release 1 content |
|---|---|---|
| **Unit** | WP 0C | Phone E.164 normalisation, name normalisation, duplicate scoring, QR token generation and rotation |
| **Feature** | WP 0C | Routes, authorization, validation, audit, idempotency, role replacement, **legacy authorization bypass attempts** |
| **Database** | WP 0C | Constraints, transactions, concurrent writes, migration backfill, rollback, provenance. **MySQL, not SQLite** — generated columns, locking and collation behave differently |
| **Contract** | Phase 1D | Google Calendar adapter against a fake. No live Calendar, ever |
| **Browser (Dusk)** | Phase 1A | Member portal, own-profile read-only, own QR, **denied direct URLs**, leader attendance, 360px mobile paths |
| **Import fixtures** | WP 0C | Anonymized workbook fixtures, all-row reconciliation |
| **Load** | Phase 1E | Sunday check-in burst |
| **Restore** | Phase 1E | Database, private media, config, keys |
| Edge / recognition | *Release 3* | Not applicable to Release 1 |

### Concurrency tests are not optional

Three invariants can only fail under concurrency, and each is a silent data-corruption path:

1. **Final-admin protection** — two simultaneous role changes must not remove the last admin.
2. **One active primary iCare** — two simultaneous transfers must not create two.
3. **Duplicate scan** — two ushers scanning the same member must produce one record.

Test these with real concurrent transactions against MySQL, not sequential calls.

---

## Part 3 — Before production

| Test | Why | Gate |
|---|---|---|
| Sunday check-in load | ~200 arrivals in a window, plus QR resolution and roster lookups | 1E.2 |
| Backup restore | RPO 24h, RTO 4h. **Untested backups are not backups** | 1E.2 |
| Parallel-run reconciliation | Laravel attendance counts vs the sheet, 2–4 Sundays | 1E.3 |
| QR reissue rehearsal | Every member card changes; verify the old token stops resolving | 1E.3 |

---

## Part 4 — What "enough" means

Not a coverage number. Release 1 testing is sufficient when:

1. Every characterization suite in Part 1 passes on `ifgf/main`.
2. Every acceptance row in PRD §11 for Release 1 features has a test.
3. The three concurrency invariants each have a failing-then-passing test.
4. A leader with no assignment is **denied** — asserted directly, not inferred from hidden nav.
5. `migrate:fresh --seed` works on an empty database **and** an anonymized legacy snapshot.
6. The full suite runs green in CI from a clean checkout on Linux.

---

## Part 5 — What is actually left to finish

**Close WP 0A** — the only thing between here and feature work:

| | Task | Est. |
|---|---|---|
| 1 | **Characterization suites 1–11** (Part 1) | 4–6 sessions |
| 2 | Scaffold `custompackages/ifgf/church-operations` — still missing | 1 |
| 3 | Provider smoke tests from a clean checkout | 1 |
| 4 | Merge `contrib/laravel-supported-platform` → `ifgf/main` **after 1 passes** | 0.5 |
| 5 | Upstream merge rehearsal — recurring, not one-off | 0.5 |
| 6 | WP 0A exit gate review | 0.5 |

**Then** `tools/PROMPTS.md` P1–P7: WP 0C → WP 0D → 1A → 1B → 1C → 1D → 1E.
**Remaining to production: ~55–70 sessions.**

### 🔴 Timezone defect — fix before any attendance data is written or imported

Found 2026-08-10 via `php artisan about`:

```
Timezone .................. Asia/Kolkata
```

Root cause — `.env.example` carries **two competing variables**, and the one that wins is wrong:

| File | Line | Value | Effect |
|---|---|---|---|
| `.env.example` | 62 | `TIMEZONE=Asia/Kolkata` | **This one wins** |
| `.env.example` | 92 | `APP_TIMEZONE=UTC` | **Dead — never read** |
| `config/app.php` | 71 | `'timezone' => env('TIMEZONE','UTC')` | Reads `TIMEZONE`, ignores `APP_TIMEZONE` |

Upstream ChurchCMS is an Indian project; `.env` was created from `.env.example`, so the church
inherited Asia/Kolkata. **Taipei is UTC+8, Kolkata is UTC+5:30 — everything the application writes
is 2.5 hours behind Taipei wall-clock.**

Consequences:

- `now()` returns Kolkata local time, so `scanned_at`, `created_at` and `attendance_date` are all
  written in the wrong zone.
- `attendance_date` can land on the **wrong calendar day** near midnight, silently misfiling a
  service.
- It violates `build.md` TECHNICAL BASELINE 3, which requires **UTC storage**.
- The legacy Apps Script manifest uses `Asia/Taipei`. Importing legacy timestamps into a
  Kolkata-configured application shifts every one of them.

### Fix — owner decision 2026-08-10: `Asia/Taipei`, not UTC

**Approved deviation from `build.md` TECHNICAL BASELINE 3 ("UTC storage").** Defensible here:

- **Taiwan has no daylight saving**, so UTC+8 is constant year-round. The main argument for UTC
  storage — ambiguous and skipped local times at DST transitions — does not apply.
- Both branches are in the same zone.
- The legacy Apps Script manifest already uses `Asia/Taipei`, so imported timestamps map 1:1 with
  no shift and no conversion step to get wrong.

Record this as an approved deviation in `UPSTREAM.md` or a `build.md` amendment. Revisit only if a
branch outside UTC+8 is ever added.

### The change set — both halves are required

```
# .env
TIMEZONE=Asia/Taipei
```

```php
// config/database.php — inside the 'mysql' connection array
'timezone' => '+08:00',
```

**Setting only the first is a silent-corruption bug.** `config/database.php` currently sets no
connection timezone, so MySQL uses the *server* default, and the schema mixes column types:

| Type | Count | MySQL behaviour |
|---|---:|---|
| `timestamp()` — incl. `scanned_at`, `locked_at` | 23 | **Converts** to/from UTC using the *session* timezone |
| `dateTime()` | 10 | Stores the literal string, **no conversion** |
| `date()` — incl. `attendance_date` | 8 | Date only; which day it lands on follows the PHP timezone |

If PHP and MySQL disagree, the `timestamp` columns shift and the `dateTime` columns do not — half
the data looks right, which makes this the hardest class of timezone bug to spot.

Use the fixed offset `+08:00` for the connection value, **not** `'Asia/Taipei'`. Named zones
require MySQL's timezone tables to be loaded, which a default install usually lacks; and with no
DST in Taiwan a fixed offset is exactly correct and cannot drift.

Also remove or reconcile the dead `APP_TIMEZONE=UTC` line in `.env.example`. Two variables where
only one is read is a trap for the next person who "fixes" the timezone by editing the wrong one.

**Add characterization assertions** in suite 4 (attendance session), where the consequence is
worst:
- the configured application timezone is `Asia/Taipei`
- the MySQL connection timezone is `+08:00`
- a `timestamp` column and a `dateTime` column written in the same request round-trip to the
  same wall-clock value

**Urgency: before WP 0C.** Once attendance is imported or written, wrong-zone timestamps are wrong
at rest and need a data migration to correct rather than a config change.

### Three loose ends

- **`database/schema/mysql-schema.sql` is untracked** (114 KB, generated 2026-08-11). Decide:
  commit it so CI skips replaying 93 migrations, or gitignore it. Note the repo already has a
  root `mysql-schema.sql` — two schema files with the same name in different places will confuse
  a future session. Rename or remove one.
- **PHP 8.3 was deleted**, so there is no PATH-reorder rollback. Acceptable now that 8.4 is
  verified, but it means a PHP-level problem needs a reinstall rather than a switch. Record it in
  the 1E rollback plan.
- **`AGENTS.md` and `CLAUDE.md` disagree** on which tool ran Session 2 (`CLAUDE.md` flags this
  itself). One attribution is wrong. Settle it — the tool-capability table depends on it.
