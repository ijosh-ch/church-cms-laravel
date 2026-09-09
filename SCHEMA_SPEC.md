# Schema specification — 21 `ifgf_` tables, their models, and the service contracts

**Part 1 (tables) written 2026-08-11 in Cowork. Part 2 (models + services) written 2026-08-21.**
Fill-pass source for the whole of `tools/GENERATION_MANIFEST.md`. Review this **before** it becomes
21 migrations and 36 classes — a wrong column type is cheap now and expensive later.

**Part 1 was amended on 2026-08-21 in four places** while specifying the services against it; each
is marked `AMENDED §20.0 A<n>` at the line and argued in **§20.0**. One of them — A3, absence —
changes what the schema is able to represent. **Three owner decisions (D4, D5, D6) are open in §24.**

Sources: `PRD.md` §4 and FR-01…FR-12 · `WORKBOOK_INVENTORY.md` · `GAS_INVENTORY.md` ·
existing schema read directly at `7d7654b`.

---

## 🔴 Rule 0 — foreign key widths. Read this first.

**The upstream schema uses mixed key widths.** Verified directly:

| Table | PK | MySQL type |
|---|---|---|
| `users`, `userprofiles`, `events`, `church`, `groups`, `group_links` | `increments('id')` | **INT UNSIGNED** |
| `event_attendance_sessions`, `event_attendees` | `bigIncrements('id')` | **BIGINT UNSIGNED** |

**`$table->foreignId('user_id')` creates BIGINT UNSIGNED and its FK to `users.id` will fail** with
errno 150, incompatible column types. This would have broken almost every table below.

```php
// FK to users / userprofiles / events / church / groups / group_links
$table->unsignedInteger('user_id');
$table->foreign('user_id')->references('id')->on('users');

// FK to event_attendance_sessions / event_attendees
$table->unsignedBigInteger('session_id');

// FK between two ifgf_ tables — both are new and use id(), so this is safe
$table->foreignId('branch_id')->constrained('ifgf_branches');
```

**New `ifgf_` tables use `$table->id()`** (BIGINT UNSIGNED) for their own primary key. Only FKs
*pointing at upstream tables* need the narrow type.

## Rule 1 — conventions applied to every table

- `$table->timestamps()` on all, `softDeletes()` where noted.
- **No MySQL enums** (`build.md` TECHNICAL BASELINE 6). Controlled values are `string` columns
  validated in the application, or FKs to a lookup table.
- Timestamps are `timestamp`, **stored in UTC** — `TIMEZONE=UTC`, MySQL connection `+00:00`.
  Settled 2026-08-15 (UP-009, `CONTEXT.md`); this line previously said `Asia/Taipei` / `+08:00`
  and was **wrong**. The standing campaign prompt still carries the old value — **escalated, not
  silently applied: see §20.0 A0.**
  **Any calendar day derived from an instant must be converted to the branch timezone first**
  (`ifgf_branches.timezone`, `ifgf_event_definitions.timezone`). The `date()` columns are where
  that rule gets broken.
- Public identifiers are opaque, never sequential (TECHNICAL BASELINE 7).
- Every table carrying imported data gets `import_batch_id` nullable → `ifgf_import_batches`.
- `church_id` is **omitted**: single-tenant per PRD §13.1.1. Branch is the IFGF axis.

---

## 1. `ifgf_branches`

Seeded by import, not seeder (PRD §13.1 seed decision).

```php
$table->id();
$table->string('code', 8)->unique();            // TPE, ZL
$table->string('name');                          // IFGF Taipei / 台北
$table->string('name_zh')->nullable();
$table->string('timezone', 64)->default('Asia/Taipei');   // FR-03.9 explicit IANA
$table->string('gmaps_url')->nullable();
$table->boolean('is_active')->default(true);
$table->unsignedInteger('display_order')->default(0);
$table->timestamps();
```

Workbook: `Domisili Gereja IFGF`, 2 values, 99.5% filled.

## 1b. `ifgf_education_levels` — owner decision 2026-08-11

Ordered lookup, not free text. The **rank** is the point: it makes "at least a bachelor's degree"
a comparison rather than a string match, and it sorts correctly in reports.

```php
$table->id();
$table->string('code', 16)->unique();            // sd, smp, sma_smk, diploma, s1, s2, s3
$table->string('name');                           // Indonesian label as members know it
$table->string('name_en')->nullable();            // English label
$table->unsignedSmallInteger('rank');             // ordinal — 10, 20, 30 … leave gaps
$table->boolean('is_active')->default(true);
$table->timestamps();
$table->unique('rank');
```

Full ladder, including levels no current member holds:

| rank | code | name | name_en |
|---:|---|---|---|
| 10 | `sd` | SD | Elementary school |
| 20 | `smp` | SMP | Junior high school |
| 30 | `sma_smk` | SMA/SMK | Senior high / vocational |
| 40 | `diploma` | Diploma (D1–D4) | Diploma |
| 50 | `s1` | S1 | Bachelor's degree |
| 60 | `s2` | S2 | Master's degree |
| 70 | `s3` | S3 | Doctoral degree |

Workbook distribution maps 1:1 with no unmatched values — S1 135, SMA/SMK 38, S2 12, Diploma 7,
S3 4, SMP 2, SD 1. **Every one of the 199 filled rows resolves**, so the import needs no
`other` bucket for this field.

**Rank in tens, not ones.** Inserting a level later — a professional certificate between SMA and
Diploma, say — is then a single insert rather than a renumbering migration.

## 1c. `ifgf_member_categories` — same argument, stronger case

Not asked for, but it is the same problem and the case is better: `Kategori` has exactly 4 values,
**drives the Absen grid's header totals block**, and is the primary reporting axis.

```php
$table->id();
$table->string('code', 24)->unique();            // adult, college, teens_youth, kids
$table->string('name');                           // Adult, College, Teens and Youth, Kids
$table->unsignedSmallInteger('rank');             // display order in reports
$table->boolean('is_active')->default(true);
$table->timestamps();
```

Workbook: College 115, Adult 67, Teens and Youth 33, Kids 1 — 4 values, zero variants, zero typos.
A cleaner controlled vocabulary than education, and reporting depends on it.

## 1d. Occupation — the case where a lookup would *hurt*

`Profesi saat ini` looks similar but is not. Its 7 values are three real clusters plus four
one-off free-text entries:

```
Siswa/Mahasiswa 156 · Pekerja 45 · Ibu Rumah Tangga 3      ← real categories
Gembala 1 · bisnis 1 · Toko 1 · 倉管 1                      ← someone typed these
```

Forcing a lookup means the import must decide, per row, whether `倉管` (warehouse management) and
`Toko` (shop) are new categories or spellings of `Pekerja` — a judgement the source cannot settle
and the owner should not have to make 4 times for 4 rows.

**Recommendation: keep both.**

```php
$table->string('occupation_category')->nullable();   // student|worker|homemaker|other
$table->string('occupation_detail')->nullable();     // verbatim source value, preserved
```

The four one-offs become `other` with their original text intact. Nothing is lost, nothing is
invented, and a lookup can be introduced later if the categories stabilise.

## 2. `ifgf_member_profiles` — the Phase 1A core

One row per adopted `users` row. **Sidecar existence marks a member as IFGF-managed**
(`build.md` invariant 28).

```php
$table->id();
$table->unsignedInteger('user_id')->unique();               // INT — Rule 0
$table->foreign('user_id')->references('id')->on('users');

// QR — PRD FR-02.7. Attendance credential only, never auth.
$table->char('qr_token', 32)->unique();                      // Str::random(32)
$table->unsignedInteger('qr_version')->default(1);
$table->timestamp('qr_rotated_at')->nullable();
$table->char('qr_short_code', 8)->unique();                  // AMENDED §20.0 A1 — FR-02.7.6
                                                             // manual-entry fallback, rotates
                                                             // with the token

$table->foreignId('branch_id')->nullable()->constrained('ifgf_branches');  // NULLABLE — §13.1.19
$table->foreignId('member_category_id')->nullable()->constrained('ifgf_member_categories');
$table->foreignId('education_level_id')->nullable()->constrained('ifgf_education_levels');
$table->string('status')->default('visitor');                // visitor|active|inactive|exited
$table->string('chinese_name')->nullable();                  // 92.2% filled
$table->string('occupation_category')->nullable();           // student|worker|homemaker|other
$table->string('occupation_detail')->nullable();             // verbatim source, see 1d
$table->string('organisation')->nullable();                  // Sekolah/Kampus/Perusahaan
$table->string('address_tw')->nullable();                    // Domisili Taiwan
$table->boolean('baptised')->nullable();
$table->date('baptised_on')->nullable();                     // FR-01.4 stored separately
$table->text('pastoral_notes')->nullable();                  // ADMIN ONLY — FR-01.8
$table->date('joined_on')->nullable();
$table->foreignId('import_batch_id')->nullable()->constrained('ifgf_import_batches');
$table->timestamps();
$table->softDeletes();

$table->index('branch_id');
$table->index('status');
$table->index('member_category_id');
```

**Not carried:** the 12 workbook columns at ≤0.9% fill — `Domisili Indonesia`, `Tahun Masuk
Ajaran`, `Jurusan`, `Gereja di Indonesia`, `Hobi`, `Pernah Komsel`, `Pernah Melayani`,
`Discipleship Class`, `Ayo Baca Alkitab`, `First Impression`, `Saran`, `Column 27`. Residue of an
older form; import-exception category, not fields.
**Follow-up preference is absent** — never captured (0%), removed from FR-02.2's minimum.

## 3. `ifgf_contact_points` — FR-01.5

```php
$table->id();
$table->unsignedInteger('user_id');
$table->foreign('user_id')->references('id')->on('users');
$table->string('channel');                       // email|phone|whatsapp|line
$table->string('value');
$table->string('value_normalised')->nullable();  // E.164 for phone/whatsapp
$table->boolean('is_primary')->default(false);
$table->timestamp('verified_at')->nullable();
$table->foreignId('import_batch_id')->nullable()->constrained('ifgf_import_batches');
$table->timestamps();

$table->index(['channel','value_normalised']);   // duplicate detection — FR-01.6
$table->unique(['user_id','channel','value']);
```

**`value_normalised` exists because legacy phone matching is broken** — `cleanPhoneNumber` no-ops
on leading zeros, so `0912…`, `+886912…`, `886912…` were three keys. Normalise to E.164 with an
explicit default region and expect duplicates the legacy system never caught (`GAS_INVENTORY.md` C3).

## 4. `ifgf_event_types` · 5. `ifgf_event_definitions` · 6. `ifgf_event_occurrences`

```php
// ifgf_event_types
$table->id();
$table->string('code')->unique();                // super_sunday|icare|worship_night|special
$table->string('name');
$table->string('default_delivery_mode')->default('onsite');
$table->string('default_audience')->default('open');
$table->boolean('is_active')->default(true);
$table->timestamps();

// ifgf_event_definitions — sidecar on upstream events
$table->id();
$table->unsignedInteger('event_id')->unique();   // INT — Rule 0
$table->foreign('event_id')->references('id')->on('events');
$table->foreignId('event_type_id')->constrained('ifgf_event_types');
$table->foreignId('branch_id')->nullable()->constrained('ifgf_branches');  // null = church-wide
$table->string('delivery_mode')->default('onsite');   // onsite|online|hybrid
$table->string('audience')->default('open');
$table->text('rrule')->nullable();               // RFC 5545 — FR-03.9
$table->string('timezone', 64)->default('Asia/Taipei');
$table->unsignedInteger('capacity')->nullable();
$table->timestamps();

// ifgf_event_occurrences — sidecar on event_attendance_sessions
$table->id();
$table->unsignedBigInteger('session_id')->nullable()->unique();  // BIGINT — Rule 0.
      // NULLABLE — AMENDED §20.0 A2. The upstream session is created lazily, on first
      // attendance write, not when the occurrence is generated.
$table->foreign('session_id')->references('id')->on('event_attendance_sessions');
$table->foreignId('event_definition_id')->constrained('ifgf_event_definitions');
$table->foreignId('branch_id')->nullable()->constrained('ifgf_branches');
$table->char('occurrence_token', 16)->unique();       // optional usher scan shortcut
$table->timestamp('starts_at');
$table->timestamp('ends_at')->nullable();
$table->string('status')->default('planned');         // planned|open|finalized|cancelled|reopened
$table->timestamp('finalized_at')->nullable();
$table->unsignedInteger('finalized_by')->nullable();
$table->text('reopen_reason')->nullable();            // FR-04.8 mandatory on reopen
$table->timestamps();

$table->unique(['event_definition_id','starts_at']);  // WP 0C item 5 — replaces date-only
$table->index(['branch_id','starts_at']);
```

**`unique(event_definition_id, starts_at)` is the fix for WP 0C item 5.** Upstream
`event_attendance_sessions` has `unique(event_id, attendance_date)` — one session per event per
**day** — which contradicts FR-03.2's multiple same-day occurrences. Removing that upstream unique
is an expand/contract change requiring characterization first; **this table can be created now and
the upstream constraint dropped later.**

## 7. `ifgf_attendance_details` — sidecar on `event_attendees`

```php
$table->id();
$table->unsignedBigInteger('event_attendee_id')->nullable()->unique();  // BIGINT — Rule 0.
      // NULLABLE — AMENDED §20.0 A3. Only a PRESENT record has an upstream row.
$table->foreign('event_attendee_id')->references('id')->on('event_attendees');
$table->unsignedInteger('user_id');                // INT — Rule 0. AMENDED §20.0 A3
$table->foreign('user_id')->references('id')->on('users');
$table->foreignId('occurrence_id')->constrained('ifgf_event_occurrences');
$table->string('status')->default('present');      // present|absent|excused
$table->string('participation_mode')->nullable();  // onsite|online — NULL when absent, FR-04.3
$table->string('capture_method')->default('manual');
      // manual|member_qr|import|zoom_import|photo_suggestion  — string, not enum (FR-04.5)
$table->string('verification_status')->nullable();
$table->unsignedInteger('recorded_by')->nullable();
$table->foreignId('import_batch_id')->nullable()->constrained('ifgf_import_batches');
$table->timestamps();

$table->unique(['occurrence_id','user_id']);       // AMENDED §20.0 A3 — the real dedupe key
$table->index(['occurrence_id','status']);
```

**`EventAttendee` is presence-only today, so a missing row means "not recorded".** After this
sidecar lands it must *still* never be read as absence. That assertion belongs in the
characterization suite **before** the migration runs — it is in `tools/UNBLOCK_PROMPT.md` step 2.

## 8. `ifgf_group_memberships` — FR-05, effective-dated

```php
$table->id();
$table->unsignedInteger('user_id');
$table->foreign('user_id')->references('id')->on('users');
$table->unsignedInteger('group_id');
$table->foreign('group_id')->references('id')->on('groups');
$table->unsignedInteger('projected_group_link_id')->nullable()->unique();  // invariant 29
$table->foreign('projected_group_link_id')->references('id')->on('group_links')->nullOnDelete();
$table->boolean('is_primary')->default(true);
$table->string('role')->default('member');       // member|leader|visitor
$table->date('effective_from');
$table->date('effective_to')->nullable();
$table->foreignId('import_batch_id')->nullable()->constrained('ifgf_import_batches');
$table->timestamps();

// One active PRIMARY membership per member — invariant 16.
// MySQL-compatible generated nullable unique: NULL rows are not compared.
$table->rawIndex(
  '(CASE WHEN `is_primary` = 1 AND `effective_to` IS NULL THEN `user_id` END)',
  'ifgf_group_memberships_one_active_primary'
);   // add ->unique() semantics via a raw CREATE UNIQUE INDEX in the migration
$table->index(['group_id','effective_to']);
```

The generated-column trick is how invariant 16 is enforced without a trigger. Pair it with row
locking in `GroupMembershipService` — the constraint catches races, the lock avoids them.

## 8b. `ifgf_group_profiles` — NEW, owner spec 2026-08-11

Sidecar on upstream `groups`. **Table count 21 → 22.** Needed for requirement 1: each iCare has a
default meeting weekday.

```php
$table->id();
$table->unsignedInteger('group_id')->unique();       // INT — Rule 0
$table->foreign('group_id')->references('id')->on('groups');
$table->foreignId('branch_id')->nullable()->constrained('ifgf_branches');
$table->unsignedTinyInteger('default_weekday')->nullable();  // 1=Mon … 7=Sun, ISO-8601
$table->time('default_time')->nullable();
$table->string('meeting_mode')->default('onsite');   // onsite|online|hybrid
$table->boolean('is_active')->default(true);
$table->foreignId('import_batch_id')->nullable()->constrained('ifgf_import_batches');
$table->timestamps();
```

**Why a column and not an RRULE.** FR-03.9 uses RFC 5545 for church events, and an iCare *could*
be modelled as an event with `RRULE:FREQ=WEEKLY;BYDAY=TU`. For a 10-person group whose leader just
wants "we meet Tuesdays," a weekday integer is the honest representation — the occurrence generator
reads it and produces the same result without the leader ever meeting an RRULE. Escalate to an
RRULE only if a group needs fortnightly or exception dates.

## 8c. Owner specification — weekly iCare attendance, 2026-08-11

> **▶ Fully specified in Part 3 (§25), added 2026-08-21.** This section maps the six requirements
> to columns. §25 carries the `IcareAttendanceService` contract, the date resolution rule in full,
> the controller and screen states, the authorization denials, and the test list — plus four
> amendments (§25.0 A4–A7) that this section's columns turn out to require.

Six requirements, mapped. **Four need no schema change.**

| # | Requirement | Where it lives | Change? |
|---|---|---|---|
| 1 | Default meeting weekday per iCare | `ifgf_group_profiles.default_weekday` | **NEW table 8b** |
| 2 | Date prefilled from that weekday, adjustable via date picker | `ifgf_event_occurrences.starts_at` | none |
| 3 | Tick which members attended | `ifgf_attendance_details.status` | none |
| 4 | Add guests — from another iCare, or in none | `ifgf_attendance_details` | **2 columns** |
| 5 | Onsite by default, tick for online | `ifgf_attendance_details.participation_mode` | none |
| 6 | Upload the group selfie | `ifgf_attendance_media` | none |

### Requirement 4 — two distinct guest cases

```php
// add to ifgf_attendance_details
$table->boolean('is_visitor')->default(false);
$table->unsignedInteger('visitor_from_group_id')->nullable();   // INT — Rule 0
$table->foreign('visitor_from_group_id')->references('id')->on('groups');
```

- **Visitor from another iCare** — `is_visitor = true`, `visitor_from_group_id` set. Their primary
  membership is **untouched** (FR-05.6). The attendance counts for the hosting group; the member
  still belongs to their own.
- **Someone in no iCare at all** — quick-add as an unclaimed member (FR-05.7), then
  `is_visitor = true` with `visitor_from_group_id` null. Unclaimed records cannot authenticate
  (invariant 2), so this is safe to do from a phone mid-meeting.

**Do not** create a group membership for a visitor. That is the mistake this flag prevents — one
visit would otherwise look like a transfer, and with the one-active-primary constraint it could
silently end their real membership.

### Requirement 2 — how the date actually behaves

```
leader opens "record iCare attendance" for group G
  → default date = most recent occurrence of G.default_weekday, today or earlier,
    COMPUTED IN THE BRANCH TIMEZONE (see caveat below)
  → date picker allows any date
  → on save: find-or-create the ifgf_event_occurrences row for (G's event definition, chosen date)
```

> **⚠ Timezone caveat — added 2026-08-21, this requirement is exactly the case the UTC contract
> warns about.** "Most recent Tuesday" is a **calendar day derived from an instant**, and §A0 is
> explicit that such a derivation must convert to the branch timezone first. Computing it from
> `now()` in UTC puts a Tuesday-evening iCare on Monday's date for any meeting starting before
> 08:00 Taipei — and iCare meets on weekday *evenings*, so a late finish crossing midnight local is
> the realistic failure, not a theoretical one.
>
> Resolve `default_weekday` against `ifgf_branches.timezone` via the group's branch, never against
> the server clock. **Write the test before the feature** — a date-arithmetic bug here silently
> files a whole meeting under the wrong day and nobody notices until reconciliation.
>
> **Understated, and corrected in §25.2:** weekday arithmetic *snaps*, so a one-day error in
> "today" becomes a **seven-day** error in the answer. The full rule, both boundary cases and the
> test that pins them are in §25.2.

