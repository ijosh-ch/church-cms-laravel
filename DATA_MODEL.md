# DATA_MODEL — the database as built

**Reviewed:** 2026-10-21 · **Branch:** `dev` at `ef44257` plus the uncommitted work in the tree ·
**Sources:** `database/migrations`, `app/Models`, `app/Enums`, and the schema of `ifgf_app`
(structure only; no row was read). Where a document and the code disagree, this file describes
the code and lists the difference in §6.

## 1. Summary

The app has 12 domain tables, the framework's `sessions`, and five framework tables from the
cache and jobs migrations (`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`). `members`
is the centre and also the sign-in model, with no `users` table. Attendance hangs off
`events → occurrences → attendances`, with one `headcounts` row per occurrence. Every fixed list
is a short string column backed by a PHP enum, as PRD §4 says. Five fixed lists still have no
enum (§6). The database has **no CHECK constraints**. Its one partial index keeps team
membership unique while `left_at` is null. The live schema matches the migrations, except the
uncommitted migration `2026_10_21_141217_add_profile_reminded_at_to_members`, which is **pending**
in `ifgf_app`. The biggest risks are these:

- Deleting an event or occurrence cascades into the attendance history.
- Nothing in the schema supports the anonymisation that PRD §5.14 requires.
- Events have no date, so the app cannot tell whether a `once` or `annual` event happens today.

## 2. Entity-relationship diagram