`unique(event_definition_id, starts_at)` makes find-or-create idempotent — two leaders saving the
same meeting produce one occurrence, not two. **Create the occurrence on save, not in advance.**
Pre-generating 22 groups × 52 weeks creates 1,144 rows a year that mostly never get used, and every
one is a row the reconciliation has to account for.

### Requirement 3 and 5 — the roster screen

Roster comes from `ifgf_group_memberships` where `effective_to IS NULL`. For each member: a
present/absent tick, and a mode toggle defaulting to **onsite**. Everything writes through
`AttendanceRecorder` — never directly to `event_attendees` (FR-04.1).

**Pre-tick from the previous occurrence.** ~10 members meeting weekly is highly regular, so last
week's attendance is a strong prior. This is the cheapest usability win available here, it needs no
biometrics and no consent, and it is worth measuring before anyone invests in photo recognition.

### Requirement 6 — the selfie

`ifgf_attendance_media` linked to the occurrence. **Documentation only.** No face detection, no
recognition, no consent gate — it is a stored file. The detection feature stays Phase 2B behind
WP 0D (`ICARE_PHOTO_ATTENDANCE.md`).

### Scope note

**This is R1b.** Still open — carried to §25.7 as decision D11. The 2026-08-11 release split put iCare after R1a — member registry, QR, Sunday
attendance, member import, production. The specification above is worth capturing now regardless;
building it early means either moving iCare into R1a or delaying the Sunday cutover. Owner decision.

## 9–11. Media — `ifgf_member_media`, `ifgf_media_variants`, `ifgf_attendance_media`

Provider-neutral per the owner's Q14 decision. **No provider named in the schema.**

```php
// ifgf_member_media
$table->id();
$table->unsignedInteger('user_id');
$table->foreign('user_id')->references('id')->on('users');
$table->string('disk');                          // config-driven, not hardcoded
$table->string('object_key')->unique();          // opaque, never a member-derived path
$table->string('purpose')->default('profile');   // profile|enrollment|documentation
$table->string('mime', 100);
$table->unsignedBigInteger('bytes');
$table->unsignedInteger('width')->nullable();
$table->unsignedInteger('height')->nullable();
$table->string('checksum', 64)->nullable();
$table->string('state')->default('quarantine');  // quarantine|clean|rejected
$table->unsignedInteger('uploaded_by');          // admin-only at launch — Q15
$table->timestamp('retained_until')->nullable();
$table->timestamps();
$table->softDeletes();

// ifgf_media_variants
$table->id();
$table->foreignId('member_media_id')->constrained('ifgf_member_media')->cascadeOnDelete();
$table->string('variant');                       // webp_256|webp_512|jpeg_fallback
$table->string('object_key')->unique();
$table->unsignedInteger('width');
$table->unsignedInteger('height');
$table->unsignedBigInteger('bytes');
$table->timestamps();

// ifgf_attendance_media — iCare documentation photos, Release 1 = storage only
$table->id();
$table->foreignId('occurrence_id')->constrained('ifgf_event_occurrences');
$table->string('disk');
$table->string('object_key')->unique();
$table->string('mime', 100);
$table->unsignedBigInteger('bytes');
$table->unsignedInteger('uploaded_by');
$table->timestamp('retained_until')->nullable();
$table->timestamps();
$table->softDeletes();
```

`cascadeOnDelete` on variants is correct — a variant has no meaning without its original. **This is
the one place cascade is right**; do not copy it to anything carrying pastoral history.

## 12. `ifgf_consents` — FR-11.10

```php
$table->id();
$table->unsignedInteger('user_id');
$table->foreign('user_id')->references('id')->on('users');
$table->string('consent_type');
      // profile_display|biometric_enrollment|live_recognition|group_photo_matching|privacy_policy
$table->string('policy_version');
$table->string('method');                        // registration_form|admin|portal|import
$table->timestamp('granted_at')->nullable();
$table->timestamp('revoked_at')->nullable();
$table->unsignedInteger('actor_id')->nullable();
$table->timestamps();

$table->index(['user_id','consent_type','revoked_at']);
```

**Four distinct types, never bundled** (invariant 33). Profile-display consent never implies
recognition consent. The iCare photo feature in `ICARE_PHOTO_ATTENDANCE.md` needs
`group_photo_matching` specifically, and it cannot be inferred from any other row.

## 13–14. Calendar — `ifgf_calendar_links`, `ifgf_calendar_viewer_access`

```php
// ifgf_calendar_links — FR-08
$table->id();
$table->unsignedInteger('user_id');
$table->foreign('user_id')->references('id')->on('users');
$table->string('provider_calendar_id');
$table->string('provider_event_id')->nullable();
$table->string('sync_hash', 64)->nullable();     // FR-08.4, covers only fields in the event
$table->string('state')->default('pending');     // pending|synced|failed|tombstoned
$table->timestamp('last_synced_at')->nullable();
$table->text('last_error')->nullable();
$table->timestamps();
$table->unique(['user_id','provider_calendar_id']);

// ifgf_calendar_viewer_access — FR-08.15
$table->id();
$table->unsignedInteger('user_id');
$table->foreign('user_id')->references('id')->on('users');
$table->string('google_email');
$table->string('grant_state')->default('pending');  // pending|granted|revoked
$table->timestamp('granted_at')->nullable();
$table->timestamp('revoked_at')->nullable();
$table->unsignedInteger('actor_id')->nullable();
$table->timestamps();
```

The legacy GAS stores one recurring series ID per member and it is **the only 100%-populated
workbook column** — so same-calendar adoption (FR-08.10) should succeed for essentially every
member. `provider_event_id` is where that legacy ID lands.

## 15–19. Import pipeline — FR-12

```php
// ifgf_import_batches
$table->id();
$table->string('source');                        // workbook|ifgf_export|zoom_csv
$table->string('source_file_hash', 64);
$table->timestamp('extracted_at');
$table->string('importer_version');
$table->unsignedInteger('operator_id');
$table->string('status')->default('dry_run');    // dry_run|committed|rolled_back
$table->unsignedInteger('rows_total')->default(0);
$table->unsignedInteger('rows_inserted')->default(0);
$table->unsignedInteger('rows_updated')->default(0);
$table->unsignedInteger('rows_skipped')->default(0);
$table->unsignedInteger('rows_conflicted')->default(0);
$table->unsignedInteger('rows_errored')->default(0);
$table->timestamp('rollback_deadline')->nullable();
$table->timestamps();

// ifgf_import_source_records
$table->id();
$table->foreignId('import_batch_id')->constrained('ifgf_import_batches');
$table->string('source_key');                    // sheet!row, stable
$table->string('payload_hash', 64);
$table->json('raw_payload');
$table->string('target_type')->nullable();
$table->unsignedBigInteger('target_id')->nullable();
$table->string('outcome')->nullable();           // insert|update|merge|skip|conflict|error
$table->text('reason')->nullable();
$table->timestamps();
$table->unique(['import_batch_id','source_key']);

// ifgf_source_identity_maps
$table->id();
$table->string('source');
$table->string('source_key');
$table->string('target_type');
$table->unsignedBigInteger('target_id');
$table->timestamps();
$table->unique(['source','source_key','target_type']);

// ifgf_import_conflicts — a REVIEW WORKFLOW, not a log (§13.1.21)
$table->id();
$table->foreignId('import_batch_id')->constrained('ifgf_import_batches');
$table->foreignId('source_record_id')->constrained('ifgf_import_source_records');
$table->string('field');
$table->text('workbook_value')->nullable();
$table->text('export_value')->nullable();
$table->text('chosen_value')->nullable();
$table->string('resolution')->default('pending'); // pending|workbook|export|manual
$table->unsignedInteger('reviewed_by')->nullable();
$table->text('reason')->nullable();
$table->timestamp('reviewed_at')->nullable();
$table->timestamps();
$table->index(['import_batch_id','resolution']);

// ifgf_import_exceptions
$table->id();
$table->foreignId('import_batch_id')->constrained('ifgf_import_batches');
$table->string('source_key')->nullable();
$table->string('kind');   // malformed_row|missing_branch|unparseable_date|duplicate_name|dead_column
$table->text('detail');
$table->timestamps();
```

**`ifgf_import_conflicts` needs an admin review UI, not just a table.** The owner chose
escalate-every-conflict with no blanket precedence, and `WORKBOOK_INVENTORY.md` shows the scan log
and the weekly grid cannot agree — 3,227 scans cannot populate ~40,000 grid cells. Volume is
unknown until the dry-run measures it, which is exactly why the pipeline must support manual
resolution from the start.

---

## Open items for the fill pass

| # | Item |
|---|---|
| 1 | `ifgf_group_memberships`'s partial unique index needs a raw `CREATE UNIQUE INDEX` in `up()`; Laravel's builder cannot express it. Write it explicitly and test the race. |
| 2 | Branch attribution for historical attendance comes from **which grid sheet a row came from**, not `Lokasi` (owner decision). The extractor carries `branch_id`; the scan log supplies timestamps only. |
| 3 | ~~Lookup tables for category / education / occupation~~ — **decided 2026-08-11.** `ifgf_education_levels` and `ifgf_member_categories` added (§1b, §1c); occupation stays a category string plus verbatim detail (§1d). **Table count 19 → 21.** `tools/GENERATION_MANIFEST.md` Section 1 needs two more `make:migration` lines and Section 2 two more `make:model` entries — see below. |
| 4 | `ifgf_event_occurrences.occurrence_token` is optional (QR-B). Keep the column; the feature is not MVP. |

## Deliberately excluded from Release 1

`ifgf_programs`, `ifgf_program_stages`, `ifgf_program_modules`, `ifgf_program_cohorts`,
`ifgf_program_enrollments`, `ifgf_program_facilitators` (FR-06 CGSL) · `ifgf_ministries`,
`ifgf_ministry_assignments` (FR-07) · `ifgf_event_registrations` (FR-13). Nine tables, all
Release 2.

---
---

# Part 2 — Models and service contracts

**Written 2026-08-21 in Cowork.** Router branch 1: **0 `ifgf_` migrations exist**, so this session
specifies rather than writes. Nothing below is code in the repository; it is the fill-pass source
for `tools/GENERATION_MANIFEST.md` Sections 2 and 3, written now so that the pass that follows
generation is transcription rather than design.

Sources: Part 1 above · `PRD.md` FR-01…FR-12 and §4.3/§13.1 (line-ranged reads only) ·
`ATTENDANCE_QR_DESIGN.md` · upstream models read directly at `3793b54` ·
`CONTEXT.md` (attendance semantics, REG-001, SEC-001/002/003) · `MEMORY.md` 2026-08-21.

---

## 20.0 Amendments to Part 1 — four changes, applied above, read before filling

Part 1 was written 2026-08-11. Specifying the services against it surfaced four places where the
schema as drawn cannot express what the PRD requires. **All four are already patched into Part 1**
and marked `AMENDED §20.0 A<n>` at the line. The reasoning lives here.

### A0 🔴 Timezone — the standing prompt and the repository disagree. ESCALATED, not decided.

Part 1 Rule 1 said *"App timezone `Asia/Taipei`, MySQL connection `+08:00`"*, and the standing
campaign prompt still carries that line. **`CONTEXT.md` records the opposite as settled**:
UTC at rest, `TIMEZONE=UTC`, connection `+00:00`, decided 2026-08-15, PRD wins, recorded as UP-009,
pinned by a test.

I have written Part 2 against **UTC at rest**, because it is the decision with a commit, a ledger
entry and a passing test behind it, and because reverting it would break `test_documents_*`. Rule 1
is corrected accordingly. **But the prompt is the owner's standing instruction and I am not
overriding it silently** — this is exactly the §13.1.20 case.

> **✅ OWNER DECISION D4 — RESOLVED 2026-08-21. UTC at rest confirmed; UP-009 stands.**
> The `Asia/Taipei` / `+08:00` line has been **struck from `tools/CAMPAIGN_PROMPT.md` section B**
> (the standing Cowork prompt) and replaced with the settled contract plus the derived rule — a bare
> "UTC at rest" does not tell a session how to derive a calendar day, which is the actual trap.
> Rule 1 above and all of Part 2 were already written against UTC at rest, so nothing changes here.
> *Original escalation kept below, because the reasoning is what makes the answer re-checkable.*
>
> ~~Confirm UTC at rest and strike the `Asia/Taipei` / `+08:00` line from the standing campaign
> prompt, or reopen UP-009. Everything in Part 2 that derives a calendar day depends on the
> answer.~~

Note the two are not really in tension on substance: **per-branch IANA timezone columns still
exist** (`ifgf_branches.timezone`, `ifgf_event_definitions.timezone`) and are what a calendar day is
derived *with*. UTC is where instants are *stored*. The prompt's line conflates the two.

### A1 The member card's human-readable fallback had no column

FR-02.7.6 requires the printed card to carry *"a short human-readable code alongside the QR, so an
usher can complete check-in manually when a camera cannot read the code."* Part 1 specified
`qr_token` only. A 32-character random string is not a code a human reads off a card and types.

Added: `qr_short_code char(8) unique`. Crockford base32 (no `I`, `L`, `O`, `U` — the characters
that are misread and the one that forms words), generated with the token, **rotated with the
token**, and resolvable only through the same rate-limited, leader-scoped endpoint.

**40 bits is the right amount of entropy here and the argument matters.** The threat FR-02.7.4
names is forgery cost, not secrecy. The short code is not a secret and not an auth factor: it
resolves only for an authenticated leader, only against an open occurrence they are assigned to,
and only through a rate-limited endpoint. Guessing one blind at those limits is not a viable
attack, and the alternative — a longer code — defeats the entire purpose of having one.

### A2 `ifgf_event_occurrences.session_id` must be nullable

`OccurrenceGenerator` creates occurrences from an RRULE over a rolling horizon (FR-03.5, FR-03.9),
weeks ahead. A `NOT NULL` unique `session_id` would force an upstream
`event_attendance_sessions` row for every future occurrence at generation time — and upstream's
`unique(event_id, attendance_date)` would then **reject the second occurrence on any day**, which is
the exact constraint FR-03.2 exists to escape.

Made nullable. The upstream session is created **lazily**, by `AttendanceRecorder` on the first
attendance write for that occurrence. MySQL does not compare NULLs in a unique index, so any number
of unattended future occurrences coexist.

**Known limitation, recorded rather than worked around:** until upstream's
`unique(event_id, attendance_date)` is dropped (WP 0C item 5, expand/contract, gated on
characterization), *two occurrences of the same event on the same Taipei day still cannot both
carry attendance* — the second lazy session creation fails. Release 1's Super Sunday is one
occurrence per day per branch, so this does not block Phase 1A. **It blocks any second same-day
service, and that is a scheduling dependency, not a schema one.**

### A3 🔴 `ifgf_attendance_details` as drawn could not represent absence at all

The largest of the four, and it goes to the campaign rule *"A MISSING attendance row is NOT
absence."*

Part 1 keyed the sidecar on `event_attendee_id` — `NOT NULL`, `unique`, FK to `event_attendees`. But
`event_attendees` is **presence-only** (`CONTEXT.md`, pinned by
`test_documents_event_attendees_is_presence_only_and_has_no_status`). So:

- **`status = 'absent'` was unreachable.** Recording absence would have required an
  `event_attendees` row, and an `event_attendees` row means "recorded present" to every upstream
  reader — the CSV export, the session counts, the API. Writing one would have corrupted upstream
  reporting to satisfy an IFGF column.
- FR-04.2 requires `present|absent|excused` and FR-04.6 requires closed-roster occurrences to
  *"create a final snapshot including absent or excused members."* Neither was expressible.

Amended: `event_attendee_id` becomes **nullable**, a `user_id` column is added (INT, Rule 0), and
the real dedupe key becomes **`unique(occurrence_id, user_id)`**.

**The resulting rule is the one `AttendanceRecorder` enforces and it is short enough to keep in
your head:**

| Status | `event_attendees` row | `ifgf_attendance_details` row |
|---|---|---|
| `present` | **yes** — upstream stays correct | yes, `event_attendee_id` set |
| `absent` | **no** | yes, `event_attendee_id` NULL |
| `excused` | **no** | yes, `event_attendee_id` NULL |
| not recorded | no | **no** — and this still means *nothing was observed* |

Upstream counts remain exactly what they were. Absence becomes an explicit, actor-stamped,
timestamped assertion — which is the only form in which it is safe to feed FR-10's inactive-risk
report. **The fourth row is the one that must survive review: a member with no row is still not
absent, before this table and after it.**

---

## 20.1 Model conventions — apply to all 21, do not restate per model

**Namespace** `Ifgf\ChurchOperations\Models`. Upstream models stay `App\Models` and are referenced
across the boundary by FQCN. Relations to `App\Models\User`, `Userprofile`, `Events`,
`EventAttendanceSession`, `EventAttendee`, `Group`, `GroupLink` are normal — the boundary is a
namespace, not a database.

**🔴 Never `protected $dates`. It is inert and has been since Laravel 10 — that is REG-001**, which
left 21 columns across 9 upstream models silently returning strings and killed the attendance CSV
export for months without an error. Every temporal column below is declared in `casts()`. Not one
`ifgf_` model may declare `$dates`, and a model with no `casts()` method should be read as an
omission, not a decision.

**Use the `casts()` method, not the `$casts` property.** Laravel 11+ supports
`protected function casts(): array`, this repo is 13.24.0, and the method form cannot be silently
shadowed by a parent property the way `$dates` was.

```php
protected function casts(): array
{
    return ['granted_at' => 'datetime', 'is_primary' => 'boolean'];
}
```

**`$fillable`, never `$guarded = []`.** Every list below is exhaustive and deliberately excludes
columns that only a service may write. Those are listed per model as **service-written**; a service
sets them with `->forceFill()` or explicit property assignment inside its own transaction.

**Reverse relations from upstream models are registered, not edited in.** `App\Models\User` is
upstream-owned; adding `ifgfProfile()` to it needs an `UPSTREAM.md` entry, a characterization test
and an owner decision, and it would conflict on every upstream sync forever. Use Eloquent's
relation resolver in `ChurchOperationsServiceProvider::boot()` instead — **no upstream file is
touched**:

```php
User::resolveRelationUsing('ifgfProfile', fn ($user) =>
    $user->hasOne(MemberProfile::class, 'user_id'));
User::resolveRelationUsing('ifgfContactPoints', fn ($user) =>
    $user->hasMany(ContactPoint::class, 'user_id'));
User::resolveRelationUsing('ifgfMemberships', fn ($user) =>
    $user->hasMany(GroupMembership::class, 'user_id'));
Events::resolveRelationUsing('ifgfDefinition', fn ($e) =>
    $e->hasOne(EventDefinition::class, 'event_id'));
EventAttendanceSession::resolveRelationUsing('ifgfOccurrence', fn ($s) =>
    $s->hasOne(EventOccurrence::class, 'session_id'));
```

**This is also the seam's failure mode.** If the package stops loading — the silent failure
`PackageProviderSmokeTest` exists to catch — these relations vanish and `$user->ifgfProfile`
throws `RelationNotFoundException` rather than returning null. That is the correct behaviour
(loud, not silent) but the smoke test should assert one resolved relation, not only the provider.

**`SoftDeletes`** on: `MemberProfile`, `MemberMedia`, `AttendanceMedia`. Nowhere else.
**No cascade deletes anywhere except `MediaVariant`** (Part 1 §9–11).

**Sidecar existence marks IFGF management** (`build.md` invariant 28) — a `users` row with no
`MemberProfile` is upstream-only and must not appear in any IFGF listing.

---

## 20.2 Lookups — `Branch`, `EducationLevel`, `MemberCategory`, `EventType`

Four models, one shape. All four: no soft deletes, no service-written columns, seeded by import
(`Branch`) or by a package seeder (the other three).

```php
// Branch → ifgf_branches
protected $table = 'ifgf_branches';
protected $fillable = ['code','name','name_zh','timezone','gmaps_url','is_active','display_order'];
protected function casts(): array {
    return ['is_active' => 'boolean', 'display_order' => 'integer'];
}
public function memberProfiles()    { return $this->hasMany(MemberProfile::class, 'branch_id'); }
public function eventDefinitions()  { return $this->hasMany(EventDefinition::class, 'branch_id'); }
public function occurrences()       { return $this->hasMany(EventOccurrence::class, 'branch_id'); }
public function scopeActive($q)     { return $q->where('is_active', true); }
public function scopeOrdered($q)    { return $q->orderBy('display_order')->orderBy('code'); }
```

`timezone` is the **only** correct source for "what calendar day was this, locally" — see A0. A
branch row's `timezone` must never be defaulted at read time; a NULL there is a data defect, which
is why the column is `NOT NULL default 'Asia/Taipei'`.

```php
// EducationLevel → ifgf_education_levels
protected $fillable = ['code','name','name_en','rank','is_active'];
protected function casts(): array { return ['rank' => 'integer', 'is_active' => 'boolean']; }
public function memberProfiles() { return $this->hasMany(MemberProfile::class, 'education_level_id'); }
public function scopeAtLeast($q, EducationLevel $l) { return $q->where('rank', '>=', $l->rank); }

// MemberCategory → ifgf_member_categories
protected $fillable = ['code','name','rank','is_active'];
protected function casts(): array { return ['rank' => 'integer', 'is_active' => 'boolean']; }
public function memberProfiles() { return $this->hasMany(MemberProfile::class, 'member_category_id'); }

// EventType → ifgf_event_types
protected $fillable = ['code','name','default_delivery_mode','default_audience','is_active'];
protected function casts(): array { return ['is_active' => 'boolean']; }
public function definitions() { return $this->hasMany(EventDefinition::class, 'event_type_id'); }
```

`scopeAtLeast` is the whole reason `rank` exists (Part 1 §1b) — "at least a bachelor's degree" is a
comparison, never a string match. Write it once here so no report re-implements it.

---

## 20.3 `MemberProfile` → `ifgf_member_profiles`

The Phase 1A core.

```php
protected $table = 'ifgf_member_profiles';
use SoftDeletes;

protected $fillable = [
    'user_id','branch_id','member_category_id','education_level_id','status',
    'chinese_name','occupation_category','occupation_detail','organisation',
    'address_tw','baptised','baptised_on','joined_on','import_batch_id',
];
// SERVICE-WRITTEN, deliberately not fillable:
//   qr_token, qr_short_code, qr_version, qr_rotated_at  → MemberQrService only
//   pastoral_notes                                       → MemberRegistryService, admin policy only

protected $hidden = ['qr_token','qr_short_code','pastoral_notes'];

protected function casts(): array {
    return [
        'baptised'      => 'boolean',
        'baptised_on'   => 'date',
        'joined_on'     => 'date',
        'qr_version'    => 'integer',
        'qr_rotated_at' => 'datetime',
    ];
}

public function user()          { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
public function userprofile()   { return $this->belongsTo(\App\Models\Userprofile::class, 'user_id', 'user_id'); }
public function branch()        { return $this->belongsTo(Branch::class, 'branch_id'); }
public function memberCategory(){ return $this->belongsTo(MemberCategory::class, 'member_category_id'); }
public function educationLevel(){ return $this->belongsTo(EducationLevel::class, 'education_level_id'); }
public function importBatch()   { return $this->belongsTo(ImportBatch::class, 'import_batch_id'); }
public function contactPoints() { return $this->hasMany(ContactPoint::class, 'user_id', 'user_id'); }
public function memberships()   { return $this->hasMany(GroupMembership::class, 'user_id', 'user_id'); }
public function consents()      { return $this->hasMany(Consent::class, 'user_id', 'user_id'); }
public function media()         { return $this->hasMany(MemberMedia::class, 'user_id', 'user_id'); }

public function scopeOfBranch($q, $branchId) { return $q->where('branch_id', $branchId); }
public function scopeStatus($q, string $s)   { return $q->where('status', $s); }
```

**`$hidden` is load-bearing, not tidiness.** `pastoral_notes` is admin-only and must never reach a
member or leader route, search, attendance screen, export or Calendar view (FR-01.8) — and this
application has already shipped one endpoint that serialised a whole Eloquent row into an API
response. `qr_token` in a JSON payload *is* the credential. Hiding them makes the accident
impossible by default; a route that genuinely needs them calls `makeVisible()` explicitly, which is
greppable.

**`userprofile()` uses `user_id` → `user_id`, not the primary key**, exactly as upstream's own
`EventAttendee::userprofile()` does. ⚠ It can return the **wrong row**: `userprofiles.user_id` is
**not unique** (WP 0C item 3, verified — a second row inserts cleanly). Until the dedupe lands, any
code reading through this relation is reading *one arbitrary* profile row. Do not add
`->latest()` to paper over it; the fix is the unique key, and the owner decides which row wins.

**Note the four fill-rate exclusions** and the twelve dead workbook columns from Part 1 §2 — they
are import exceptions, not fields, and must not be re-added during the fill pass because a
spreadsheet column exists.

---

## 20.4 `ContactPoint` → `ifgf_contact_points`

```php
protected $fillable = ['user_id','channel','value','is_primary','import_batch_id'];
// SERVICE-WRITTEN: value_normalised (PhoneNormalizer via MemberRegistryService), verified_at

protected function casts(): array {
    return ['is_primary' => 'boolean', 'verified_at' => 'datetime'];
}

public function user() { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
public function scopeChannel($q, string $c) { return $q->where('channel', $c); }
public function scopePrimary($q)            { return $q->where('is_primary', true); }
```

`value_normalised` is **not fillable on purpose**. If a controller can set it, one will eventually
set it to the raw value and the duplicate detection in FR-01.6 quietly stops working — the failure
is invisible because the column still looks populated. Only `PhoneNormalizer` writes it.

Channel values: `email|phone|whatsapp|line`. Application-validated strings, no enum.

---

## 20.5 Events — `EventDefinition`, `EventOccurrence`

```php
// EventDefinition → ifgf_event_definitions  (sidecar on upstream events)
protected $fillable = [
    'event_id','event_type_id','branch_id','delivery_mode','audience',
    'rrule','timezone','capacity',
];
protected function casts(): array { return ['capacity' => 'integer']; }

public function event()      { return $this->belongsTo(\App\Models\Events::class, 'event_id'); }
public function eventType()  { return $this->belongsTo(EventType::class, 'event_type_id'); }
public function branch()     { return $this->belongsTo(Branch::class, 'branch_id'); }
public function occurrences(){ return $this->hasMany(EventOccurrence::class, 'event_definition_id'); }
```

```php
// EventOccurrence → ifgf_event_occurrences  (sidecar on event_attendance_sessions)
protected $fillable = ['event_definition_id','branch_id','starts_at','ends_at','status'];
// SERVICE-WRITTEN: session_id (AttendanceRecorder, lazily — A2), occurrence_token,
//                  finalized_at, finalized_by, reopen_reason

protected function casts(): array {
    return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'finalized_at' => 'datetime'];
}

public function definition()  { return $this->belongsTo(EventDefinition::class, 'event_definition_id'); }
public function branch()      { return $this->belongsTo(Branch::class, 'branch_id'); }
public function session()     { return $this->belongsTo(\App\Models\EventAttendanceSession::class, 'session_id'); }
public function details()     { return $this->hasMany(AttendanceDetail::class, 'occurrence_id'); }
public function media()       { return $this->hasMany(AttendanceMedia::class, 'occurrence_id'); }
public function finalizedBy() { return $this->belongsTo(\App\Models\User::class, 'finalized_by'); }

public function scopeOpen($q)      { return $q->where('status', 'open'); }
public function scopeWritable($q)  { return $q->whereIn('status', ['planned','open','reopened']); }

public function isWritable(): bool {
    return in_array($this->status, ['planned','open','reopened'], true);
}
public function localDate(): \Carbon\CarbonImmutable {
    return \Carbon\CarbonImmutable::parse($this->starts_at)
        ->setTimezone($this->branch?->timezone ?? $this->definition->timezone);
}
```

**`localDate()` is the A0 rule made into one method, and every report must call it** rather than
`->starts_at->toDateString()`. Instants are UTC; a Taipei service at 09:00 local is 01:00 UTC the
same day, but a 07:00 local service is 23:00 UTC the **previous** day. The 8 `date()` columns in
this application are where that rule has already been broken once.

`isWritable()` is the single definition of "attendance may be written here." `AttendanceRecorder`
calls it; nothing else re-derives it from `status`.

---

## 20.6 `AttendanceDetail` → `ifgf_attendance_details`

Read A3 above first — this model's shape is the amendment.

```php
protected $fillable = [];   // ← INTENTIONALLY EMPTY
```

**🔴 `$fillable = []` is the design, not an oversight.** `AttendanceRecorder` is the sole write path
(campaign rule, FR-04). An empty fillable list means `AttendanceDetail::create([...])` from a
controller throws `MassAssignmentException` instead of quietly bypassing the recorder. The recorder
writes with explicit assignment inside its own transaction. **If the fill pass "helpfully" populates
this array, the sole-write-path guarantee is gone and nothing will fail loudly to tell you.**

```php
protected function casts(): array { return []; }  // no temporal columns; timestamps() only

public function occurrence()    { return $this->belongsTo(EventOccurrence::class, 'occurrence_id'); }
public function user()          { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
public function eventAttendee() { return $this->belongsTo(\App\Models\EventAttendee::class, 'event_attendee_id'); }
public function recordedBy()    { return $this->belongsTo(\App\Models\User::class, 'recorded_by'); }
public function importBatch()   { return $this->belongsTo(ImportBatch::class, 'import_batch_id'); }

public function scopePresent($q) { return $q->where('status', 'present'); }
```

Values, all application-validated strings (FR-04.5):
`status` `present|absent|excused` · `participation_mode` `onsite|online`, **NULL when absent**
(FR-04.3) · `capture_method` `manual|member_qr|import|zoom_import|photo_suggestion`, with
`face_assisted|face_auto` added in Phase 2 **without a new table**.

---

## 20.7 `GroupMembership` → `ifgf_group_memberships`

```php
protected $fillable = ['user_id','group_id','is_primary','role','effective_from','import_batch_id'];
// SERVICE-WRITTEN: effective_to, projected_group_link_id → GroupMembershipService only

protected function casts(): array {
    return [
        'is_primary'     => 'boolean',
        'effective_from' => 'date',
        'effective_to'   => 'date',
    ];
}

public function user()          { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
public function group()         { return $this->belongsTo(\App\Models\Group::class, 'group_id'); }
public function projectedLink() { return $this->belongsTo(\App\Models\GroupLink::class, 'projected_group_link_id'); }
public function importBatch()   { return $this->belongsTo(ImportBatch::class, 'import_batch_id'); }

public function scopeActive($q)     { return $q->whereNull('effective_to'); }
public function scopePrimary($q)    { return $q->where('is_primary', true); }
public function scopeAsOf($q, $date) {
    return $q->where('effective_from', '<=', $date)
             ->where(fn ($w) => $w->whereNull('effective_to')->orWhere('effective_to', '>', $date));
}
```

**`effective_to` is service-written because closing a membership is a transition, not an edit.**
FR-05.11 requires a transfer to lock the row, close the old membership, open the new one and write
an audit record **in one transaction**; a fillable `effective_to` lets a controller do half of that.

`scopeAsOf` is the historical query the iCare reports need and the one people re-implement wrongly
— note the `>` rather than `>=`: `effective_to` is the first day *not* covered.

---

## 20.8 Media — `MemberMedia`, `MediaVariant`, `AttendanceMedia`

```php
// MemberMedia → ifgf_member_media
use SoftDeletes;
protected $fillable = ['user_id','purpose'];
// EVERYTHING ELSE IS SERVICE-WRITTEN — MemberMediaService only:
//   disk, object_key, mime, bytes, width, height, checksum, state, uploaded_by, retained_until

protected function casts(): array {
    return [
        'bytes' => 'integer', 'width' => 'integer', 'height' => 'integer',
        'retained_until' => 'datetime',
    ];
}
protected $hidden = ['disk','object_key'];

public function user()     { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
public function variants() { return $this->hasMany(MediaVariant::class, 'member_media_id'); }
public function uploadedBy(){ return $this->belongsTo(\App\Models\User::class, 'uploaded_by'); }
public function scopeClean($q) { return $q->where('state', 'clean'); }

// MediaVariant → ifgf_media_variants
protected $fillable = [];            // service-written entirely
protected $hidden   = ['disk','object_key'];
protected function casts(): array {
    return ['width' => 'integer', 'height' => 'integer', 'bytes' => 'integer'];
}
public function memberMedia() { return $this->belongsTo(MemberMedia::class, 'member_media_id'); }

// AttendanceMedia → ifgf_attendance_media
use SoftDeletes;
protected $fillable = ['occurrence_id','purpose'];
protected $hidden   = ['disk','object_key'];
protected function casts(): array {
    return ['bytes' => 'integer', 'retained_until' => 'datetime'];
}
public function occurrence() { return $this->belongsTo(EventOccurrence::class, 'occurrence_id'); }
public function uploadedBy() { return $this->belongsTo(\App\Models\User::class, 'uploaded_by'); }
```

**`object_key` and `disk` are hidden on all three.** Suite 11 measured that member-photo privacy
in this application rests **entirely** on a 40-character `hashName()` filename — there is no private
disk, no `temporaryUrl()`, no signed route anywhere in `app/`. Under those conditions the object key
*is* the access token, and serialising it into a JSON response is the whole breach. Hidden by
default; `MemberMediaService` is the only thing that reads them.

---

## 20.9 `Consent` → `ifgf_consents`

```php
protected $fillable = ['user_id','consent_type','policy_version','method','actor_id'];
// SERVICE-WRITTEN: granted_at, revoked_at

protected function casts(): array {
    return ['granted_at' => 'datetime', 'revoked_at' => 'datetime'];
}

public function user()  { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
public function actor() { return $this->belongsTo(\App\Models\User::class, 'actor_id'); }

public function scopeActive($q)  { return $q->whereNotNull('granted_at')->whereNull('revoked_at'); }
public function scopeOfType($q, string $t) { return $q->where('consent_type', $t); }

public function isActive(): bool {
    return $this->granted_at !== null && $this->revoked_at === null;
}
```

**No `hasConsent()` helper on the model, and no `$user->hasConsent('x')` accessor.** Consent is
never bundled (invariant 33, FR-11.18) and a convenience accessor is precisely how bundling happens
— someone caches "the user consented" and reuses it for a different type. Every check names its
type at the call site: `Consent::forUser($id)->ofType('group_photo_matching')->active()->exists()`.

Types: `profile_display|biometric_enrollment|live_recognition|group_photo_matching|privacy_policy`.
Profile-display consent **never** implies recognition consent.

---

## 20.10 Calendar — `CalendarLink`, `CalendarViewerAccess`

Release 1b (FR-08 is not Phase 1A). Specified for completeness; **do not fill bodies before the
R1a packages are done.**

```php
// CalendarLink → ifgf_calendar_links
protected $fillable = ['user_id','provider_calendar_id','provider_event_id'];
// SERVICE-WRITTEN: sync_hash, state, last_synced_at, last_error
protected function casts(): array { return ['last_synced_at' => 'datetime']; }
protected $hidden = ['provider_calendar_id','provider_event_id'];
public function user() { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
public function scopeFailed($q) { return $q->where('state', 'failed'); }

// CalendarViewerAccess → ifgf_calendar_viewer_access
protected $fillable = ['user_id','google_email','actor_id'];
// SERVICE-WRITTEN: grant_state, granted_at, revoked_at
protected function casts(): array { return ['granted_at' => 'datetime', 'revoked_at' => 'datetime']; }
public function user()  { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
public function actor() { return $this->belongsTo(\App\Models\User::class, 'actor_id'); }
```

FR-11's rule, worth restating at the model: **an application role never implies the external ACL
exists.** A `leader` role and a `granted` row are two separate facts and only the second one means
the person can actually open the calendar.

---

## 20.11 Import pipeline models

```php
// ImportBatch → ifgf_import_batches
protected $fillable = ['source','source_file_hash','extracted_at','importer_version','operator_id'];
// SERVICE-WRITTEN: status, rows_* counters, rollback_deadline
protected function casts(): array {
    return [
        'extracted_at' => 'datetime', 'rollback_deadline' => 'datetime',
        'rows_total' => 'integer', 'rows_inserted' => 'integer', 'rows_updated' => 'integer',
        'rows_skipped' => 'integer', 'rows_conflicted' => 'integer', 'rows_errored' => 'integer',
    ];
}
public function sourceRecords() { return $this->hasMany(ImportSourceRecord::class, 'import_batch_id'); }
public function conflicts()     { return $this->hasMany(ImportConflict::class, 'import_batch_id'); }
public function exceptions()    { return $this->hasMany(ImportException::class, 'import_batch_id'); }
public function operator()      { return $this->belongsTo(\App\Models\User::class, 'operator_id'); }
public function isCommitted(): bool { return $this->status === 'committed'; }

// ImportSourceRecord → ifgf_import_source_records
protected $fillable = ['import_batch_id','source_key','payload_hash','raw_payload'];
// SERVICE-WRITTEN: target_type, target_id, outcome, reason
protected function casts(): array { return ['raw_payload' => 'array']; }
public function batch()     { return $this->belongsTo(ImportBatch::class, 'import_batch_id'); }
public function conflicts() { return $this->hasMany(ImportConflict::class, 'source_record_id'); }
public function target()    { return $this->morphTo(__FUNCTION__, 'target_type', 'target_id'); }

// SourceIdentityMap → ifgf_source_identity_maps
protected $fillable = ['source','source_key','target_type','target_id'];

// ImportConflict → ifgf_import_conflicts
protected $fillable = ['import_batch_id','source_record_id','field','workbook_value','export_value'];
// SERVICE-WRITTEN: chosen_value, resolution, reviewed_by, reason, reviewed_at
protected function casts(): array { return ['reviewed_at' => 'datetime']; }
public function batch()        { return $this->belongsTo(ImportBatch::class, 'import_batch_id'); }
public function sourceRecord() { return $this->belongsTo(ImportSourceRecord::class, 'source_record_id'); }
public function reviewer()     { return $this->belongsTo(\App\Models\User::class, 'reviewed_by'); }
public function scopePending($q) { return $q->where('resolution', 'pending'); }

// ImportException → ifgf_import_exceptions
protected $fillable = ['import_batch_id','source_key','kind','detail'];
public function batch() { return $this->belongsTo(ImportBatch::class, 'import_batch_id'); }
```

**`raw_payload` casts to `array`, and the column holds the source row verbatim.** FR-12.8 — no
source row is silently discarded — is only true if the payload survives a failed parse. Cast it,
never trim it, and never normalise on the way in: normalisation belongs downstream where it can be
re-run.

**`target()` is a `morphTo` with no morph map entry.** `target_type` stores the FQCN, which for
package models is `Ifgf\ChurchOperations\Models\MemberProfile` — 44 characters in every row. Add a
`Relation::enforceMorphMap([...])` in the service provider during the fill pass so the stored value
is `member_profile`, and do it **before the first import runs**: changing it afterwards means
rewriting every `target_type` and `ifgf_source_identity_maps.target_type` value in place.

---

# 21. Service contracts

Fifteen classes, namespaced `Ifgf\ChurchOperations\{Services,Support,Import}`, generated by
`GENERATION_MANIFEST.md` Section 3. **Every signature below is the contract**; the fill pass writes
bodies to match and does not re-decide method names, because the tests in Section 6 are written
against these names.

## 21.0 Rules that apply to all fifteen