The ERD lives in [README.md → Data model](README.md#data-model), so there is one copy to keep up
to date. It draws all 12 domain tables and `sessions`, but not `cache`, `cache_locks`,
`jobs`, `job_batches` or `failed_jobs`.

## 3. Class diagram

The class diagrams live in [README.md → Class diagram](README.md#class-diagram):
1. the domain models and enums, which mirror the ERD;
2. the controllers and services;
3. the form requests and validation rules;
4. the background jobs, the calendar, the commands and the mail.

The README also explains where the class diagram and the ERD differ: the pivot table, the
framework tables, the enums and the classes that have no table.

## 4. Table inventory

`id` is `bigint` identity unless stated. "ts" means `created_at`/`updated_at` (`timestamp(0)
without time zone`, nullable). All timestamps are written in UTC (`config/app.php` timezone is
`UTC`). `occurs_on` is the Asia/Taipei calendar date.

| Table | Columns (type · null · default) | Indexes | Foreign keys (ON DELETE) |
|---|---|---|---|
| `branches` | code varchar(8) · name varchar · name_zh varchar null · gmaps_url varchar null · is_active bool default true · ts | PK; UQ(code) | — |
| `teams` | type varchar(16) [TeamType] · name · branch_id null · meeting_weekday smallint null · ability varchar(16) null [TeamAbility] · is_active bool default true · ts | PK; (type) | branch_id → branches **SET NULL** |
| `households` | name varchar · ts | PK | — |
| `institutions` | country varchar(2) · code varchar(32) null · name_en · name_zh null · ts | PK; UQ(country, code) | — |
| `members` | public_ref uuid · household_id null · household_role varchar(16) null [HouseholdRole] · branch_id · icare_team_id null · institution_id null · full_name · chinese_name null · email null · phone varchar(20) null · line_id null · birthday date null · domicile_taiwan null · occupation null · education_level varchar(16) null [EducationLevel] · school_or_company null · category varchar(16) [MemberCategory] · status varchar(16) [MemberStatus] · qr_token_hash varchar(64) null · qr_token_enc text null · short_code varchar(8) null · calendar_event_id null · calendar_synced_at null · profile_reminded_at null *(pending)* · email_verified_at null · remember_token varchar(100) null · legacy_ref null · ts · deleted_at null | PK; UQ(public_ref); UQ(email); UQ(qr_token_hash); UQ(short_code); (phone); (status); (legacy_ref) | household_id → households **SET NULL**; branch_id → branches **RESTRICT**; icare_team_id → teams **SET NULL**; institution_id → institutions **SET NULL** |
| `member_team` | member_id · team_id · role varchar(16) [TeamRole] · left_at null · ts | PK; partial UQ(member_id, team_id) WHERE left_at IS NULL; (team_id) | member_id → members **CASCADE**; team_id → teams **CASCADE** |
| `events` | type varchar(16) [EventType] · name · branch_id null · team_id null · recurrence varchar(16) [Recurrence] · weekday smallint null · is_active bool default true · ts | PK; (type) | branch_id → branches **SET NULL**; team_id → teams **SET NULL** |
| `occurrences` | event_id · occurs_on date · locked_at null · ts | PK; UQ(event_id, occurs_on) | event_id → events **CASCADE** |
| `attendances` | occurrence_id · member_id null · guest_name null · mode varchar(16) [AttendanceMode] · method varchar(16) [AttendanceMethod] · category varchar(16) null [MemberCategory] · recorded_by null · recorded_at · ts | PK; UQ(occurrence_id, member_id); (member_id) | occurrence_id → occurrences **CASCADE**; member_id → members **SET NULL**; recorded_by → members **SET NULL** |
| `headcounts` | occurrence_id · adult, college, teens_youth, kids, online, total integer default 0 · reporter varchar null · source varchar(16) *(no enum)* · ts | PK; UQ(occurrence_id) | occurrence_id → occurrences **CASCADE** |
| `change_requests` | member_id · requested_by · changes jsonb · token_hash varchar(64) · status varchar(16) *(no enum)* · expires_at · decided_at null · ts | PK; UQ(token_hash); (status) | member_id → members **CASCADE**; requested_by → members **CASCADE** |
| `import_batches` | source, mode, status varchar(16) *(no enums)* · file_hash varchar(64) · summary jsonb null · created_by null · ts | PK; (file_hash) | created_by → members **SET NULL** |
| `sessions` | id varchar PK · user_id bigint null · ip_address varchar(45) null · user_agent text null · payload text · last_activity int | PK; (user_id); (last_activity) | none: `user_id` is not a foreign key |
| `cache`, `cache_locks` | key PK · value / owner · expiration bigint | (expiration) | — |
| `jobs`, `job_batches`, `failed_jobs` | Laravel defaults | jobs(queue); failed_jobs UQ(uuid), (connection, queue, failed_at) | — |

**Models.** Every model lists its fillable attributes with `#[Fillable]`. These are deliberately
**not fillable**:

- `members`: `category` (computed in `saving`), `qr_*`, `short_code`, `calendar_*`,
  `email_verified_at`, `legacy_ref`, `public_ref`.
- `teams.ability`.
- `occurrences.locked_at`.
- `change_requests`: `token_hash` and `status`.

**Hidden:** `Member` hides `qr_token_hash`, `qr_token_enc` and `remember_token`; `ChangeRequest`
hides `token_hash`. **Encrypted:** `members.qr_token_enc` (cast) and every `sessions.payload`.
**Soft deletes:** `Member` only. **UUID:** `Member` uses `HasUuids` on `public_ref`, which is also
its route key. `ImportBatch` has no factory.

**Relations and their database support.** Every `belongsTo` has a matching foreign key. The
children reached through a relation are often **not indexed**, because PostgreSQL does not index
foreign keys by itself:

| Relation | FK | Index on the child column |
|---|---|---|
| Branch→members, teams, events | yes | **no** (`members.branch_id`, `teams.branch_id`, `events.branch_id`) |
| Household→members | yes | **no** |
| Institution→members | yes | **no** |
| Team→icareMembers (`icare_team_id`) | yes | **no** |
| Team↔Member (`member_team`, pivot `role`, `left_at`, ts; filtered to `left_at IS NULL`) | yes | yes: partial UQ leads with member_id; (team_id) |
| Team→events | yes | **no** |
| Event→occurrences | yes | yes (UQ leads with event_id) |
| Occurrence→attendances | yes | yes (UQ leads with occurrence_id) |
| Occurrence→headcount | yes | yes (UQ) |
| Member→attendances | yes | yes |
| Attendance→recorder | yes | **no** |
| Member→changeRequests / ChangeRequest→requester | yes | **no** / **no** |
| ImportBatch→creator | yes | **no** |

## 5. Queries the app runs, against the indexes

| Query (file) | Index used | Verdict |
|---|---|---|
| Attendance by occurrence and member (`Services/AttendanceRecorder::find`) | UQ(occurrence_id, member_id) | good |
| Attendance list of one occurrence, by `recorded_at` (`AttendanceTableController`) | UQ prefix occurrence_id | good |
| Member by `qr_token_hash` / `short_code` | UQ | good |
| Member by email (registrar, Google, `ifgf:admin:grant`) | UQ(email), case-sensitive | good, as long as every writer lower-cases |
| Member by phone (`Rules/NotRegistered`, registrar) | (phone) | good |
| Member by `lower(trim(line_id))` (`Rules/NotRegistered`) | **none**, full scan | needs an expression index |
| Occurrence by `event_id` + `occurs_on` (`firstOrCreate`) | UQ | good |
| Occurrence by date via `whereDate` + event type and branch (`AttendanceTableController`) | UQ when the planner starts from `events`; `whereDate` adds a `::date` cast | acceptable; use `where` |
| Sunday Service event by type + branch + active (`AttendanceRecorder::occurrenceFor`) | (type) | fine at this size; nothing stops two active ones |
| Roster and admin search, `full_name`/`chinese_name`/`email ILIKE '%q%'` | **none**, full scan | fine at ~250 members; `pg_trgm` only past thousands |
| Members missing profile data (`ifgf:...` reminder: `profile_reminded_at`, `status`, `created_at`) | (status) | fine at this size |
| Members by `icare_team_id` (admin filter; later FR-9) | **none** | add when FR-9 lands |

## 6. Differences from the PRD

1. **Events have no date** in either the PRD or the code (finding M3).
2. **WhatsApp number and Line ID are unique** in PRD FR-1 (amended 2026-10-15, in the
   uncommitted `PRD.md`), but only `Rules/NotRegistered` enforces it. The database has no unique
   constraint. That is partly on purpose: a child under 13 may share a number, and an IT admin's
   edit is not checked.
3. **`phone_country`** is written in FR-1 as if it were a column. In the code it is only a form
   field.
4. **`members.profile_reminded_at`** is in the code (uncommitted migration, pending in
   `ifgf_app`) but not in PRD §4.
5. **Enums missing:** PRD §4 says every fixed list is enum-backed, but `headcounts.source`,
   `import_batches.source`/`mode`/`status` and `change_requests.status` are plain strings.
   `ChangeRequest` has only a `PENDING` constant.
6. **The PRD §4 ERD leaves out relations the code has:** branches→teams,
   attendances.recorded_by, change_requests.requested_by, import_batches.created_by, and
   sessions.user_id.
7. **Framework tables:** PRD §4 names `cache` and `jobs`, but the migrations also create
   `cache_locks`, `job_batches` and `failed_jobs`.
8. **`institutions` unique(country, code)** does not stop duplicates when `code` is null, because
   PostgreSQL treats NULLs as distinct.
9. **"Who entered it" for a headcount** is not in the PRD text. FR-12 only says ushers and IT
   admins enter one per occurrence, and `headcounts` has only free-text `reporter`. Attendances
   *do* record `recorded_by` (FR-7).
10. Everything else in PRD §4 matches the code: columns, the partial unique index on
    `member_team`, `public_ref`, the QR columns, `unique(event_id, occurs_on)`,
    `unique(occurrence_id, member_id)`, and timestamps on every table.

## 7. Personal data by table (STANDARDS §4)

| Table | Columns holding personal data |
|---|---|
| `members` | full_name, chinese_name, email, phone, line_id, birthday, domicile_taiwan, occupation, education_level, school_or_company, institution_id, household_id + household_role, branch_id, icare_team_id, status, category (derived from age), legacy_ref (links to the workbook row), calendar_event_id. Credentials: qr_token_hash, qr_token_enc, short_code, remember_token |
| `households` | name (usually a family name) |
| `attendances` | member_id with recorded_at and mode: a record of church attendance; guest_name (a non-member's name) |
| `headcounts` | reporter (a person's name, free text) |
| `change_requests` | changes (proposed personal values, jsonb) |
| `import_batches` | summary: the PRD allows row references only, but the schema does not enforce it |
| `sessions` | ip_address, user_agent; payload is encrypted |
| `jobs`, `failed_jobs` | serialised jobs; `failed_jobs.exception` may echo values |
| `cache` | throttle keys can contain an IP address |
| `branches`, `teams`, `events`, `occurrences`, `institutions` | none |

**Sequential ids (STANDARDS §3 item 5).** No URL or QR carries a sequential id today:

- Members are addressed by `public_ref` (`/admin/members/{member}`).
- The QR is a random 256-bit token, and the short code is random.
- `?date=` is the only other URL value.
- Search filters live in the session.
- Team ids travel only in POST bodies.

Of the tables a URL or email will reach later, only `change_requests` has a random token
(`token_hash`) and `members` has `public_ref`. **`teams`, `events` and `occurrences` have
neither**, and FR-8, FR-11 and FR-14 pages will need to address them.

## 8. Findings, ranked

### Must fix before production

- **M1. A delete wipes the attendance history.**
  - **Problem:** `occurrences.event_id`, `attendances.occurrence_id` and
    `headcounts.occurrence_id` are all `ON DELETE CASCADE`. Deleting one event erases every
    occurrence, scan and headcount under it, years of history included. This contradicts PRD
    §5.14 ("attendance counts stay").
  - **Proposal:** make all three `RESTRICT`, and retire an event with `is_active = false`.
- **M2. Nothing in the schema supports anonymisation (§5.14).**
  - **Problem:** `SoftDeletes` alone keeps every personal column. A soft-deleted member's email
    still holds the unique index, so if that person registers again, `MemberRegistrar` catches
    the violation and treats them as registered, and they can never come back. Personal data also
    sits outside `members`: `change_requests.changes`, `sessions` (no FK, so nothing cascades),
    `headcounts.reporter` and `failed_jobs`. `full_name` is NOT NULL, so anonymising needs a
    placeholder.
  - **Proposal:** define anonymise as one transaction:
    1. Null the personal columns and set a placeholder name.
    2. Clear `qr_*`, `short_code`, `calendar_event_id` (after removing the event),
       `legacy_ref`, `remember_token` and `email_verified_at`.
    3. Set `left_at` on the member's `member_team` rows.
    4. Delete their `change_requests` and `sessions`.
    5. Soft-delete the row.

    `attendances` keep their `member_id`, which points at the anonymised row. Never hard-delete
    a member.
- **M3. The app cannot tell whether a `once` or `annual` event happens today.**
  - **Problem:** FR-7's last line ("any other active event for today") needs it, but `events`
    has only `weekday`, so a `once` or `annual` event either always shows or never shows.
  - **Proposal:** add `starts_on date null` and `ends_on date null`:
    - `once`: `starts_on` is the day.
    - `annual`: today matches the month and day of `starts_on`.
    - `weekly`: `weekday`, optionally within the range.

### Should fix

- **S1. Who entered a headcount is not recorded.** `headcounts.reporter` is free text. Add
  `recorded_by` (FK to members, SET NULL) and `updated_by` for corrections, and keep `reporter`
  for the names from the LINE and workbook imports.
- **S2. Nothing stops two active Sunday Services for one branch.** If there are two, the scans
  split across two occurrences, because `occurrenceFor` takes whichever comes first. Add a partial
  unique index on `events(branch_id)` where `type = 'sunday_service'` and `is_active`.
- **S3. Line ID lookup is a full scan and its uniqueness can race.** Add a plain expression index
  on `lower(trim(line_id))`. It should not be unique, because an IT admin's edit is unchecked.
  Accept the small race between two simultaneous registrations, or catch it the way
  `MemberRegistrar` already does for email.
- **S4. Email uniqueness depends on case.** Make the unique index `lower(email)`, so the coming
  workbook import and sheet sync cannot store a differently-cased duplicate. Today every writer
  lower-cases, but the importer does not exist yet.
- **S5. A hard-deleted leader takes their requests with them.** `change_requests.requested_by` is
  `CASCADE`. Make it nullable with `SET NULL`.
- **S6. Some fixed lists have no enum.** Add PHP enums for `headcounts.source`,
  `import_batches.source`/`mode`/`status` and `change_requests.status`, as PRD §4 requires.
- **S7. `short_code` is not hidden.** It works like a password for attendance (the typed
  fallback), but `Member` does not list it in `#[Hidden]`, so it appears in `toArray()` and JSON.
- **S8. No CHECK constraints.**
  - `attendances`: `member_id IS NOT NULL OR guest_name IS NOT NULL`.
  - `headcounts`: every count `>= 0`. `unsignedInteger` is not enforced on PostgreSQL.
  - `weekday` and `meeting_weekday`: between 0 and 6 (and decide which convention).
- **S9. Future pages will need a public reference for teams, events and occurrences.** Add a
  `public_ref` to each before FR-8, FR-11 or FR-14 adds a route that names one.
- **S10. Some foreign-key columns have no index.** Index `members.icare_team_id` (FR-9 and the
  leader's roster), `members.household_id` (FR-3) and `change_requests.member_id`. The rest are
  harmless at this size.

### Note

- **N1. Email for a child.** Email is unique and required on the public form. A child without
  their own address can register only through FR-3 family rows (not built), where email is
  optional. The database already allows a null email. If a child types a parent's email, the
  request ends silently as "already registered" and the parent gets the sign-in link. Decide
  whether that is acceptable or whether children register only through a parent.
- **N2.** `members.phone` is indexed but not unique, which is correct for FR-1's children rule.
  The application rule is the only guard for adults.
- **N3.** `headcounts.total` is stored rather than computed, so it can disagree with the sum. The
  legacy sheets typed it by hand. Keep it, but show a mismatch.
- **N4.** `whereDate('occurs_on', …)` on a `date` column adds a cast; use `where`. An index on
  `occurrences(occurs_on)` will help the FR-11 grid (a date range across events).
- **N5.** The `ILIKE '%q%'` searches cannot use an index. That is fine below a few thousand
  members.
- **N6.** Timestamps are `without time zone` and depend on `app.timezone = UTC` staying unchanged.
- **N7.** `Member::teams()` filters out rows that have a `left_at`, so team history cannot be
  reached through Eloquent. Add a `teamHistory()` relation when it is needed.
- **N8.** The migration `add_profile_reminded_at_to_members` is uncommitted and still pending in
  `ifgf_app`, but the reminder command already reads that column.
- **N9.** Deleting a team sets `events.team_id` to null, which leaves an iCare event with no team.
  Prefer `is_active = false` for teams too.