1. **Services never resolve the actor themselves.** No `Auth::user()`, no `Auth::id()` inside any
   class below. The actor is an explicit `User $actor` parameter. A service that reads the session
   cannot be called from a console command, a queue job or an import — and all three are in scope.
   This is also why `AttendanceRecorder` can be the sole write path for both a controller and
   `ifgf:import-attendance`.
2. **Services never abort, never redirect, never return a response.** They throw typed exceptions
   from `Ifgf\ChurchOperations\Exceptions`; controllers translate. Upstream's habit of returning
   `response()->json(...)` from deep inside logic is what made the two attendance call sites
   impossible to reuse.
3. **Every mutation is inside `DB::transaction()`**, and every read-then-write inside one uses
   `lockForUpdate()`. Named explicitly per method below where it matters.
4. **Every mutation writes an audit record** through upstream's existing activity log
   (FR-11.14). ⚠ `doActivityLog()` currently 500s when its `$logname`/`$message` arguments are unset
   on a non-success path (`ExportMemberController` — recorded 2026-08-21). Call it with both
   arguments always set, never conditionally assigned.
5. **Nothing here writes real member data into a file.** Extractors take a path argument; the
   workbook is never committed and never fixtured.

---

## 21.1 `MemberQrService` — the attendance credential

```php
final class MemberQrService
{
    public function issue(MemberProfile $profile): MemberProfile;
    public function rotate(MemberProfile $profile, User $actor, string $reason): MemberProfile;
    public function resolveToken(string $token): ?MemberProfile;
    public function resolveShortCode(string $code): ?MemberProfile;
    public function payloadFor(MemberProfile $profile): string;   // the token, and nothing else
}
```

**`issue()`** — generates `qr_token` (`Str::random(32)`, `char(32)` unique), `qr_short_code`
(8 chars Crockford base32, alphabet `0123456789ABCDEFGHJKMNPQRSTVWXYZ` — no `I L O U`),
`qr_version = 1`, `qr_rotated_at = null`. Retries on unique violation, maximum 5, then throws
`QrTokenGenerationFailed`. **Idempotent**: called on a profile that already has a token, it returns
the profile untouched — it must never silently reissue, because reissuing invalidates a printed
card.

**`rotate()`** — new token **and** new short code, `qr_version++`, `qr_rotated_at = now()`, in one
transaction with `lockForUpdate()` on the profile row. **No grace period**: the previous code stops
resolving the instant the transaction commits (FR-02.7.3). Writes an audit record carrying actor and
reason. Rotating is what a member does when a card is lost, so the audit entry is the pastoral
record of that event.

**`resolveToken()` / `resolveShortCode()`** — return the profile or `null`. **They must not
distinguish "no such token" from "token belongs to another church" in their return value or in
timing**, and the caller must not either: FR-02.7.4's threat is an actor producing valid codes
without ever seeing a card, and a distinguishable response turns the scan endpoint into an oracle
for testing guesses. One shape for both: `404 member not found`.

**Not this service's job, and must be enforced at the call site:** rate limiting (FR-04.4.1),
occurrence scope, and the leader's assignment. `resolveToken()` answers *who*, never *whether they
may*.

**Never**: a signed URL, an expiring URL, a URL at all, a sequential id, a name, an email, a
branch, a group, or any self-describing structure in the payload (FR-02.7.2, FR-02.7.5). `payloadFor()`
returns the bare token so that no caller assembles a URL "for convenience".

---

## 21.2 `AttendanceRecorder` — the sole attendance write path

The single most constrained class in Release 1. **The two call sites to reroute are
`Api\AttendanceController::scan()` and `Admin\EventAttendanceController::markAttendee()`** — both
currently call `EventAttendee::create()` directly, and both were read at `3793b54`.

```php
final class AttendanceRecorder
{
    public function record(RecordAttendance $command): AttendanceResult;
    public function remove(AttendanceDetail $detail, User $actor, string $reason): void;
    public function finalize(EventOccurrence $occurrence, User $actor): FinalizationSummary;
    public function reopen(EventOccurrence $occurrence, User $actor, string $reason): void;
}

final class RecordAttendance          // DTO, Support/ or Services/, readonly
{
    public function __construct(
        public readonly EventOccurrence $occurrence,
        public readonly int    $userId,
        public readonly string $status,             // present|absent|excused
        public readonly ?string $participationMode, // onsite|online — MUST be null when absent
        public readonly string $captureMethod,      // manual|member_qr|import|zoom_import|photo_suggestion
        public readonly User   $actor,
        public readonly ?int   $importBatchId = null,
        public readonly ?string $verificationStatus = null,
    ) {}
}

final class AttendanceResult
{
    public readonly AttendanceDetail $detail;
    public readonly bool $wasAlreadyRecorded;   // FR-04.7 — duplicate is a RESULT, not an error
}
```

### The write algorithm — this is the contract, not an implementation sketch

```
DB::transaction(function () {
  1. assert $occurrence->isWritable()        else throw OccurrenceNotWritable   (FR-04.8)
  2. assert status/mode agree                else throw InvalidAttendanceState  (FR-04.3)
  3. lockForUpdate the ifgf_attendance_details row for (occurrence_id, user_id)
  4. if it exists → return AttendanceResult(existing, wasAlreadyRecorded: true)   (FR-04.7)
  5. if status === 'present':
       a. session = $occurrence->session ?? lazily create event_attendance_sessions   (A2)
       b. create event_attendees row  (session_id, church_id, event_id, user_id,
                                       scanned_at = now(), scanned_by = actor->id)
       c. catch QueryException 23000 on unique (session_id, user_id) →
          re-read and return wasAlreadyRecorded: true   — NEVER swallow, never retry blind
     else:
       no event_attendees row is written.  absent and excused are IFGF-side only.   (A3)
  6. create ifgf_attendance_details with event_attendee_id set (present) or NULL
  7. audit: actor, timestamp, source, capture_method, occurrence           (FR-04.10)
});
```

**Step 5c is not defensive coding.** `event_attendees` carries a real `UNIQUE (session_id, user_id)`
— the duplicate-scan guard is a **database constraint, not controller logic** (pinned by
characterization). Two ushers scanning the same member simultaneously is the normal case at a door,
not an edge case, and the constraint is what actually prevents the double row. The recorder must
keep it and must translate its violation into the FR-04.7 "already checked in" result.

**Step 5a is where A2's known limitation surfaces at runtime.** Lazy session creation hits upstream's
`unique(event_id, attendance_date)`; a second same-day occurrence for the same event throws. Catch
it and throw `SameDayOccurrenceNotSupported` with the WP 0C item 5 reference in the message, so the
day it happens the operator gets an explicable failure rather than a 500.

**`finalize()`** — sets `status = 'finalized'`, `finalized_at`, `finalized_by`. For a **closed-roster**
occurrence it first writes the FR-04.6 snapshot: an explicit `absent` detail row for every rostered
member with no row. For an **open-audience** occurrence it writes **nothing** — present records only.
Returns counts plus the exception list of missing required reasons (FR-05.10).

> 🔴 **`finalize()` on a closed roster is the one method in Release 1 that manufactures pastoral
> data.** Every `absent` row it writes is an assertion nobody made by hand. It is legitimate only
> because the roster defines who was expected and the occurrence is being closed deliberately by a
> named actor — and it must never run on an open-audience occurrence, never run automatically on a
> schedule, and never be back-applied to historical imports. FR-10's inactive-risk report reads these
> rows. **A missing row is still not absence, and `finalize()` is the only thing allowed to turn one
> into the other.**

**`reopen()`** — `status = 'reopened'`, mandatory non-empty `$reason` into `reopen_reason`
(FR-04.8), audited, admin-only at the policy layer. ⚠ Upstream's `unlock()` records **no reason and
has nowhere to put one**; that endpoint must be rerouted here, not extended.

**`remove()`** — deletes the detail row and its `event_attendees` row together, with reason and
audit. Reroute `Admin\EventAttendanceController::removeAttendee()`.

**Not this service's job:** authorization. The recorder assumes the caller checked scope. FR-11.11's
"a leader may record attendance only within an assigned scope" is `EventOccurrencePolicy` +
`AttendanceDetailPolicy` — and **SEC-002 is the standing warning here**: `event_managers` exists,
`openSession`/`markAttendee`/`lock`/`unlock` never consult it, and that control has to be **built**,
not adjusted. Rerouting the write path does not fix SEC-002 and must not be reported as fixing it.

---

## 21.3 `RoleAssignmentService`

FR-11.1–11.8 and PRD §4.3 L423. Three assignable roles: `member`, `leader`, `admin`.

```php
final class RoleAssignmentService
{
    public function assignAtActivation(User $user, string $role, User $actor): void;
    public function replace(User $user, string $role, User $actor, string $reason): void;
    public function roleOf(User $user): ?string;
    public function assertNotLastActiveAdmin(User $user): void;   // throws LastAdminProtected
}
```

- **Exactly one role.** `replace()` removes the existing role and attaches the new one **in one
  transaction**; it never appends. `assignAtActivation()` refuses when the user already has one and
  refuses to complete with zero (FR-11.8).
- **Row locks.** `lockForUpdate()` on the `users` row, and on the admin count when the change could
  remove an admin. Two concurrent demotions must not both pass the "there is another admin" check —
  that is the specific race FR-11.8 names.
- **Final-admin protection** in `assertNotLastActiveAdmin()`, counting **active, non-soft-deleted**
  admins inside the same locked transaction.
- **Invalidate immediately** — Laratrust's permission cache for that user, and the user's sessions
  (FR-11.7). A role change that leaves a live session with stale permissions is the failure this
  clause exists to prevent.
- **Audit every change**: actor, subject, from-role, to-role, reason, timestamp.

> 🔴 **`usergroup_id` is NOT this service's business in Release 1.** SEC-001 — the one-line alias
> making `usergroup_id == 3` bypass every permission check — stays until FR-11's mapping and cutover
> report land (FR-11.9). Removing it today locks out every church admin. `RoleAssignmentService`
> writes Laratrust roles **alongside** the legacy field and asserts nothing about it. The
> characterization test pinning the bypass must be **replaced, not deleted**, when the cutover
> happens.

**Schema dependency, not in the 21 tables:** FR-11.2 requires `unique (user_id, user_type)` on
upstream `role_user`. That is an expand/contract change to an upstream table, gated on
characterization (WP 0C). Until it exists the "exactly one role" invariant is enforced **only** in
this service, and a concurrent write can still create a second row. Say so in the fill pass; do not
imply the database enforces it yet.

---

## 21.4 `GroupMembershipService`

FR-05, effective-dated, invariants 16 and 29.

```php
final class GroupMembershipService
{
    public function join(User $member, Group $group, string $role, CarbonInterface $from,
                         bool $primary, User $actor): GroupMembership;
    public function transferPrimary(User $member, Group $toGroup, CarbonInterface $on,
                                    User $actor, string $reason): GroupMembership;
    public function leave(GroupMembership $membership, CarbonInterface $on, User $actor): void;
    public function activePrimaryFor(User $member): ?GroupMembership;
    public function rosterAsOf(Group $group, CarbonInterface $date): Collection;
}
```

**`transferPrimary()` is one transaction and the order is fixed** (FR-05.11): lock the member's
active primary row → set its `effective_to = $on` → insert the new membership with
`effective_from = $on`, `is_primary = true` → reconcile the upstream `group_links` projection →
audit. A transfer that closes the old row and fails to open the new one leaves a member in no iCare
group at all, which is why it is not two calls.

**Invariant 16 — one active primary membership — is enforced twice, deliberately.** The partial
unique index from Part 1 §8 catches the race; `lockForUpdate()` avoids it. Neither alone is enough:
the index alone turns a normal concurrent transfer into a user-visible 500, and the lock alone
cannot stop a write from a path that forgot to take it.

**The `group_links` projection (invariant 29) is this service's responsibility and nothing else's.**
`projected_group_link_id` is a nullable unique FK with `nullOnDelete`. Opening a primary membership
creates or reuses a `GroupLink`; closing one soft-deletes it. `nullOnDelete` means an upstream hard
delete degrades the projection to NULL rather than destroying the IFGF membership history — **the
pastoral record survives an upstream deletion**, which is the whole point of the sidecar pattern.

**`rosterAsOf()` uses `scopeAsOf`** from §20.7 and is the only sanctioned way to answer "who was in
this group on that date". Historical attendance reports must call it rather than reading
`group_links`, which has no history at all.

---

## 21.5 `MemberMediaService`

FR-01.9–01.12, FR-11.11, and the direct beneficiary of the SEC-003 findings.

```php
final class MemberMediaService
{
    public function ingest(User $member, UploadedFile $file, string $purpose, User $actor): MemberMedia;
    public function promote(MemberMedia $media, User $actor): MemberMedia;        // quarantine → clean
    public function reject(MemberMedia $media, string $reason, User $actor): void;
    public function retire(MemberMedia $media, User $actor): void;                // replace/remove
    public function variantsFor(MemberMedia $media): Collection;
    public function temporaryUrl(MemberMedia $media, string $variant, int $ttlSeconds = 300): string;
}
```

**Validation is by content, never by filename.** Build the MIME check from real bytes.
`UploadedFile::fake()` derives its MIME from the filename and **cannot settle any question about
file-type handling** — it is what inflated SEC-003 into a false RCE rating on 2026-08-21, the
fourth time this project asserted against the harness instead of the application.

**🔴 Do not use Laravel's `image` rule.** It **allows SVG** (`ValidatesAttributes` excludes SVG only
when `allow_svg` is absent — and the rule's default admits it), and SVG is the classic stored-XSS
carrier. Use an explicit `mimes:` allow-list — `jpeg,png,webp,heic` — and treat any future
"simplification" back to `image` as a regression.

**`ingest()`** — validate by content → strip EXIF (FR-01.12; GPS in a member photo is a
location disclosure) → compute checksum → write to the **configured** disk under an opaque
`object_key` that is never derived from a member name → `state = 'quarantine'` → record
`uploaded_by` and `retained_until`. Variants are generated on `promote()`, not on ingest, so a
rejected upload never produces derivatives.

> ⚠ **There is no private disk in this application today.** All four disks are public and `uploads`
> is rooted at `public_path()` itself; there is no `temporaryUrl()`, signed route or
> `hasValidSignature()` call anywhere in `app/`. `temporaryUrl()` above therefore **has no working
> backend yet** and Release 1 must create one — a private disk plus a signed-access route. Filling
> this method against a public disk would produce a URL that looks signed and protects nothing.
> **This is a prerequisite, not a detail:** FR-11.11 requires private storage with expiring access
> for exactly these objects. It should be sized together with the SEC-003 residue — an allow-list
> across `Common::uploadFile()`'s **47 call sites** is a worse answer than moving member media off a
> public disk.

**`retire()`** — retires the old object without touching historical attendance snapshots
(FR-01.11), revokes new signed access immediately, and schedules object plus variant deletion.
Soft-delete the row; the cascade on `ifgf_media_variants` is the **one** legitimate cascade in this
schema.

---

## 21.6 `MemberRegistryService`

FR-01, FR-02. The orchestrator for member lifecycle; it owns the transaction that spans
`users` + `userprofiles` + `ifgf_member_profiles` + `ifgf_contact_points`.

```php
final class MemberRegistryService
{
    public function register(RegisterMember $command): MemberProfile;   // self-service, FR-02.2
    public function createByAdmin(RegisterMember $command, User $actor): MemberProfile;
    public function update(MemberProfile $profile, array $attributes, User $actor): MemberProfile;
    public function changeStatus(MemberProfile $p, string $status, User $actor, string $reason): void;
    public function candidateDuplicates(RegisterMember $command): Collection;   // via DuplicateScorer
}
```

- **Home branch is NULLABLE in the schema and REQUIRED at registration and activation** (§13.1.19,
  FR-02.2.1). The validation lives in `StoreMemberProfileRequest` and in `changeStatus()` when moving
  to `active` — **never as a `NOT NULL` column**, because imported and unclaimed records are legitimately
  branch-neutral and a source row missing a branch must become an import exception, not a fabricated
  value.
- **Follow-up preference is not collected** — never captured in the legacy form, 0% filled, removed
  from FR-02.2's minimum. Do not add it back because the workbook has a column.
- `register()` calls `MemberQrService::issue()` inside the same transaction, so a member never exists
  without a card credential.
- `update()` writes `pastoral_notes` only when the policy grants admin; the field is `$hidden` and
  admin-only (FR-01.8).
- Status values `visitor|active|inactive|exited`; transitions audited with a reason.
- **Merge (FR-01.7) is deliberately not in Release 1's service surface.** It must reassign attendance,
  memberships, enrolments, ministry and calendar links with a complete audit trail, and half of those
  tables are Release 2. Specifying it now would be specifying against tables that do not exist.

---

## 21.7 `OccurrenceGenerator`

```php
final class OccurrenceGenerator
{
    public function generate(EventDefinition $definition, CarbonInterface $through): Collection;
    public function generateHorizon(CarbonInterface $through): int;   // all active definitions
    public function cancel(EventOccurrence $occurrence, User $actor, string $reason): void;
    public function editSeries(EventDefinition $d, array $changes, string $scope, User $actor): void;
        // $scope: this_occurrence | this_and_following | whole_series      (FR-03.10)
}
```

- **Idempotent by `unique(event_definition_id, starts_at)`** (FR-03.5). Re-running over the same
  horizon inserts nothing and must not update existing rows — a regenerated occurrence that
  overwrites `status` would silently un-cancel cancellations.
- **RRULE is RFC 5545, expanded in the definition's IANA timezone, stored as UTC instants**
  (FR-03.9, and A0). `starts_at` is a `timestamp`, not a `date`; the local day comes from
  `EventOccurrence::localDate()`.
- **Generated occurrences retain exception and cancellation history rather than being silently
  replaced** (FR-03.10). `cancel()` sets `status = 'cancelled'`; nothing deletes an occurrence.
- DST: Taiwan has no transition, but the timezone is per-definition and Indonesian or visiting
  branches may differ, so expansion must be timezone-correct rather than offset-correct (FR-03.11).
- Creates **no** `event_attendance_sessions` rows. That is A2, and it is what makes a horizon safe.

---

## 21.8 Support — `PhoneNormalizer`, `NameNormalizer`, `DuplicateScorer`

```php
final class PhoneNormalizer
{
    public function normalize(?string $raw, string $defaultRegion = 'TW'): ?string;  // E.164 or null
    public function isPlausible(?string $raw, string $defaultRegion = 'TW'): bool;
}
```

**Returns `null` on anything unparseable — it never guesses.** The legacy `cleanPhoneNumber` no-ops
on leading zeros, so `0912…`, `+886912…` and `886912…` were three different keys and the legacy
system never caught the duplicates (`GAS_INVENTORY.md` C3). Expect the first normalised import to
surface duplicates that have existed for years. **The verbatim value stays in `value`; only
`value_normalised` is derived** — a normaliser that overwrites its input destroys the evidence for
the conflict review.

Default region `TW`, but Indonesian `+62` numbers are common in this membership and a bare `08…`
is ambiguous between the two — an ambiguous number is an **import exception**, not a coin flip.

```php
final class NameNormalizer
{
    public function key(?string $name): ?string;        // comparison key only, never stored as a name
    public function tokens(?string $name): array;
}
```

Casefold, strip diacritics, collapse whitespace, drop punctuation. **Does not split Chinese names on
spaces** — 92.2% of this membership has a `chinese_name`, and token-splitting a Han string produces
nonsense keys. Output is used for matching and **never written back over a member's name**.

```php
final class DuplicateScorer
{
    public function score(array $candidate, array $existing): DuplicateScore;   // 0.0–1.0 + reasons
    public function candidatesFor(array $candidate, int $limit = 10): Collection;
}
```

FR-01.6's three signals: normalised email, normalised phone, name + date of birth. Returns a score
**and the reasons that produced it**, because a reviewer needs to see *why* two rows matched.
**Never auto-merges and never blocks a registration** — it produces a review item. Thresholds live
in package config, not in the class.

---

## 21.9 Import — `MemberExtractor`, `AttendanceGridExtractor`, `IdentityResolver`, `ConflictRecorder`, `ReconciliationReport`

```php
final class MemberExtractor
{
    public function extract(string $path, ImportBatch $batch): int;   // rows read
}
final class AttendanceGridExtractor
{
    public function extract(string $path, ImportBatch $batch): int;
}
```

**Extractors write `ifgf_import_source_records` and `ifgf_import_exceptions`. They never write a
domain row.** That separation is what makes `--dry-run` meaningful and what makes a re-run
idempotent.

**Every source row produces exactly one of: a source record, or an exception.** FR-12.8 — no source
row is silently discarded — is an assertion the extractor must be able to prove: `rows_total` equals
`count(source_records) + count(exceptions)`, and `ReconciliationReport` checks it.

**Branch attribution for historical attendance comes from *which grid sheet the row came from*, not
from the `Lokasi` column** (owner decision, Part 1 open item 2). The extractor carries `branch_id`
down from the sheet; the scan log supplies timestamps only.

```php
final class IdentityResolver
{
    public function resolve(string $source, string $sourceKey, string $targetType): ?int;
    public function remember(string $source, string $sourceKey, string $targetType, int $targetId): void;
}
```

Deterministic lookup through `ifgf_source_identity_maps`. **Creates nothing.** Re-running an
identical source must not duplicate a domain record or repeat an external side effect (FR-12.10),
and this class is the mechanism.

```php
final class ConflictRecorder
{
    public function record(ImportSourceRecord $r, string $field, ?string $workbook, ?string $export): ImportConflict;
    public function resolve(ImportConflict $c, string $resolution, ?string $chosen, User $reviewer, string $reason): void;
}
```

**Escalate every conflict; there is no blanket precedence** (owner decision). `resolution` is
`pending|workbook|export|manual`, and a batch with pending conflicts **cannot be committed** —
that check belongs in the commit path, not in the reviewer's discipline.
**`ifgf_import_conflicts` needs an admin review UI, not just a table**; the volume is unknown until
the dry run measures it, which is precisely why manual resolution has to work from day one.

```php
final class ReconciliationReport
{
    public function forBatch(ImportBatch $batch): array;     // machine-readable, FR-12.1
    public function assertRowAccounting(ImportBatch $batch): void;   // throws on FR-12.8 violation
}
```

**Matrix and annual sheets reconcile counts; they are never converted into records** (FR-12.5).
`WORKBOOK_INVENTORY.md` already shows the two sources cannot agree — **3,227 scans cannot populate
~40,000 grid cells** — so a report that shows a clean match is a report with a bug in it. The
expected output is a disagreement with a documented size.

---

# 22. Console command contracts

Five commands, `Ifgf\ChurchOperations\Console\Commands`, registered in
`ChurchOperationsServiceProvider`. They are the operator surface for the pipeline above and contain
**no logic of their own** — each one parses options, opens or resumes an `ImportBatch`, calls
services, and prints the report.

| Signature | Contract |
|---|---|
| `ifgf:import-members {path} {--dry-run} {--commit} {--source=workbook} {--batch=}` | Exactly one of `--dry-run`/`--commit` required; **neither defaults**. `--dry-run` runs extractor + resolver + scorer and writes source records, exceptions and conflicts only. `--commit` refuses while conflicts are `pending`. `--batch=` resumes an existing dry run instead of re-extracting. |
| `ifgf:import-attendance {path} {--dry-run} {--commit} {--batch=}` | Same shape. Writes through `AttendanceRecorder` with `capture_method = 'import'` — **never direct SQL** — so imported rows obey the same invariants as scanned ones. |
| `ifgf:reconcile {--batch=}` | Read-only. Prints `ReconciliationReport::forBatch()` and exits non-zero on an FR-12.8 accounting violation, so CI can run it. |
| `ifgf:rotate-member-qr {--member=} {--branch=} {--all} {--reason=}` | `--reason` mandatory. `--all` requires an interactive confirmation and is refused with `--no-interaction` unless `--force`. Every rotation invalidates a printed card, so bulk rotation is a decision, not a maintenance task. |
| `ifgf:sync-birthdays {--dry-run}` | **R1b (FR-08).** Generated in this pass; body stays empty until the Calendar package is specified. |

**Machine-readable exception output** (FR-12.1) is a `--json` flag on all three import commands
writing to stdout, with human text on stderr, so a pipe is never corrupted by progress lines.

---

# 23. Fill-pass order — build in this sequence

Batch as the campaign prompt directs, reporting after each so Claude Code can verify incrementally.
The order is dependency-correct, and each batch is independently verifiable.

| # | Batch | Verifiable by |
|---|---|---|
| 1 | **21 migrations** | `migrate:fresh` on the disposable DB, then `SHOW CREATE TABLE` on the four Rule 0 tables to confirm INT vs BIGINT FKs actually landed |
| 2 | **21 models** | A tinker round-trip per relation; assert **no model declares `$dates`** (REG-001 guard, one grep) |
| 3 | **Support (3) + lookups seeder** | `PhoneNormalizerTest`, `DuplicateScorerTest` — both unit, both already in the manifest |
| 4 | **Services (7)** | `MemberRegistrySchemaTest`, `MemberQrTest` |
| 5 | **Policies (5)** | Role matrix per FR-11.3–11.5 |
| 6 | **Import (5) + commands (5)** | `ImportDryRunTest` with a **synthetic** workbook — never the real one |
| 7 | **Controllers (3) + requests (2)** | `MemberPortalTest` |

**Rule 0 is verified in batch 1 or not at all.** `$table->foreignId()` reaching a `users` FK fails
with errno 150 at migrate time, which is loud — but `unsignedInteger` where `unsignedBigInteger`
belonged succeeds quietly and silently truncates at 4.29 billion. Read the actual `SHOW CREATE
TABLE` output; do not infer the width from the migration source.

---

# 24. Open items and owner decisions

## Carried from Part 1, still open

| # | Item |
|---|---|
| 1 | `ifgf_group_memberships`'s partial unique index needs a raw `CREATE UNIQUE INDEX` in `up()` — Laravel's builder cannot express it. Write it explicitly and test the race. |
| 2 | Historical branch attribution comes from the grid **sheet**, not `Lokasi`. Settled; restated in §21.9 because the extractor is where it gets implemented. |
| 4 | `occurrence_token` (QR-B) stays a column; the feature is not MVP. |

## New, from this pass

| # | Item |
|---|---|
| 5 | **No private disk exists.** `MemberMediaService::temporaryUrl()` has no working backend until Release 1 creates a private disk plus a signed-access route (§21.5). Size it with the SEC-003 residue — 47 `Common::uploadFile()` call sites. |
| 6 | **`unique(user_id, user_type)` on upstream `role_user`** is required by FR-11.2 and is not among the 21 tables. Expand/contract on an upstream table, gated on characterization. Until it lands, "exactly one role" is service-enforced only. |
| 7 | **Upstream `unique(event_id, attendance_date)` must be dropped** (WP 0C item 5) before any event can hold two occurrences on one Taipei day. Not a Phase 1A blocker; a hard blocker for a second same-day service. |
| 8 | **Morph map before the first import** (§20.11). Changing `target_type` after rows exist means rewriting every value in two tables. |
| 9 | **`doActivityLog()` 500s on unset arguments** on non-success paths. Every service audit call must pass both arguments unconditionally, and the upstream defect needs its own entry. |

## ▶ Decisions this pass needs from the owner

- **✅ D4 — Timezone. RESOLVED 2026-08-21.** UTC at rest confirmed, UP-009 stands, and the
  `Asia/Taipei` / `+08:00` line is **struck from `tools/CAMPAIGN_PROMPT.md` section B**. Note what
  the near-miss was: that prompt was written **2026-08-21**, six days *after* the decision, so this
  was not a stale line surviving — it was a **rejected contract being re-introduced into standing
  instruction by a fresh document**. A settled decision is only settled in the artifacts that were
  reconciled to it. **§20.0 A0.**
- **D5 — Short code on the printed card.** Approve `qr_short_code char(8)` (Crockford base32,
  rotates with the token, 40 bits) as the FR-02.7.6 manual-entry fallback, or accept that an usher
  cannot complete check-in when a camera fails. **§20.0 A1.**
- **D6 — Absence is representable.** Approve the `ifgf_attendance_details` amendment: nullable
  `event_attendee_id`, added `user_id`, `unique(occurrence_id, user_id)`, and the rule that only
  `present` writes an upstream `event_attendees` row. **§20.0 A3.** This is the one that changes what
  the database can say, and `finalize()` on a closed roster is the only thing permitted to assert
  absence.

## Deliberately excluded from Release 1 — unchanged

`ifgf_programs*` (6, CGSL) · `ifgf_ministries*` (2) · `ifgf_event_registrations`. Nine tables.
Also excluded from this specification: **FR-01.7 merge** (§21.6), **FR-14 biometrics** in every
form, and Worship Night / Zoom import beyond the `zoom_import` capture-method string.

## What Part 2 does NOT establish

It is a specification, not evidence. **Nothing here has been executed** — this session had no PHP,
ran no migration, and instantiated no model. Every signature is a claim about what should exist, and
the first `migrate:fresh` is what turns Rule 0 from an argument into a fact. The four amendments in
§20.0 are reasoning from the PRD and from measured upstream behaviour; they have not been tested,
because the tables do not exist yet. Read §23 as the order in which those claims get checked.

---

# Part 3 — iCare weekly attendance (§25), R1b

**Written 2026-08-21 in Cowork.** Specification only. **0 `ifgf_` migrations exist**, the tables in
Part 1 have not been created, and no PHP was written or run in this session. Part 3 completes
§8b/§8c: those sections established *what columns iCare needs*; this one establishes *the service,
the date rule, the screen, the authorization and the tests* — everything the fill pass needs so
that building iCare is transcription rather than design.

Sources: §8b/§8c (owner spec 2026-08-11) · §20.0 A0–A3 · §20.5–20.7 · §21.0/21.2/21.4/21.6 ·
`PRD.md` FR-03 L991–1004, FR-04 L1005–1026, FR-05 L1027–1039, FR-11 L1104–1126 (line-ranged) ·
`CONTEXT.md` (UP-009, SEC-001, SEC-002, the two-gate surface) · `MEMORY.md` sessions 6 and 9.

**Face detection is out of scope here and stays out.** §8c requirement 6 is a stored file and
nothing more; detection is Phase 2B behind WP 0D (`ICARE_PHOTO_ATTENDANCE.md`). Nothing in Part 3
reads a photo, infers a person from one, or creates a capture method that implies it.

---

## 25.0 Amendments Part 3 requires — A4 … A7

Specifying the iCare flow against Parts 1 and 2 surfaced four places where the schema and the
service contracts as drawn **cannot express what §8c asks for**. They follow the §20.0 pattern:
each is stated with its reasoning, and each is marked at the line it changes. **Unlike A0–A3 these
are NOT yet patched into Parts 1 and 2** — they are collected here so the owner reviews them as one
set before the migrations are generated. A4 and A6 change columns; A5 changes a DTO; A7 changes a
value written to an upstream table.

### A4 🔴 An iCare group cannot own an occurrence as the schema stands

`ifgf_event_occurrences` belongs to `ifgf_event_definitions`, and a definition is a **sidecar on
upstream `events`** — `event_id` is `unsignedInteger`, `unique`, **NOT NULL**. An iCare group is a
row in upstream `groups`. There is no path from a group to an occurrence, so "find-or-create the
occurrence for group G on date D" cannot be written.

Worse, the gap is not fixable by relaxing `event_id` to nullable, because
`AttendanceRecorder` step 5a **lazily creates an `event_attendance_sessions` row**, and that table
requires `event_id`. The moment one member is marked present at an iCare meeting, an upstream
`events` row must exist. **A backing `events` row is forced by the write path, not chosen.**

Amendment, two parts:

```php
// add to ifgf_event_definitions
$table->unsignedInteger('group_id')->nullable()->unique();   // INT — Rule 0
$table->foreign('group_id')->references('id')->on('groups');
// event_id stays NOT NULL unique. group_id is an ADDITIONAL link, not an alternative to it.
```

- Every iCare group gets **exactly one** backing upstream `events` row and **exactly one**
  `ifgf_event_definitions` row, `event_type_id` → the `icare` type, `group_id` set, `branch_id`
  from `ifgf_group_profiles.branch_id`, `rrule` **null** (§8b's argument: a weekday integer, not an
  RRULE), `audience = 'group'`, `timezone` from the branch.
- `unique` on `group_id` is what makes "the iCare definition for group G" a single row rather than
  a query that can return two.
- Creation is `IcareAttendanceService::ensureDefinition()` (§25.1), idempotent, **never** a seeder
  and **never** a migration — groups are created by the import and by admins at runtime.

**Why not model an iCare as an event and skip `groups` entirely.** Because the roster, the
leadership and the membership history all live in `ifgf_group_memberships` keyed on `group_id`
(§8, FR-05.2), and `GroupMembershipService::rosterAsOf()` is the only sanctioned roster query.
Splitting the roster from the occurrence would mean two sources of truth for who is in an iCare.

**Consequence to state plainly:** creating an iCare group now writes an upstream `events` row.
That row appears in upstream's event lists, exports and counts. **The `icare` event type is the
seam that keeps it distinguishable**, and any upstream report that must exclude iCare filters on
it. This is a visible side effect on an upstream surface and needs the owner's eyes — decision D7.

### A5 `RecordAttendance` cannot carry §8c's visitor columns

§8c requirement 4 added `is_visitor` and `visitor_from_group_id` to `ifgf_attendance_details`. But
`AttendanceDetail::$fillable` is **intentionally empty** (§20.6) and `AttendanceRecorder` is the
sole write path, so the only way a value reaches those columns is through the `RecordAttendance`
DTO — which has no fields for them. As drawn, **the two columns are unwritable.**

Amendment to §21.2's DTO:

```php
public readonly bool  $isVisitor = false,
public readonly ?int  $visitorFromGroupId = null,
public readonly ?string $note = null,              // A6
```

with a rule enforced in the recorder alongside FR-04.3's status/mode check:
**`visitorFromGroupId` non-null requires `isVisitor === true`** — else `InvalidAttendanceState`.
A `visitor_from_group_id` on a non-visitor row is a transfer that never happened.

### A6 No column holds FR-05.5's note or FR-05.10's required reason

FR-05.5 lists *"present onsite, present online, absent, excused, notes"* and FR-05.10 requires
finalization to produce *"an exception list for missing required reasons."* `ifgf_attendance_details`
has neither a note nor a reason column, so FR-05.10's exception list is not computable from the
schema — there is no field that can be missing.

Amendment — **one** column, not two:

```php
// add to ifgf_attendance_details
$table->text('note')->nullable();
```

and the rule: **`status = 'excused'` requires a non-empty `note`.** Validated at write time
(`InvalidAttendanceState`), so a well-formed row can never be an exception; `finalize()`'s exception
list then catches only rows that arrived by another route — import, backfill, or a row written
before this rule existed. One column rather than a separate `excuse_reason` because a leader typing
on a phone will use one box, and two boxes produce one empty box.

**`note` is pastoral free text about a named member.** It is not `$hidden` like `pastoral_notes`
(§21.6) but it must be excluded from every export and every member-facing view until FR-11.11's
privacy review says otherwise — decision D8.

### A7 What `event_attendance_sessions.attendance_date` receives on the iCare path

The lazy session created in `AttendanceRecorder` step 5a must put *something* in upstream's
`attendance_date`, which is a `date()` column that never converts (UP-009, pinned by
`test_documents_early_taipei_services_resolve_to_the_previous_utc_day`).

**Rule: `attendance_date = $occurrence->localDate()->toDateString()`** — the branch-local calendar
day of `starts_at`. Never `now()`, never `$occurrence->starts_at->toDateString()` (which is the UTC
day), never the leader's clock.

Two things follow, and both must be said out loud:

1. **This deliberately differs from what upstream's own controllers write today.** Upstream derives
   the date from the server instant; the IFGF path derives it in the branch timezone. The
   characterization test above pins the **upstream controller path** and stays green — Part 3
   changes nothing upstream. But the column now holds two semantics depending on which path wrote
   the row, and WP 0C's reconciliation must know that.
2. **Nothing in IFGF reporting may read `attendance_date`.** It is a projection kept correct for
   upstream's benefit. The source of truth for an occurrence's day is
   `EventOccurrence::localDate()` (§20.5), and every iCare report calls it.

Decision D9 records the alternative (write the UTC day, keep the column internally consistent, and
accept that an early-morning occurrence files under the wrong day upstream). **Recommended: the
local day.** A column whose name is `attendance_date` should hold the date attendance was taken.

---

## 25.1 `IcareAttendanceService` — the contract

```php
namespace Ifgf\ChurchOperations\Services;

final class IcareAttendanceService
{
    // ---- setup -------------------------------------------------------------
    public function ensureDefinition(Group $group): EventDefinition;

    // ---- date --------------------------------------------------------------
    public function branchTimezoneFor(Group $group): string;
    public function defaultLocalDate(Group $group, ?CarbonInterface $at = null): ?CarbonImmutable;

    // ---- occurrence --------------------------------------------------------
    public function occurrenceFor(Group $group, CarbonImmutable $localDate): EventOccurrence;
    public function findOccurrence(Group $group, CarbonImmutable $localDate): ?EventOccurrence;

    // ---- screen ------------------------------------------------------------
    public function loadRoster(Group $group, CarbonImmutable $localDate): IcareRoster;
    public function suggestionsFrom(EventOccurrence $occurrence): Collection;  // user ids, present-only

    // ---- writes (all through AttendanceRecorder) ---------------------------
    public function saveRoster(SaveIcareRoster $command): IcareSaveSummary;
    public function amend(AttendanceDetail $detail, string $status, ?string $mode,
                          ?string $note, User $actor, string $reason): AttendanceDetail;
    public function addVisitor(EventOccurrence $occurrence, User $visitor, ?Group $fromGroup,
                               string $mode, User $actor): AttendanceResult;
    public function quickAddUnclaimed(EventOccurrence $occurrence, QuickAddVisitor $command,
                                      User $actor): AttendanceResult;
    public function attachPhoto(EventOccurrence $occurrence, UploadedFile $file,
                                User $actor): AttendanceMedia;

    // ---- close -------------------------------------------------------------
    public function finalize(EventOccurrence $occurrence, User $actor): FinalizationSummary;
}
```

§21.0's five rules apply unchanged and are not restated: **no `Auth::` inside this class**, no
aborts or responses (typed exceptions from `Ifgf\ChurchOperations\Exceptions` only), every mutation
in `DB::transaction()` with `lockForUpdate()` on read-then-write, every mutation audited with both
`doActivityLog()` arguments **unconditionally** set (§21.0 rule 4 — the upstream method 500s on an
unset argument), and no real member data in any fixture.

### 🔴 The rule that outranks every method above

**Every attendance write in this service goes through `AttendanceRecorder`. This class never
touches `event_attendees`, never touches `ifgf_attendance_details`, and never issues an
`INSERT`/`UPDATE` against either.** It builds `RecordAttendance` DTOs and calls
`AttendanceRecorder::record()`; it deletes through `AttendanceRecorder::remove()`; it closes through
`AttendanceRecorder::finalize()`. `AttendanceDetail::$fillable = []` (§20.6) is the mechanical guard
— a fill pass that "helpfully" writes a detail row here gets a `MassAssignmentException`, and if it
works around that by assigning properties directly, **the guarantee is gone with nothing failing to
say so.** That is the single thing a reviewer of the fill pass must check.

### DTOs

```php
final class IcareRoster                     // readonly, screen state — writes nothing
{
    public readonly Group             $group;
    public readonly CarbonImmutable   $localDate;      // resolved, branch-local
    public readonly string            $timezone;       // IANA, for display
    public readonly ?EventOccurrence  $occurrence;     // NULL until the first save — see below
    public readonly Collection        $rows;           // RosterRow[]
    public readonly Collection        $visitors;       // RosterRow[] already recorded as visitors
    public readonly Collection        $returningVisitors; // suggestions only, never pre-ticked
    public readonly ?CarbonImmutable  $suggestedFrom;  // the occurrence the pre-ticks came from
    public readonly string            $state;          // §25.3 — one of six
    public readonly CarbonImmutable   $loadedAt;       // concurrency baseline, echoed back on save
}

final class RosterRow
{
    public readonly int      $userId;
    public readonly string   $displayName;
    public readonly ?int     $detailId;        // existing detail, if this occurrence already has one
    public readonly ?string  $status;          // recorded status, NULL = nothing recorded
    public readonly ?string  $mode;
    public readonly ?string  $note;
    public readonly bool     $isVisitor;
    public readonly ?int     $visitorFromGroupId;
    public readonly bool     $suggestedPresent;   // pre-tick — a DEFAULT, never a recorded value
}

final class SaveIcareRoster
{
    public readonly Group           $group;
    public readonly CarbonImmutable $localDate;    // carried from the load, NOT re-derived
    public readonly array           $marks;        // MarkInput[] — explicit, one per member acted on
    public readonly User            $actor;
    public readonly CarbonImmutable $loadedAt;     // optimistic-concurrency baseline
}

final class MarkInput
{
    public readonly int      $userId;
    public readonly string   $status;      // present|absent|excused
    public readonly ?string  $mode;        // onsite|online — NULL when absent/excused (FR-04.3)
    public readonly ?string  $note;
    public readonly ?int     $seenDetailId;   // what the leader's screen showed, or null
}

final class QuickAddVisitor
{
    public readonly string  $displayName;      // required — the only required field
    public readonly ?string $phone;            // normalised via PhoneNormalizer, never guessed
    public readonly ?int    $branchId;         // nullable — an unclaimed record is branch-neutral
    public readonly string  $mode;             // onsite|online
    public readonly ?string $note;
}

final class IcareSaveSummary
{
    public readonly EventOccurrence $occurrence;
    public readonly int   $created;
    public readonly int   $alreadyRecorded;   // FR-04.7 — a duplicate is a result, not an error
    public readonly int   $amended;
    public readonly array $conflicts;         // ConflictRow[] — written by someone else since load
    public readonly array $rejected;          // RejectedRow[] — validation failures, with reason
}
```

### `ensureDefinition()` — A4 made into one method

Find-or-create, idempotent, in one transaction with `lockForUpdate()` on the `groups` row:

```
1. definition = ifgf_event_definitions where group_id = G  → return if found
2. create upstream events row:
     name        = G->name  (the iCare group's own name)
     church_id   = G->church_id
     event type  = the ifgf_event_types row with code 'icare'
   — created here rather than by an admin, because an iCare group's meeting is not an
     event anyone schedules by hand (FR-05.1)
3. create ifgf_event_definitions:
     event_id    = the row from 2          (NOT NULL, unique — unchanged)
     group_id    = G->id                   (A4)
     branch_id   = ifgf_group_profiles.branch_id for G
     timezone    = the branch's timezone   (§25.2)
     audience    = 'group'                 (FR-03.4 — iCare group)
     delivery_mode = ifgf_group_profiles.meeting_mode
     rrule       = NULL                    (§8b — a weekday integer, deliberately not an RRULE)
4. audit: actor is the caller's actor; this creates an upstream row and must be traceable
```

**Never call this from `loadRoster()`.** Opening a screen must not create rows — see the empty-roster
state in §25.3. It is called by `occurrenceFor()`, which is called on **save**.

### `occurrenceFor()` — find-or-create, and why it is idempotent under two leaders

```
DB::transaction(function () {
  1. definition = ensureDefinition($group)
  2. startsAt = $localDate in the branch timezone at ifgf_group_profiles.default_time
                (default 19:30 local when default_time is null), converted to UTC
  3. SELECT ... WHERE event_definition_id = ? AND starts_at = ?  FOR UPDATE
     → return it if found
  4. INSERT with status 'open', branch_id from the definition
  5. catch QueryException 23000 on unique(event_definition_id, starts_at)
     → re-SELECT and return the winner.  NEVER retry blind, NEVER create a second row.
});
```

**`unique(event_definition_id, starts_at)` is what makes this safe** (§4–6), and step 5 is the same
discipline as `AttendanceRecorder` step 5c: a database constraint is the thing that actually
prevents the duplicate, and the service's job is to translate its violation into a normal result.
Two leaders saving the same meeting at the same second produce **one** occurrence.

**Step 2 is load-bearing and easy to get wrong.** `starts_at` is a UTC instant derived from a
branch-local wall time. Two occurrences are "the same meeting" iff their `starts_at` match exactly,
so `default_time` must be applied deterministically — the same group, the same local date and the
same `default_time` must always produce the same instant. Do not use `now()`'s time-of-day, and do
not round. If `default_time` changes later, past occurrences keep their original `starts_at`; they
are historical facts, not projections.

**Create on save, never in advance** (§8c). 22 groups × 52 weeks is 1,144 rows a year that mostly
never get used, and every one is a row the FR-12 reconciliation has to account for.

### `loadRoster()` — reads only

Roster membership comes from **`GroupMembershipService::rosterAsOf($group, $localDate)`**, not from
"currently active". Attendance recorded three weeks late must use the roster **as it was on the
meeting date** — a member who joined last Monday was not at a meeting the Tuesday before. `scopeAsOf`
(§20.7) is the sanctioned query and its `>` boundary (`effective_to` is the first day *not* covered)
is exactly the semantics this needs. Reading `group_links` instead is the mistake this pins against:
it has no history at all.

Rows already recorded for the occurrence (if one exists) are merged in by `user_id`, so re-opening a
saved screen shows what was saved rather than the pre-ticks.

`loadRoster()` **writes nothing** — no occurrence, no definition, no upstream `events` row, no
detail. A leader who opens the screen and closes it leaves no trace beyond a read audit entry.

### Pre-tick from the previous occurrence — a default, never a value

`suggestionsFrom()` returns the **user ids marked `present`** at the most recent occurrence of the
same definition with `starts_at <` this one **that has at least one detail row**. If there is none,
there are no suggestions and every row loads unset.

Four rules, and each one exists because its opposite is a plausible bug:

1. **Only `present` is ever suggested.** Absence is never pre-ticked. A suggested absence that a
   tired leader saves without reading is a pastoral assertion nobody made — the exact thing §20.0 A3
   and `finalize()`'s warning exist to prevent. A member the leader does not touch stays **unset**,
   and unset still means *nothing was observed*.
2. **A suggestion is not a recorded value.** `RosterRow::$suggestedPresent` is separate from
   `$status`, the save payload carries an explicit `MarkInput` per member acted on, and **a member
   with no `MarkInput` is not written** — even if they were suggested. Closing the screen without
   saving must never file last week's attendance as this week's.
3. **`capture_method` stays `manual`.** A human confirmed each row; that is what `manual` means.
   Do not add a `pretick` capture method — it would make a confirmed observation look like a
   machine inference, which is precisely the distinction Phase 2B's `face_assisted` will need.
4. **Visitors are not pre-ticked.** Last week's visitor is not on this week's roster, and a
   suggested visitor is a suggested guest list. They appear in `returningVisitors` as a
   convenience list requiring an explicit add.

This is the cheapest usability win on the screen — ~10 members meeting weekly is a highly regular
signal — and it needs no biometrics, no consent and no photo. **It is worth measuring before anyone
invests in detection**, which is the argument §8c makes and Part 3 keeps.

### `saveRoster()` — the write algorithm

```
DB::transaction(function () {
  1. occurrence = occurrenceFor($group, $localDate)          // find-or-create, above
  2. assert $occurrence->isWritable()   else throw OccurrenceNotWritable      (FR-04.8)
  3. assert $localDate is not in the future in the branch timezone
                                        else throw FutureOccurrenceNotRecordable
  4. roster = GroupMembershipService::rosterAsOf($group, $localDate)  → user id set
  5. for each MarkInput:
       a. user must be on the roster as of $localDate, or already recorded as a visitor
          on this occurrence, else → rejected[] (NOT an exception — one bad row must not
          discard the leader's other nineteen)
       b. status/mode agreement is AttendanceRecorder's check (FR-04.3); excused requires
          a note (A6).  Failures land in rejected[], with the member and the reason.
       c. existing = detail for (occurrence, user), locked
          - none                      → AttendanceRecorder::record(...)          → created++
          - exists, same status+mode  → AttendanceResult(wasAlreadyRecorded)     → alreadyRecorded++
          - exists, DIFFERENT, and existing->updated_at <= $loadedAt
                                      → amend(...)                               → amended++
          - exists, DIFFERENT, and existing->updated_at >  $loadedAt
                                      → conflicts[]  — NOT written               (see below)
  6. audit once per save with the counts, plus one audit per write inside the recorder
});
```

**Step 5c's last branch is the answer to "two leaders at once", and it is deliberately not
last-write-wins.** If leader A marked Budi present and leader B, on a screen loaded before that,
marks him absent, silently taking B's value overwrites an observation with a stale one — and
`AttendanceRecorder::record()` would not even do that: it returns `wasAlreadyRecorded` and writes
nothing, so B's contradiction would vanish with a success message. Neither behaviour is acceptable
on a pastoral record. **The conflicting row is left as A wrote it and returned in
`IcareSaveSummary::$conflicts`**, and the screen shows B what changed and who changed it, so the
correction is a decision rather than a race. Every non-conflicting row in B's save still commits.

### `amend()` — why `AttendanceRecorder` needs no `update()`

`AttendanceRecorder` exposes `record`, `remove`, `finalize`, `reopen` and no update, which is
correct: a mark is an observation, and changing one is a retraction plus a new observation, not an
edit. So a present→absent correction is `AttendanceRecorder::remove($detail, $actor, $reason)`
followed by `AttendanceRecorder::record(...)`, **inside one transaction**, with the reason carried
into both audit records.

`remove()` deletes the detail row *and* its `event_attendees` row together (§21.2), which is exactly
what a present→absent change requires: upstream must stop counting the member as present. The
reverse — absent→present — creates the upstream row on the way back in via step 5 of the recorder's
algorithm. Neither direction may be done by hand.

**A `$reason` is mandatory on `amend()` and there is no default.** Correcting attendance after the
fact is what a pastoral audit trail is for. This is the same lesson as upstream's `unlock()`, which
"records no reason and has nowhere to put one" — do not reproduce it here.

### `addVisitor()` — the registered visitor from another iCare

```
1. visitor->church_id must equal actor->church_id, else VisitorOutsideChurch
2. visitor must NOT have an active membership in $group as of the occurrence's local date,
   else MemberIsOnRoster   — they are a member, not a guest; mark them on the roster
3. AttendanceRecorder::record(new RecordAttendance(
       occurrence:  $occurrence,
       userId:      $visitor->id,
       status:      'present',
       participationMode: $mode,
       captureMethod: 'manual',
       actor:       $actor,
       isVisitor:   true,                          // A5
       visitorFromGroupId: $fromGroup?->id,        // A5 — their own group, if known
   ))
```

**No group membership is created. Ever.** That is the mistake §8c's flag exists to prevent: with the
one-active-primary partial unique index (§8, invariant 16), writing a membership for a visitor could
**silently end their real membership in their own iCare**. One visit would read as a transfer, and
FR-05.6 says the opposite — *"flagged as visitors without changing their primary group"*.

Step 1 is not boilerplate. Resolving a person by name or id across churches and acting on the result
is precisely CARD-002, which is live in this codebase today; the scoped version exists two files away
on `POST /api/v1/attendance/scan`. **Write the scope clause first, not last.**

### `quickAddUnclaimed()` — the visitor who is in no iCare at all

FR-05.7. Creates an unclaimed member record and marks them present, in one transaction:

```
1. profile = MemberRegistryService::createUnclaimed($command, $actor)      // see below
2. AttendanceRecorder::record(... isVisitor: true, visitorFromGroupId: null ...)
3. DuplicateScorer runs against the new record; candidates are written as PENDING conflicts
   via ConflictRecorder and are NOT shown as a blocking prompt
```

`MemberRegistryService::createUnclaimed(QuickAddVisitor $command, User $actor): MemberProfile` does
not exist in §21.6 and must be added there: status `visitor`, `church_id` from `$actor->church_id`,
home branch **nullable** (§13.1.19 — required at activation, not at creation; an unclaimed record is
legitimately branch-neutral), phone stored verbatim in `value` with `value_normalised` derived by
`PhoneNormalizer` (which returns null rather than guessing), **no credentials and no ability to
authenticate** (invariant 2), fully audited with the occurrence as context.

Two decisions inside step 3 that look small and are not:

- **Duplicate review must not block the leader.** They are on a phone, mid-meeting, with a guest
  standing in front of them. A modal asking "is this the same Andi as these three?" gets dismissed
  at random. Recording the candidates as pending conflicts routes them to the same review queue the
  import already has (§21.9) and gets a correct answer later instead of a coerced one now.
- **No QR token is issued at quick-add.** §21.6's `register()` issues one so a self-registering
  member never exists without a credential; an unclaimed record has no card, no login and nobody to
  hand a card to. `MemberQrService::issue()` is idempotent, so issuing at claim/activation is safe
  and issuing here is only noise in the token space. Confirm as decision D10.

### `attachPhoto()` — §8c requirement 6, and nothing more

Stores one or more files against the occurrence via `ifgf_attendance_media` and
`MemberMediaService`'s ingest discipline: **validate by content, never by filename** (an
`UploadedFile::fake()` assertion cannot settle a file-type question — that is what produced SEC-003's
false RCE rating), an explicit `mimes:jpeg,png,webp,heic` allow-list and **never Laravel's `image`
rule**, which admits SVG. EXIF is stripped — a group photo carries GPS.

**Documentation only.** No detection, no recognition, no template, no consent gate, no
`capture_method` value implying any of them. The blocker is stated in §21.5 and applies here
verbatim: **there is no private disk in this application today**, all four disks are public,
`uploads` is rooted at `public_path()` itself, and no `temporaryUrl()` or signed route exists
anywhere in `app/`. Until Release 1 creates a private disk plus a signed-access route, an iCare
group photo would land at a world-readable path in the webroot — the same finding as CARD-003, with
a photograph of ten identifiable members instead of one PDF. **`attachPhoto()` must not be filled
against a public disk.** It is the last method built, after the private disk exists.

### `finalize()` — delegation, plus the one thing iCare must get right

Calls `AttendanceRecorder::finalize($occurrence, $actor)`, which sets `status = 'finalized'`,
`finalized_at`, `finalized_by`, and — because an iCare occurrence is **closed-roster** (audience
`group`) — writes an explicit `absent` detail row for every rostered member with no row (FR-04.6),
returning totals plus the FR-05.10 exception list.

🔴 **The roster `finalize()` snapshots must be `rosterAsOf($group, $occurrence->localDate())`, not
the roster today.** Finalizing a three-week-old meeting against today's membership writes `absent`
rows for members who had not joined yet — manufactured pastoral data about a meeting they could not
have attended, fed straight into FR-10's inactive-risk report. This is the single highest-value
assertion in §25.5 that a reviewer would not think to ask for.

Everything §21.2 says about `finalize()` still binds: it is the **only** thing in Release 1 allowed
to turn a missing row into an absence, it never runs on an open-audience occurrence, it never runs
on a schedule, and it is never back-applied to historical imports. A missing row is still not
absence, before this method and after it.

`reopen()` stays `AttendanceRecorder::reopen()` with a mandatory non-empty reason (FR-04.8) and is
**admin-only at the policy layer** (FR-04.12) — a leader may not reopen their own finalized meeting.

---

## 25.2 The date resolution rule, in full

§8c requirement 2 in one line: *"default date = the most recent occurrence of the group's
`default_weekday`, today or earlier."* That sentence contains a **calendar day derived from an
instant**, which §20.0 A0 governs: instants are stored UTC (UP-009, settled, not reopened here);
a calendar day is derived **in the branch timezone**, always, without exception.

### The algorithm

```php
public function defaultLocalDate(Group $group, ?CarbonInterface $at = null): ?CarbonImmutable
{
    $profile = $group->ifgfProfile;                       // ifgf_group_profiles, §8b
    if ($profile === null || $profile->default_weekday === null) {
        return null;                                       // NO GUESS — §25.3 state B
    }

    $tz    = $this->branchTimezoneFor($group);             // resolution chain below
    $today = CarbonImmutable::instance($at ?? CarbonImmutable::now())
                 ->setTimezone($tz)                        // ← the whole rule is this line
                 ->startOfDay();

    $delta = ($today->dayOfWeekIso - $profile->default_weekday + 7) % 7;   // 0 when today IS the day
    return $today->subDays($delta);                        // today or earlier, never later
}
```

`default_weekday` is **ISO-8601, 1 = Monday … 7 = Sunday** (§8b), and `dayOfWeekIso` is the matching
accessor. Carbon's `dayOfWeek` is 0 = Sunday and is the wrong one; mixing them is a silent one-day
error on Sundays and Mondays only, which is exactly the kind of bug that survives a hand test on a
Wednesday.

**The timezone resolution chain, in order, with no silent fallback:**

1. `$group->ifgfProfile->branch->timezone` — `ifgf_branches.timezone`, explicit IANA (§1).
2. `$group->ifgfProfile->branch_id` is null → the group's `ifgf_event_definitions.timezone`
   (default `Asia/Taipei`, §4–6).
3. Neither resolves → **throw `BranchTimezoneUnresolved`**.

🔴 **`config('app.timezone')` is never step 3, and never any step.** It is `UTC` in this
application by decision (UP-009), so falling back to it is not a safe default — it is the wrong
answer written down as a default, and it fails silently. `date()`, `strtotime()`, `Carbon::today()`
and `now()` without a timezone argument are all the same mistake wearing different clothes and none
of them may appear in this method or in anything that calls it.

### Why computing this in UTC is wrong by SEVEN days, not one

The existing caveat in §8c says an early meeting "puts a Tuesday-evening iCare on Monday's date."
That understates it. Weekday arithmetic **snaps**, so a one-day error in *today* becomes a
seven-day error in *the answer*.

Taipei is UTC+8, so between **00:00 and 08:00 local** the UTC calendar day is the **previous** day.
Take a group whose `default_weekday` is Tuesday (2), and a leader opening the screen at **07:00
Taipei on Tuesday 2026-08-18**:

| computed in | "today" | most recent Tuesday ≤ today | result |
|---|---|---|---|
| `Asia/Taipei` (correct) | Tue 2026-08-18 | **2026-08-18** | this week's meeting |
| UTC (wrong) | Mon 2026-08-17 | **2026-08-11** | **last week's meeting, seven days out** |

The consequence is not a cosmetic mislabel. `occurrenceFor()` would find-or-create the occurrence
for **2026-08-11**, which either (a) already exists and is **finalized**, so the save fails with
`OccurrenceNotWritable` and the leader is told, mysteriously, that a meeting they just held is
closed; or (b) already exists and is still open, and this week's attendance is merged into last
week's roster — **two meetings collapsed into one, silently, with no error anywhere.** Case (b) is
the dangerous one and it is invisible until someone reconciles counts months later.

Note which direction is *safe*, because it explains why casual testing misses this: a leader
recording **after** the meeting — Tuesday 21:00 local, or Wednesday 00:30 local — gets the same
answer under both computations. The bug only fires in the 00:00–08:00 local window **on the meeting
weekday itself**. Nobody hits it by accident on a Wednesday afternoon.

### Boundary case 1 — the evening meeting that finishes after local midnight

An iCare meets Tuesday 21:00 and finishes 00:30 Wednesday, local. Stored:
`starts_at = 2026-08-18T13:00Z`, `ends_at = 2026-08-18T16:30Z`.

**The occurrence's calendar day is the branch-local date of `starts_at`. Never `ends_at`, never
`now()` at save time.** `EventOccurrence::localDate()` (§20.5) is the only implementation of that
sentence and every caller uses it.

Two rules make the crossing safe:

1. **The date is resolved once, when the screen loads, and carried explicitly.** `IcareRoster`
   returns `$localDate`; `SaveIcareRoster` takes it as a required field; `saveRoster()` **never
   re-derives it from `now()`**. A leader who opens the screen at 23:55 and taps save at 00:05
   must write to the same occurrence — if the save path re-derived the default, the second half of
   the roster would land on a different day, and with `default_weekday = Tuesday` it would land
   **seven days earlier** by the same snapping argument.
2. **The date picker's value is authoritative over the default.** §8c requirement 2 allows any date;
   the default is a starting position, not a constraint. A leader recording Wednesday 00:30 for a
   meeting whose local date is Tuesday sees `Tuesday 2026-08-18` prefilled — correct, because
   `defaultLocalDate()` at 00:30 Wednesday returns *yesterday's* Tuesday — and may change it.

For completeness, the one case that is genuinely ambiguous: a meeting that **starts** after local
midnight (00:30 Wednesday, no earlier session). Its local date is Wednesday, and its
`default_weekday` is Tuesday, so the default and the truth disagree by a day. **The schema is right
and the leader is right** — they change the date, `occurrenceFor()` creates the Wednesday occurrence,
and it is a distinct meeting. Do not add a "meetings after midnight belong to the previous day"
rule; it is unfalsifiable from the data and would silently misfile a genuine Wednesday group.

### Boundary case 2 — the leader in a different timezone to the branch

A Taipei iCare's leader is travelling in Los Angeles (UTC−7) and opens the screen at **Monday 18:00
LA time**, which is **Tuesday 09:00 in Taipei** — the meeting is happening now, on a video call.

| computed in | "today" | most recent Tuesday | result |
|---|---|---|---|
| `Asia/Taipei` — the branch (correct) | Tue | **today** | this week |
| `America/Los_Angeles` — the device | Mon | **six days ago** | **last week, seven days out** |
| `UTC` — the server | Tue 01:00 | today | correct **here, by luck** |

Three rules follow:

1. **Resolve against the branch, never the actor.** The device's timezone, the browser's
   `Intl.DateTimeFormat().resolvedOptions().timeZone`, the `Accept-Language` header and any
   client-supplied offset are **inputs to nothing**. The meeting happened where the group meets.
2. **The resolved date is displayed with its timezone label** — "Tuesday 18 Aug 2026 (Asia/Taipei)"
   — so a remote leader can see that the app is reasoning in branch time and correct it if the
   group genuinely met on another day. FR-04.1.3 makes the same argument for the usher's occurrence
   selector: *a mis-selected occurrence must be visible before writing, not discovered during
   reconciliation.*
3. **Note that UTC gets the right answer in this example by coincidence.** That is why the
   date-boundary test must use a branch whose timezone is neither the server's nor the fixture's
   default — a `UTC`-vs-`Asia/Taipei` test can pass with the conversion missing entirely. §25.5
   pins this with an `America/Los_Angeles` branch specifically.

### DST

Taiwan has no transition and neither does Indonesian WIB, so nothing in Release 1 exercises this.
It is still specified because the timezone is per-branch and per-definition (FR-03.11): expansion
and weekday arithmetic are **timezone-correct, never offset-correct**. Do the arithmetic with
`CarbonImmutable` in the named zone; never add or subtract a fixed number of hours, and never store
an offset where an IANA name belongs.

---

## 25.3 Controller and screen states

```php
namespace Ifgf\ChurchOperations\Http\Controllers;

final class IcareAttendanceController extends \App\Http\Controllers\Controller
{
    public function index(Request $r): View;                       // groups this actor leads
    public function show(Group $group, Request $r): View;          // the roster screen
    public function store(SaveIcareRosterRequest $r, Group $group): RedirectResponse;
    public function visitor(AddIcareVisitorRequest $r, Group $group): RedirectResponse;
    public function quickAdd(QuickAddVisitorRequest $r, Group $group): RedirectResponse;
    public function photo(UploadIcarePhotoRequest $r, Group $group): RedirectResponse;
    public function finalize(Request $r, Group $group, EventOccurrence $occurrence): RedirectResponse;
}
```

Per §21.0 rule 2 the controller is the **only** layer that turns an exception into a response. The
mapping is fixed so the tests can assert it:

| Exception | HTTP | Screen |
|---|---|---|
| `OccurrenceNotWritable` | **409** | state D, read-only, with the reopen path shown only to admins |
| `FutureOccurrenceNotRecordable` | **422** | validation error on the date field |
| `BranchTimezoneUnresolved` | **409** | state F — a configuration error, not the leader's fault |
| `InvalidAttendanceState` | **422** | per-row error, other rows unaffected |
| `MemberIsOnRoster` | **422** | "already on this roster — mark them there" |
| `VisitorOutsideChurch` | **404** | **not 403.** Never confirm that a member of another church exists |
| `SameDayOccurrenceNotSupported` | **409** | §20.0 A2 / WP 0C item 5, with that reference in the message |
| authorization failure | **403** | §25.4 |

`VisitorOutsideChurch → 404` follows §21.1's rule for `resolveToken()`: one shape for "no such
member" and "not your church", so the endpoint is not an oracle. It is the same argument that makes
CARD-002 a defect.

### The six states

**A · Empty roster.** `rosterAsOf($group, $localDate)` returns nothing — a new iCare, or a date
before any membership began. **Nothing is created**: no occurrence, no definition, no upstream
`events` row. Show the empty state and a link to add members. Saving with no marks is a no-op that
writes nothing and says so. **Do not offer "finalize" here** — finalizing an empty closed roster is
a snapshot asserting that nobody was absent, from a meeting nobody has evidence happened.

**B · No `default_weekday`.** `ifgf_group_profiles` is missing for the group, or `default_weekday`
is null. `defaultLocalDate()` returns **null** and the date picker opens **empty**. 🔴 **Do not
prefill today.** Today is not a meeting date; it is the date someone opened an app, and prefilling
it converts a missing configuration into a plausible-looking wrong answer that saves in one tap.
Show a one-line prompt to set the group's meeting weekday, linking to the group profile screen. The
leader may still pick a date manually and record normally — a missing weekday must not block
attendance.

**C · Normal.** Date resolved and labelled with its timezone (§25.2), roster loaded as of that date,
`present` pre-ticks applied as defaults, mode defaulting to **onsite** with a per-row online toggle
(§8c requirement 5), select-all and search per FR-05.5. `IcareRoster::$occurrence` is **null** until
the first save — that is correct and must not be treated as an error.

**D · Already finalized.** `$occurrence->status === 'finalized'` → `isWritable()` false. The screen
is **read-only**, shows totals, `finalized_at` and `finalized_by`, and offers **no** save control.
An attempted write returns **409** rather than silently discarding. The reopen control is rendered
only for an actor whose policy allows it (admin, FR-04.12) and requires a non-empty reason
(FR-04.8); a leader sees who to ask, not a disabled button with no explanation. `cancelled` behaves
the same way with different wording.

**E · Concurrent save.** Handled in `saveRoster()` step 5c (§25.1): non-conflicting rows commit,
conflicting rows do not, and the response re-renders with `IcareSaveSummary::$conflicts` — for each,
the member, what the other leader recorded, who they were and when. The leader chooses; the app does
not choose for them. **The one thing that must never happen is a success message covering a
discarded mark.**

**F · Configuration error.** `BranchTimezoneUnresolved`, or no `icare` row in `ifgf_event_types`.
409 with an explicit message naming what is missing. These are administrator problems and the
message says so — a leader must never be shown a stack trace or a generic 500 for a row somebody
forgot to seed.

### Route registration — do not reuse either legacy gate

Routes are registered by `ChurchOperationsServiceProvider`, and the middleware stack is
**`['web', 'auth']` plus policy authorization**. Not `permission:`, and not `churchadmin`:

- **`permission:` is `App\Http\Middleware\AdminOrPermission`** (`Kernel.php:74`), which admits
  **any** `usergroup_id == 3` account to every `permission:*` route with no role and no audit record
  — SEC-001. Putting a leader route behind it inherits the bypass on day one, on a screen that
  exposes named members and pastoral notes.
- **`churchadmin` is `MustBeChurchAdmin`**, which admits usergroups 3 and 4, redirects usergroup 1
  to `/portal` and aborts 403 for everything else — including **usergroup 5, which is what an iCare
  leader is today**. It would lock out exactly the people the screen is for.

Neither gate can express "leads this group", which is what FR-05.4 and FR-11.11 actually require.
The new surface denies with **403**, while the legacy Laratrust surface denies with **401** — a
deliberate difference, recorded here so a future reader does not "align" them and reintroduce
SEC-001. And check which route **file** a route lands in: the `/admin/*` group at
`routes/web.php:182` carries no `auth` at all.

🔴 **But policies alone do not escape SEC-001 either — see §25.4, corrected 2026-08-21.** The stack
is `['web', 'auth', 'icare.leader']`, where `icare.leader` is a **package middleware**, not the
`can:` middleware and not a policy call.

**URI prefix: `/icare/...`, and it must not collide.** `mapWebRoutes()` runs before
`mapAdminRoutes()` (`RouteServiceProvider` L39–43: api → web → admin), and suite 6 measured the
consequence — the granular group-permission surface at `routes/web.php:262` is **shadowed dead** by
`routes/admin.php`, so `read-groups` alone creates, edits and deletes groups while
`create-groups`/`update-groups`/`delete-groups` sit on routes that never resolve. **A route file
documents a model the application does not have**, and nothing errors.

Package routes are registered by `ChurchOperationsServiceProvider`, whose boot order relative to
those three is a property of provider discovery — the same silent seam as UP-010. **Do not rely on
it.** Measured 2026-08-21: no route anywhere in `routes/` contains the string `icare`, so the
prefix is clear today. Pin it: a test asserting `/icare/*` resolves to the package controller is
what catches the day an upstream merge adds a colliding route, because a shadowed route produces no
error — only the wrong controller.

---

## 25.4 Authorization — assert the denial

FR-05.4: *"Leaders see only groups they lead unless granted broader permission."* FR-11.11: *"A
leader may record attendance only when an active event, group, cohort, ministry, or occurrence
assignment grants that scope."* FR-11.4: leader adds **assigned-scope attendance** and nothing else.

### Where "leads this group" comes from

**`ifgf_group_memberships` where `group_id = G`, `role = 'leader'`, `effective_to IS NULL`** — via
`GroupMembershipService`, using `scopeAsOf` when the question is historical.

**Not `event_managers`.** That upstream table exists and `EventAttendanceController` manages it, but
`openSession`, `markAttendee`, `lock` and `unlock` **never consult it** — SEC-002. It is
organisational bookkeeping, not authorization, and it is per-**event**, not per-**group**. FR-11.11's
control **does not exist yet and must be built**; rerouting writes through `AttendanceRecorder` does
not fix SEC-002 and must never be reported as fixing it.

### 🔴 CORRECTED 2026-08-21 — SEC-001 is TWO controls, and policies do not escape it

**The first version of this section was wrong** and the error is worth keeping visible, because the
plan it proposed looks correct and fails silently. It said: avoid the `permission:` middleware,
authorize through policies instead, and SEC-001's `usergroup_id == 3` bypass cannot reach the iCare
surface. Suite 6 found the second half of SEC-001, and it invalidates that plan.

`app/Providers/AuthServiceProvider.php::boot()`, read at `3793b54`:

```php
// Church admins (usergroup_id == 3) bypass all permission checks.
Gate::before(function ($user, $ability) {
    if ($user->usergroup_id == 3) {
        return true;
    }
});
```

**`Gate::before` short-circuits every authorization check that goes through the Gate** — `can()`,
`Gate::allows()`, `$this->authorize()`, the `can:` middleware, and **every policy method, including
one registered by a package that did not exist when this callback was written.** A non-null return
means the policy body never runs.

So `EventOccurrencePolicy::record()` would return `true` for any `usergroup_id == 3` account with no
leader membership, in any church, and the method would never be entered. **The proposed control was
not weak; it was absent**, and every grant test would have passed.

Two lessons, both already this project's own:

- **SEC-001 was recorded as "ONE LINE, not 33 call sites."** That was an improvement on the earlier
  count and still incomplete: it is **two controls in two files** — the `Kernel.php:74` alias *and*
  this `Gate::before`. A finding narrowed once can be narrowed too far. It also means
  `Gate::allows('group', …)`, the only church scope on `show`/`edit`/`destroy`, is bypassed for
  usergroup 3 — that account reads and edits **other churches'** groups.
- **Do not route around a bypass through a mechanism you have not read.** The plan was written from
  the recorded one-line summary rather than from `AuthServiceProvider`.

### The enforcement path — middleware and a plain authorizer, never the Gate

```php
// package middleware, registered by ChurchOperationsServiceProvider as 'icare.leader'
final class EnsureIcareLeader          // aborts 403; the ONLY route-level gate
final class IcareAuthorizer            // plain object. NOT a policy, NOT resolved through Gate
{
    public function mayViewRoster(User $actor, Group $group): bool;
    public function mayRecord(User $actor, Group $group): bool;
    public function mayFinalize(User $actor, Group $group): bool;
    public function mayReopen(User $actor, EventOccurrence $o): bool;   // admin only, FR-04.12
    public function assertMayRecord(User $actor, Group $group): void;   // throws IcareAccessDenied
}
```

`IcareAuthorizer` is called **directly** — `app(IcareAuthorizer::class)->assertMayRecord(...)` —
from the middleware and again from the controller. Nothing in the iCare path calls `authorize()`,
`can()`, `Gate::allows()` or the `can:` middleware, because all four pass through `Gate::before`.

**Why the check runs twice, and why that is not belt-and-braces theatre.** The middleware guards the
route; the controller guards the *action*, because `store()` takes a `Group` from the URL and the
occurrence from a form field, and a route-level check on the former does not constrain the latter.
FR-11.6 is explicit that hidden navigation never replaces authorization.

**Three rejected alternatives, recorded so they are not re-proposed:**

1. **A package `Gate::before` that returns `false` first.** It works only if the package provider
   boots *before* `AuthServiceProvider`, which is a property of provider discovery order, not of
   this code. A merge that changes discovery silently inverts the control — the UP-010 failure mode
   on an authorization boundary. Rejected.
2. **Removing the `Gate::before`.** Correct, and it belongs to FR-11, which must first map legacy
   groups onto the three approved roles. Removing it today locks out every church admin. Rejected
   for now, not forever.
3. **Reusing `permission:`.** `Kernel.php:74`, the other half of SEC-001. Rejected.

**The policy classes are still generated** (`GENERATION_MANIFEST.md` Section 4) and still carry the
methods below. They are the **FR-11 target shape**, not today's enforcement path, and a test pins
that distinction so nobody "simplifies" the middleware away while `Gate::before` is still live.

### `EventOccurrencePolicy` and `AttendanceDetailPolicy` — the iCare methods

```php
// EventOccurrencePolicy
public function viewRoster(User $actor, EventOccurrence $o): bool;
public function record(User $actor, EventOccurrence $o): bool;
public function finalize(User $actor, EventOccurrence $o): bool;
public function reopen(User $actor, EventOccurrence $o): bool;    // admin only — FR-04.12

// a group-scoped pre-check, because the roster screen is reached before an occurrence exists
// GroupMembershipPolicy
public function recordAttendanceFor(User $actor, Group $group): bool;
```

The rule, in order, and **every clause is load-bearing**:

1. `admin` → true for everything (FR-11.5, FR-04.12).
2. `leader` → true **iff** the actor holds an active `leader` membership in the occurrence's group
   **and** `$o->definition->event->church_id === $actor->church_id`.
3. `member` → **false**, including for their own attendance row. FR-11.3 is self-only for *profile
   and QR*; it does not extend to marking themselves present.
4. Anything else → false. There is no fourth branch and no `usergroup_id` clause anywhere in these
   policies. A device is a machine principal, not a human role (FR-11.20), and it has no business
   on this surface.
5. `reopen()` is **admin-only** even for the group's own leader.

**The church check in clause 2 is not redundant with the group check.** A group id is a small
integer arriving from a URL; `Group $group` route-model binding resolves it globally. Without the
explicit `church_id` comparison, a leader in church A who is somehow a leader-role row against a
church-B group — an import defect, a merge, a test fixture — reaches church B's members. This is
CARD-002's exact shape, and the scoped version already exists two files away on
`POST /api/v1/attendance/scan`.

### 🔴 Assert the denial, not the grant

A policy suite that only proves "the right person gets in" proves nothing about the control. Every
denial below is named as a test in §25.5, and each targets a way this specific codebase has already
been observed to fail:

| Denial | Why this one, specifically |
|---|---|
| leader of group A → group B's roster: **403** | the base case FR-05.4 states |
| leader of group A → group B's **save**: **403** | reading and writing are separate gates; a screen that hides a control is not authorization (FR-11.6) |
| leader → a group in **another church**: **403/404** | CARD-002 is live in this codebase today |
| **`usergroup_id == 3`** with no leader membership: **403** | SEC-001's bypass must not reach the package surface. **Catches both halves**: a route placed behind `permission:` (`Kernel.php:74`) *and* an authorizer resolved through the Gate (`AuthServiceProvider`'s `Gate::before`). Write it first — it is the only test that would notice either. |
| **usergroup 4 church admin** with no leader membership and no `admin` role: **403** | legacy admin ≠ FR-11 `admin`; FR-11.9 forbids a legacy path silently granting broader access |
| ordinary `member` → any roster: **403** | FR-11.3 is self-only for profile and QR |
| guest → any iCare route: **redirect to login** | `['web','auth']`, not the legacy 401 |
| leader → **`reopen`** on their own finalized occurrence: **403** | FR-04.12 admin-only |
| **expired leadership** (`effective_to` in the past): **403** | the `whereNull('effective_to')` clause is the whole control; a policy reading "was ever a leader" passes every other test |
| leader → `addVisitor` for a **member of another church**: **404** | one shape for "no such member" and "not your church" (§21.1) |

The `usergroup_id == 3` row is the one to write first. It is true everywhere else in the
application right now — through **two** independent mechanisms, either of which is one careless
refactor away from being true here — and no other test in the suite would notice.

---

## 25.5 The test list

Generated per the manifest's Section 6 convention — **root `tests/`**, so the existing suite and CI
pick them up unchanged, `-n` throughout:

```
php artisan make:test Ifgf/IcareDateResolutionTest --unit -n
php artisan make:test Ifgf/IcareRosterTest -n
php artisan make:test Ifgf/IcareAttendanceWriteTest -n
php artisan make:test Ifgf/IcareVisitorTest -n
php artisan make:test Ifgf/IcareAuthorizationTest -n
php artisan make:test Ifgf/IcareFinalizationTest -n
php artisan make:test Ifgf/IcareConcurrencyTest -n
```

**Pre-flight, inherited and non-negotiable:** start MySQL by hand (no registered Windows service);
run `php artisan cache:clear --env=testing` before **every** run — `.env.testing` sets
`CACHE_DRIVER=file` and Laratrust caches resolved permissions to disk for 84,000 seconds keyed by
user id, and `DatabaseTransactions` rolls back rows but not the filesystem, so any red authorization
test is that until proven otherwise. Any test rendering an admin view seeds `settings.*` first
(`MemberProfileCharacterizationTest::seedRuntimeSettings()` is the pattern). Denial on **package**
routes is **403**; denial on the **legacy Laratrust** surface is **401** (§25.3).

### `IcareDateResolutionTest` — unit, no database. 🔴 Write this first.

**A date bug here misfiles a whole meeting silently**, by seven days, with no error anywhere. This
file is written before the feature, and it is the reason §25.2 exists.

Fixture rule for the whole file: **the branch timezone must be neither UTC nor the machine
default.** A `UTC`-vs-`Asia/Taipei` test can pass with the conversion missing entirely (§25.2
boundary case 2 shows UTC coincidentally right). Use an `Asia/Taipei` branch **and** an
`America/Los_Angeles` branch, and travel to explicit instants with `CarbonImmutable::setTestNow()`
on a UTC clock.

| Test | Asserts |
|---|---|
| `test_default_date_is_today_when_today_is_the_meeting_weekday` | Tue 19:00 Taipei, `default_weekday = 2` → **that Tuesday**. The `% 7 == 0` branch. |
| `test_default_date_is_the_most_recent_past_occurrence_otherwise` | Fri Taipei, weekday Tue → the Tuesday three days earlier, never the next one |
| `test_default_date_never_returns_a_future_date` | every weekday × every `default_weekday` (49 cases) → result ≤ today, in branch time |
| 🔴 `test_early_morning_local_resolves_seven_days_later_than_a_utc_computation` | **the headline.** 07:00 Taipei Tue 2026-08-18 → `2026-08-18`; asserts explicitly that it is **not** `2026-08-11`. Fails by exactly seven days if `setTimezone()` is dropped. |
| 🔴 `test_resolution_uses_the_branch_timezone_not_the_server_timezone` | server/`config('app.timezone')` = UTC, branch = Taipei, instant chosen so the two disagree → branch answer |
| 🔴 `test_resolution_uses_the_branch_timezone_not_the_actor_timezone` | boundary case 2: LA leader, Mon 18:00 LA = Tue 09:00 Taipei → **this** Tuesday. Any device-timezone input is ignored. |
| `test_meeting_crossing_local_midnight_keeps_the_start_date` | occurrence 21:00 Tue → 00:30 Wed local; `localDate()` = Tuesday, from `starts_at` and never `ends_at` |
| `test_save_at_0005_local_writes_the_same_occurrence_as_a_load_at_2355` | the carried-date rule: `SaveIcareRoster::$localDate` is honoured and never re-derived from `now()` |
| `test_iso_weekday_numbering_is_used` | `default_weekday = 7` resolves **Sunday**, `1` resolves **Monday** — catches Carbon's `dayOfWeek` (0 = Sunday) being used in place of `dayOfWeekIso` |
| `test_returns_null_when_default_weekday_is_not_set` | null, **not** today (state B) |
| `test_throws_when_the_branch_timezone_cannot_be_resolved` | `BranchTimezoneUnresolved` — never a silent `config('app.timezone')` fallback |
| `test_dst_branch_uses_timezone_correct_arithmetic` | an `America/Los_Angeles` branch across a DST boundary; the local weekday is right on both sides |

### `IcareRosterTest`

| Test | Asserts |
|---|---|
| `test_roster_is_the_membership_as_of_the_meeting_date_not_today` | a member joining after the date is **absent from the roster**, and one who left before it is too |
| `test_roster_excludes_memberships_ended_on_the_meeting_date` | `scopeAsOf`'s `>` boundary — `effective_to` is the first day *not* covered |
| `test_loading_the_screen_creates_no_rows` | no occurrence, no definition, no upstream `events` row, no detail. Counts before == after. |
| `test_empty_roster_renders_state_a_and_offers_no_finalize` | state A |
| `test_missing_default_weekday_renders_an_empty_date_picker` | state B — asserts the field is **empty**, explicitly not today |
| `test_pretick_marks_last_occurrence_present_members_as_suggested_only` | `suggestedPresent` true, `status` **null** — a suggestion is not a value |
| 🔴 `test_pretick_never_suggests_absence` | nobody is suggested `absent`; unmarked members stay unset |
| 🔴 `test_closing_without_saving_writes_nothing_despite_pretick` | load, discard, assert zero detail rows. The bug this rules out files last week's attendance as this week's. |
| `test_pretick_source_is_the_most_recent_prior_occurrence_with_details` | skips an intervening occurrence that has none |
| `test_no_previous_occurrence_yields_no_suggestions` | a first-ever meeting loads entirely unset |
| `test_visitors_are_not_pretick_suggested` | they appear in `returningVisitors` only |
| `test_saved_marks_take_precedence_over_suggestions_on_reload` | reopening a saved screen shows what was saved |

### `IcareAttendanceWriteTest`

| Test | Asserts |
|---|---|
| 🔴 `test_every_write_goes_through_the_attendance_recorder` | a spy/fake `AttendanceRecorder` bound in the container receives **every** write; asserts the service performed **zero** direct inserts on `event_attendees` and `ifgf_attendance_details` |
| 🔴 `test_direct_detail_creation_is_blocked_by_empty_fillable` | `AttendanceDetail::create([...])` throws `MassAssignmentException` — the §20.6 guard is real |
| `test_present_writes_an_upstream_event_attendees_row` | §20.0 A3 row 1 |
| `test_absent_writes_no_upstream_row` | A3 row 2 — upstream counts unchanged |
| `test_excused_writes_no_upstream_row` | A3 row 3 |
| 🔴 `test_an_unmarked_member_has_no_row_at_all` | A3 row 4: not recorded ≠ absent. **The assertion the whole schema exists to protect.** |
| `test_first_save_creates_the_occurrence_and_the_definition_and_the_backing_event` | A4's chain, once |
| `test_second_save_reuses_the_same_occurrence` | find-or-create is idempotent; counts unchanged |
| `test_occurrence_starts_at_is_derived_from_default_time_in_branch_local_wall_time` | the same group/date/`default_time` always yields the same UTC instant |
| 🔴 `test_upstream_attendance_date_is_the_branch_local_day` | A7 — an early-morning occurrence stores the **local** day, not the UTC one |
| `test_excused_without_a_note_is_rejected` | A6, `InvalidAttendanceState`, per-row |
| `test_absent_with_a_participation_mode_is_rejected` | FR-04.3 |
| `test_one_invalid_row_does_not_discard_the_rest_of_the_save` | `rejected[]`, others commit |
| `test_duplicate_mark_returns_already_recorded_rather_than_erroring` | FR-04.7 |
| `test_amend_removes_and_rerecords_in_one_transaction` | present→absent deletes the `event_attendees` row and writes a new detail |
| `test_amend_requires_a_reason_and_audits_both_halves` | no default reason; two audit records |
| `test_future_date_is_rejected` | `FutureOccurrenceNotRecordable`, evaluated in **branch** time |
| `test_writes_to_a_finalized_occurrence_are_rejected` | `OccurrenceNotWritable` → 409, state D |
| `test_every_write_records_actor_timestamp_source_and_capture_method` | FR-04.10 |
| `test_capture_method_is_manual_for_a_confirmed_pretick` | no `pretick` value invented |

### `IcareVisitorTest`

| Test | Asserts |
|---|---|
| 🔴 `test_adding_a_visitor_creates_no_group_membership` | §8c's central rule — `ifgf_group_memberships` count unchanged |
| 🔴 `test_adding_a_visitor_does_not_end_their_primary_membership` | their own active primary row is untouched, `effective_to` still null (FR-05.6) |
| `test_visitor_row_carries_the_flag_and_the_source_group` | `is_visitor`, `visitor_from_group_id` (A5) |
| `test_visitor_from_group_id_without_the_flag_is_rejected` | A5's paired rule |
| `test_visitor_who_is_on_the_roster_is_rejected` | `MemberIsOnRoster` → 422 |
| 🔴 `test_visitor_from_another_church_is_not_found` | **404, not 403** — no oracle (§21.1) |
| `test_quick_add_creates_an_unclaimed_record_that_cannot_authenticate` | invariant 2 — a login attempt fails |
| `test_quick_add_allows_a_null_home_branch` | §13.1.19 — required at activation, not creation |
| `test_quick_add_normalises_the_phone_and_keeps_the_verbatim_value` | `value` verbatim, `value_normalised` derived; unparseable → null, never a guess |
| `test_quick_add_records_duplicate_candidates_as_pending_conflicts` | via `ConflictRecorder`; the save still succeeds |
| `test_quick_add_does_not_block_on_duplicates` | no exception, no prompt, member marked present |
| `test_quick_add_issues_no_qr_token` | D10; `MemberQrService::issue()` at claim time is idempotent |
| `test_quick_added_visitor_is_scoped_to_the_actors_church` | `church_id` from the actor, never from input |

### `IcareAuthorizationTest` — the denials from §25.4

`test_leader_can_view_and_record_for_their_own_group` is the **only** grant test in the file, plus
`test_admin_can_record_for_any_group_in_their_church`. Every other test is a denial:

`test_leader_cannot_view_another_groups_roster` · `test_leader_cannot_save_to_another_groups_roster`
· `test_leader_cannot_reach_a_group_in_another_church` ·
🔴 `test_usergroup_three_bypass_does_not_reach_the_icare_surface` ·
🔴 `test_legacy_usergroup_four_church_admin_without_a_leader_membership_is_denied` ·
`test_ordinary_member_cannot_view_any_roster` · `test_member_cannot_mark_their_own_attendance` ·
`test_guest_is_redirected_to_login_not_401` ·
🔴 `test_expired_leadership_is_denied` (`effective_to` in the past — the `whereNull` clause is the
whole control) · `test_leader_cannot_reopen_their_own_finalized_occurrence` (FR-04.12) ·
`test_admin_can_reopen_with_a_mandatory_reason` · `test_reopen_without_a_reason_is_rejected`
(FR-04.8) · `test_hidden_controls_do_not_substitute_for_server_side_denial` — a leader POSTs
directly to another group's save endpoint and is refused (FR-11.6: navigation visibility never
replaces authorization).

Four more, added 2026-08-21 with the SEC-001 correction (§25.4). They assert the *mechanism*, not
just the outcome, because the outcome was right in the broken design too — it was right by
accident, and only for accounts that were not usergroup 3:

- 🔴 `test_gate_before_grants_usergroup_three_every_ability` — asserts the **upstream** behaviour
  directly: `Gate::allows('anything-at-all', ...)` is `true` for a usergroup-3 account. A
  documenting test in the WP 0A style. When FR-11 removes the `Gate::before`, this goes red and
  **is replaced, not deleted** — the same treatment as
  `test_documents_defect_usergroup_id_three_bypasses_all_permission_checks`.
- 🔴 `test_icare_authorization_does_not_pass_through_the_gate` — a usergroup-3 account with no
  leader membership is denied 403, **and** the same actor is shown to be granted by
  `Gate::allows()` in the same test. The two assertions side by side are the proof the iCare path
  routes around the bypass rather than inheriting it.
- `test_policies_are_not_the_enforcement_path_while_gate_before_is_live` — pins §25.4's rejected
  alternative 3: `EventOccurrencePolicy::record()` invoked **through the Gate** returns true for
  usergroup 3, while `IcareAuthorizer::mayRecord()` returns false. Stops a future "simplification"
  that swaps the middleware for `authorize()`.
- `test_icare_routes_are_not_shadowed_by_an_upstream_route` — `/icare/*` resolves to the package
  controller. Suite 6 measured a whole permission surface silently shadowed by route-file ordering
  (`routes/web.php:262` vs `routes/admin.php`), and a shadowed route raises no error.

### `IcareFinalizationTest`

| Test | Asserts |
|---|---|
| 🔴 `test_finalize_snapshots_the_roster_as_of_the_meeting_date` | a member who joined **after** the meeting gets **no** `absent` row. The highest-value assertion in the file. |
| `test_finalize_writes_absent_rows_for_rostered_members_with_no_row` | FR-04.6, closed roster |
| `test_finalize_writes_no_upstream_event_attendees_rows` | those absences are IFGF-side only (A3) |
| `test_finalize_returns_totals_and_the_missing_reason_exception_list` | FR-05.10 |
| `test_finalize_does_not_refuse_on_missing_reasons` | it reports; it does not trap a leader at the end of a meeting |
| `test_finalize_sets_status_finalized_at_and_finalized_by` | §20.5 |
| `test_finalized_occurrence_is_read_only` | state D; a save returns 409 |
| `test_finalize_is_rejected_on_an_empty_roster` | state A — no snapshot asserting nobody was absent |
| `test_finalize_does_not_run_on_an_open_audience_occurrence` | §21.2's guard, asserted from the iCare side |
| `test_reopen_records_the_reason_and_the_actor` | FR-04.8 — upstream `unlock()` records neither |
| `test_photo_upload_stores_documentation_only` | an `ifgf_attendance_media` row, **no** template, no consent record, no detection-implying `capture_method` |
| `test_photo_upload_rejects_svg_and_validates_by_content` | explicit `mimes:` allow-list; never Laravel's `image` rule; assertion **not** built on `UploadedFile::fake()`'s filename-derived MIME |

### `IcareConcurrencyTest`

| Test | Asserts |
|---|---|
| `test_two_simultaneous_first_saves_create_one_occurrence` | `unique(event_definition_id, starts_at)` + the catch-and-reread path; exactly one row |
| `test_two_leaders_marking_the_same_member_the_same_way_is_not_an_error` | `alreadyRecorded`, one detail row (FR-04.7) |
| 🔴 `test_a_conflicting_mark_is_reported_and_not_silently_dropped` | leader B's contradicting mark lands in `conflicts[]`; A's value stands; **B is not shown a bare success** |
| `test_non_conflicting_rows_in_a_conflicted_save_still_commit` | partial success is the contract |
| `test_conflict_report_names_the_other_actor_and_the_time` | the leader can resolve it |
| `test_duplicate_upstream_scan_row_is_translated_not_swallowed` | `AttendanceRecorder` step 5c's `23000` path, exercised from the iCare side |

**Total: roughly 80 tests across seven files.** They are Phase 1A/R1b tests, **not** WP 0A
characterization — they assert intended behaviour of tables that do not exist, so they do not count
toward the 80–120 characterization target and must not be reported as if they did.

---

## 25.6 Where this lands in the fill-pass order

§23's seven batches are unchanged; iCare threads through them rather than adding a batch:

| §23 batch | iCare addition |
|---|---|
| 1 migrations | A4's `ifgf_event_definitions.group_id`, A6's `ifgf_attendance_details.note`. Verify both with `SHOW CREATE TABLE` — `group_id` must be **INT** (Rule 0, upstream `groups.id`). |
| 2 models | `EventDefinition::group()`, `Group` ↔ `GroupProfile` accessor, `AttendanceDetail::visitorFromGroup()`. No `$dates` anywhere (REG-001). |
| 4 services | A5's DTO fields **first**, then `IcareAttendanceService`, then `MemberRegistryService::createUnclaimed()` |
| 5 policies | §25.4's methods on `EventOccurrencePolicy`, `AttendanceDetailPolicy`, `GroupMembershipPolicy` |
| 7 controllers | `IcareAttendanceController` + its four form requests, and the route registration in §25.3 |

**`IcareDateResolutionTest` is written before batch 4**, not after batch 7. It is a unit test with
no database, it needs nothing but `defaultLocalDate()`, and it is the one file whose absence would
let a seven-day error ship.

**`attachPhoto()` is filled last**, after Release 1 creates a private disk and a signed-access
route. It must not be filled against a public disk (§21.5, and CARD-003 is the live example of what
that produces).

---

## 25.7 Open items and owner decisions

Continuing §24's numbering.

| # | Item |
|---|---|
| 10 | **A4 creates an upstream `events` row per iCare group.** It becomes visible in upstream event lists, exports and counts. The `icare` event type is the seam that keeps it filterable. |
| 11 | **A6's `note` is pastoral free text about a named member** and is excluded from every export and member-facing view until the FR-11.15 privacy review says otherwise. |
| 12 | **A7 gives `event_attendance_sessions.attendance_date` two semantics** — upstream writes the server-derived day, the IFGF path writes the branch-local day. WP 0C's reconciliation must know which path wrote a row. |
| 13 | **`attachPhoto()` has no working backend.** Same blocker as `MemberMediaService::temporaryUrl()` (§24 item 5): no private disk exists. An iCare group photo on the public disk is CARD-003 with ten identifiable members. |
| 15 | **`group_links` has a PRIMARY key and nothing else** — no unique on `(group_id, user_id)`, and duplicate membership is reachable (GRP-003, measured 2026-08-21). §21.4 projects `ifgf_group_memberships.projected_group_link_id` onto it as a *nullable unique* FK, so "create or reuse a `GroupLink`" is **ambiguous when duplicates exist**. Same class as WP 0C item 3's `userprofiles` duplicates: dedupe is a pastoral data decision, not a technical one. `GroupMembershipService` must pick deterministically **and record that it did**. |
| 16 | **`group_links.church_id` is not trustworthy provenance** (GRP-002, measured 2026-08-21): `GroupLinkController::store()` takes `church_id` **from the request body**, performs no `Gate` check, and was measured writing into another church's group. **Nothing in iCare may derive a church or a branch from `group_links`.** §25.1 resolves church from `users.church_id` and branch from `ifgf_group_profiles.branch_id`, which is correct as written — but any FR-12 backfill populating `ifgf_group_memberships` from `group_links` inherits the bad data and must reconcile against `users.church_id`, treating a mismatch as an import exception. |
| 17 | **Deleting a group hard-deletes every member's permission rows** (GRP-001), unscoped to group or church, while the group itself is only *soft* deleted and nothing records what the permissions were. iCare groups are `groups` rows. **Until FR-11 replaces this, deleting an iCare group is a destructive act with no undo**, and the iCare surface must not expose one. |
| 14 | **FR-05.3's "temporary exception" to one-active-primary is not modelled.** Visitors cover the common case; a genuine administrator-recorded dual membership with an expiry is not expressible against the §8 partial unique index. Release 2 unless the owner needs it sooner. |

### ▶ Decisions this part needs from the owner

- **D7 — A4, the backing `events` row.** Approve one upstream `events` row plus one
  `ifgf_event_definitions` row per iCare group, created on demand by `ensureDefinition()`, with
  `group_id` added to the definition. The write path forces it: `AttendanceRecorder`'s lazy session
  needs an `event_id`. The alternative is a second attendance path for iCare, which contradicts
  FR-04.1. **§25.0 A4.**
- **D8 — A6, the note column.** Approve one nullable `note` on `ifgf_attendance_details`, with
  `excused` requiring it non-empty. Without it FR-05.5's notes have nowhere to go and FR-05.10's
  exception list is not computable. Confirm it is excluded from exports. **§25.0 A6.**
- **D9 — A7, what upstream's `attendance_date` receives.** Recommended: the **branch-local** day, at
  the cost of two semantics in one upstream column. The alternative is the UTC day — internally
  consistent, and wrong for any occurrence starting before 08:00 local. **§25.0 A7.**
- **D10 — no QR token at quick-add.** Confirm that an unclaimed visitor record gets no `qr_token`
  until it is claimed or activated, departing from §21.6's "a member never exists without a
  credential" (which was written for self-service registration). `MemberQrService::issue()` is
  idempotent, so issuing later is safe. **§25.1.**
- **D11 — is iCare in R1a or R1b?** Unchanged from §8c's scope note and still unanswered. Building
  it early means either moving iCare into R1a or delaying the Sunday cutover. Part 3 exists so the
  answer is a scheduling decision rather than a design one.

---

## 25.8 What Part 3 does NOT establish

Same standard as §24's closing section, and it applies with more force here because Part 3 specifies
a **screen** as well as a schema.

- **Nothing here has been executed.** No PHP ran in this session; no migration, no model, no test.
  Every signature is a claim about what should exist.
- **A4, A5, A6 and A7 are not patched into Parts 1 and 2.** Unlike A0–A3 they are proposals awaiting
  D7–D10. Do not generate migrations from Part 1 expecting `group_id` or `note` to be there.
- **The date rule is argued, not measured.** §25.2's seven-day claim follows from UTC+8 and weekday
  arithmetic; `IcareDateResolutionTest` is what turns it into a fact, and it is the first thing
  written.
- **§25.4 was wrong once and is corrected in place.** The first version proposed policies as the
  escape from SEC-001; `Gate::before` in `AuthServiceProvider` grants usergroup 3 every ability,
  policies included. The correction is dated and the reasoning kept, because the broken plan reads
  as correct and would have passed every grant test. **Read §25.4 end to end; do not skim the
  method list.**
- **SEC-002 is not fixed by anything in Part 3.** The per-leader scope control is *specified* here
  and does not exist in the application. `test_documents_defect_unassigned_leader_can_record_attendance`
  still asserts the current, wrong 200 on the upstream surface; **replace it when FR-11 lands, do
  not delete it.**
- **No face detection, no recognition, no biometric template, and no consent gate for one.** §8c
  requirement 6 is a stored file. Detection is Phase 2B behind WP 0D and behind FR-11.17's separate
  legal and pastoral approval; nothing in Part 3 may be read as preparing for it beyond the
  `capture_method` string column that already exists.
