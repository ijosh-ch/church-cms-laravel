# GAS → Laravel 13 + PostgreSQL — migration design

**Written 2026-09-07.** Planning only; nothing built, nothing committed, no branch created yet.
Supersedes the MySQL assumptions in `SCHEMA_SPEC.md` Rule 0 only — the domain design in that file
survives intact and is the source for §4 below.

**Owner decisions taken this session:**

| # | Decision |
|---|---|
| D-A | Build on a **new branch inside `church-cms-laravel`**, not a greenfield repo |
| D-B | **Keep** Google Calendar birthday sync · **drop** Forms + Sheets as system of record |
| D-C | **Add** a read-only Forms ingestion adapter, so Forms can keep running in parallel |
| D-D | **Add** NFC attendance alongside QR |
| D-E | Import **members + full attendance history** |

---

## 0. Governance conflicts — raised, not silently resolved

1. `TODO.md` states **"WP 0C must not begin"** and **"`SCHEMA_SPEC.md` … do not action it."**
   This plan is WP 0C-class work. The owner instruction supersedes the standing rule; recorded here
   so the override is visible rather than assumed.
2. **WP 0A's exit gate is not passed** (4 of 7) and its **94 characterization tests are pinned
   against MySQL**. Changing driver moves date casting, string collation and `enum` behaviour.
   Those tests are a MySQL baseline and **stop being a PostgreSQL baseline the moment the branch
   switches driver.** Either finish WP 0A on MySQL first, or accept that the baseline is re-pinned.

---

## 1. Preconditions — verified on this machine, 2026-09-07

| Component | State | Action needed |
|---|---|---|
| Laravel | **13.24.0** installed | none — already current |
| PHP | **8.4.24**, bare `php` resolves | none |
| PostgreSQL server | **17.10** at `C:\Program Files\PostgreSQL\17` | `psql` is not on PATH; PG 18 is the current major if you want to upgrade |
| `pdo_pgsql` / `pgsql` | 🔴 **NOT ENABLED** — DLLs ship, php.ini has no lines | **two-line php.ini edit, see below** |
| `config/database.php` | `pgsql` connection block already present (L77) | none |
| Laravel Socialite | **not installed** | `composer require laravel/socialite` |
| i18n | `resources/lang/en` only; `locale => 'en'` | add `id`, `zh_TW` |
| MySQL coupling in app code | **only 6 files** use `DATE_FORMAT(` | port to `to_char()` |
| `ENGINE=` / `AUTO_INCREMENT` | **only** in `database/schema/mysql-schema.sql` | no app coupling — dump is per-driver |
| `->enum()` in migrations | ~20 files | works on PG (compiles to `varchar` + `CHECK`); new `ifgf_` tables must not use it |

**Enable PostgreSQL** — add after line 1861 of `C:\php\8.4\php.ini`:

```
extension=pdo_pgsql
extension=pgsql
```

`libpq.dll`, `php_pdo_pgsql.dll` and `php_pgsql.dll` are already present in `C:\php\8.4`.
Verify with `php -m | Select-String pgsql` before anything else in this plan.

---

## 2. Feature map — all five GAS features, function by function

Source: 2,635 lines across 7 JS files, 44 functions (`GAS_INVENTORY.md`, graph rebuilt this
session: 114 nodes / 146 edges / 11 communities).

### 2.1 Church member registration

| GAS | Laravel replacement |
|---|---|
| Google Form + `onFormSubmit` | `RegistrationController` + `MemberRegistrationRequest` |
| `getMemberDetailsFromResponse` | Form Request validation + `MemberRegistryService::register()` |
| `matchesFieldTitle`, `getColumnIndexForFieldTitle`, `getFieldIdByTitle`, `autoDetectEntryIds`, `logQuestionIDs`, `isFieldRequiredByTitle`, `getFormFieldMapping` | **deleted** — 7 functions of fuzzy column-header matching exist only because the store is a spreadsheet |
| `cleanPhoneNumber` | `PhoneNormalizer` → **E.164 with explicit default region**. The GAS leading-zero branch is a literal no-op (`cleaned = cleaned`), so `0912345678` / `+886912345678` / `886912345678` are three keys for one person today |
| `checkIfMemberExists`, `findMemberRowInSpreadsheet` | `DuplicateScorer` on normalised email **or** E.164 phone |
| `addEditUrlSpreadsheet`, `editMember`, `checkIfResponseIsEdited`, `checkIfResponseAlreadyExists` | **deleted** — replaced by an authenticated self-service profile page. The whole "edit URL" mechanism is a workaround for Forms having no identity |
| `openRegistrationForm` / `closeRegistrationForm` / `checkFormAccess` | a `registration_open` window setting, or drop entirely — Laravel has real auth |

**Registration is via Google / Apple sign-in.** See §6.

### 2.2 Birthday → Google Calendar  *(kept — D-B)*

`calendar.js` is **more careful than the PRD assumed** and ports across almost unchanged. The
"Birthday ID" column is the **only 100%-filled column in the roster** (217/217) — those event
series IDs must be carried into `ifgf_calendar_links` so no member's event is recreated.

| GAS | Laravel |
|---|---|
| `addBirthdayToCalendar` | `BirthdaySyncService::create()` |
| `checkAndUpdateBirthdayIfChanged` | `::reconcile()` — keep the in-place `setRecurrence()` attempt, keep the delete-and-recreate fallback |
| `updateBirthdayEventDetails` | `::patchDetails()` — details-only path when the date is unchanged |
| `removeEventById`, `removeBirthdayFromCalendar`, `removeBirthdayEventsByNameAndDate` | `::revoke()` + name-based cleanup when no series ID exists |
| `checkBirthdayExists` | `::exists()` |
| `syncAllBirthdays` | `php artisan ifgf:calendar:sync` (queued, chunked) |

Auth changes from the script owner's OAuth grant to a **Google service account**, credentials in
`.env` and never committed.
⚠ `build.md` SECURITY 8 forbids revealing contact / iCare / pastoral data in Calendar titles — the
event title must be the member's name only.

### 2.3 QR code for attendance  🔴 **defect, not a feature to port**

`generatePrefilledUrl` builds the QR payload as a prefilled Google Form URL containing the member's
**email, WhatsApp number, full name and iCare group in plaintext**. That URL *is* the QR. Anyone who
photographs it — printed, on a screen, over a shoulder, in a group photo — reads all four.

It is also **not rotatable**: the URL is a pure function of the member's own data, so regenerating
produces an identical code. There is no revocation path short of changing their email or phone.

Violates `build.md` SECURITY 8, `build.md` TECHNICAL BASELINE 7, and PRD FR-02's "opaque, rotatable".

**Replacement:** `MemberQrService` issues an opaque high-entropy token, stored **hashed**, resolved
server-side, revocable and reissuable. `simplesoftwareio/simple-qrcode` is already in `composer.json`.

**Cutover consequence:** every existing member QR stops working by design. 217 members must be
reissued and told. This is the single most visible user-facing change in the migration — it needs a
communication plan, not just a deploy.

### 2.4 Sunday weekly attendance, two branches

| GAS | Laravel |
|---|---|
| Second Google Form + `Daftar Absensi` log | `AttendanceController` — QR scan, NFC tap, or manual tick |
| `addWeeklyAttendanceColumns` (insert 2 cols at F, copy H:I→F:G, merge F6:G6) | `OccurrenceGenerator` — `ifgf_event_occurrences` rows, scheduled. The whole column-shuffling job disappears |
| `updateSheetWithQrCodeUrl` | credential issuance, §5 |
| `Absen-TPE_ZL` | **not imported** — the script never maintained it; stalled at 2026-04-26 while the per-branch sheets run to 2026-09-13 |

🔴 **Branch attribution is absent from 98.8% of raw scans.** `Lokasi` is populated on **40 of 3,516**
rows, because the QR deliberately leaves it blank for the member to pick and members don't. Fixed
structurally: attendance is recorded against an **occurrence**, which already knows its branch — the
member is never asked.

`Online` is set on **3 of 3,516** rows. Online attendance is modelled (the grids have an Onsite/Online
pair per week) but is effectively unused in practice.

**Filtering by branch and iCare** (both required) falls out of `ifgf_event_occurrences.branch_id` and
`ifgf_group_memberships` — no extra structure needed.

### 2.5 Quarterly report

Currently `Summary Absen` — 55×80 of spreadsheet formulas over monthly averages by
Adult / College / Teens and Youth / Kids × TPE / ZL, with a "Goal 2026" column.

Becomes a query over `ifgf_attendance_details ⋈ ifgf_event_occurrences`, grouped by quarter, branch
and category. PostgreSQL earns its keep here: `date_trunc('quarter', …)`, `FILTER (WHERE …)`
aggregates and window functions replace the formula grid outright. Export via
`maatwebsite/excel` (already required) and `barryvdh/laravel-dompdf` (already required).

⚠ Quarter boundaries must be computed in the **branch timezone**, not UTC. `SCHEMA_SPEC.md` §20.0 A0
records that storage is UTC (settled 2026-08-15, UP-009) — every calendar day derived from an
instant converts to `ifgf_branches.timezone` first. This is the rule most likely to be broken.

---

## 3. The two additions

### 3.1 Google Forms read adapter  *(D-C)*

Forms stops being the system of record but keeps running in parallel, and any *other* form data
must remain retrievable.

```
Google Forms API ──▶ ifgf_form_ingestions (payload JSONB, raw, immutable)
                          │
                          ├─ FormResponseMapper ──▶ member / attendance / group tables
                          └─ unmapped fields stay queryable in JSONB, forever
```

**Why this is the PostgreSQL-specific win.** The legacy roster has **33 columns of which ~14 carry
real data** — 12 are ≤0.9% filled and 3 are 0%, residue from an older form version. Modelling those
as columns is wrong; discarding them loses data. `JSONB` + a **GIN index** stores every field
verbatim and keeps it queryable without a schema change per form revision. MySQL's JSON type has no
equivalent index. This alone justifies the driver choice.

Runs on a schedule, idempotent on Google's `responseId`.

### 3.2 NFC attendance  *(D-D)* — read this before designing any UI

**The stated premise does not hold.** A phone does not expose a stable NFC ID that can be read and
stored:

- **Android HCE randomizes the NFCID1 on every tap.** It is a per-session random value, by design,
  for privacy. There is no persistent phone UID to enrol. HCE additionally requires a **native
  Android app** registering an AID in its manifest — a Laravel web app cannot emulate a card.
- **iOS**: third-party HCE requires Apple's *NFC & SE Platform* entitlement — granted case-by-case
  under a commercial agreement, and only in specific regions. **Taiwan is not among them.**
- **Web NFC** (Chrome on Android only; absent from iOS Safari entirely) can **read NDEF tags** from
  the reader side. This is the half that does work.

**Therefore: generalise the credential, don't special-case the phone.**

| Type | Mechanism | Status |
|---|---|---|
| `qr_token` | opaque token in a QR, §2.3 | Release 1 — works on every phone |
| `nfc_tag` | **physical NTAG213/215/216 card or sticker** — stable 7-byte UID **plus** an NDEF record carrying the opaque token | Release 1 — ~US$0.30/tag for 217 members ≈ US$65 |
| `hce` | companion Android app responding to a SELECT AID | **reserved** — schema ready, build only if wanted |

**Reader side, both supported:**
- an **Android phone**, Chrome, Web NFC (`NDEFReader`) — a leader taps members in, no extra hardware;
- a **USB ACR122U** (~US$35) on a laptop at the door, for a fixed check-in desk.

**Store the UID, trust the token.** The tag UID is convenient but *clonable* — plain UIDs are not a
security boundary. Resolve attendance on the **NDEF token**, hashed, exactly as the QR path does;
treat the UID as a lookup hint only. iPhone members keep the QR path, which every phone can display.

This gives the tap-to-attend experience asked for, with a mechanism that exists today, and leaves
one migration-free row type for phone HCE later.

---

## 4. ERD — PostgreSQL

Ported from `SCHEMA_SPEC.md`. **Rule 0 of that file is void here**: the mixed-key-width rule
(`unsignedInteger` FKs to dodge MySQL errno 150) is a MySQL artefact. On PostgreSQL every new table
uses `$table->id()` and `foreignId()->constrained()` uniformly.

Conventions: `timestamps()` everywhere; `softDeletes()` where noted; **no enums** — controlled values
are `string` + application validation or an FK to a lookup table; timestamps stored **UTC**; public
identifiers opaque, never sequential; every imported row carries `import_batch_id`.

```
                    ifgf_branches (TPE, ZL)
                          │ timezone, gmaps_url
        ┌─────────────────┼──────────────────────┐
        │                 │                      │
ifgf_member_profiles      │            ifgf_event_definitions
  ├─ branch_id ───────────┘              ├─ branch_id
  ├─ category_id ──▶ ifgf_member_categories       ├─ group_id  (nullable → iCare weekly)
  ├─ education_level_id ──▶ ifgf_education_levels ├─ event_type_id ──▶ ifgf_event_types
  ├─ public_ref (uuid, opaque)                    └─ rrule, timezone
  ├─ user_id ──▶ users  (nullable until first login)          │
  └─ occupation, school, chinese_name, birthday               ▼
        │                                        ifgf_event_occurrences
        ├──▶ ifgf_contact_points                   ├─ definition_id
        │      type: email|whatsapp|line            ├─ branch_id
        │      value, normalized_value (E.164)      ├─ occurrence_date (branch-local)
        │      is_primary, verified_at              └─ starts_at (UTC)
        │                                                      │
        ├──▶ ifgf_member_credentials  ◀── §3.2                 │
        │      type: qr_token | nfc_tag | hce                  │
        │      token_hash, nfc_uid, label                      │
        │      issued_at, revoked_at, last_used_at             │
        │                                                      ▼
        ├──▶ ifgf_group_memberships              ifgf_attendance_details
        │      group_id ──▶ groups (upstream)      ├─ occurrence_id
        │      effective_from / effective_to       ├─ member_id (nullable → guest)
        │      role, note                          ├─ status: present|absent|excused
        │      ⚠ effective-dated, never deleted    ├─ mode:   onsite|online
        │                                          ├─ method: qr|nfc|manual|import
        ├──▶ ifgf_calendar_links                   ├─ credential_id (nullable)
        │      google_calendar_id                  ├─ recorded_by, recorded_at
        │      event_series_id  ← 217 carried over └─ guest_name, guest_phone
        │      synced_at, status
        │
        ├──▶ ifgf_member_media / ifgf_media_variants
        └──▶ ifgf_consents

ifgf_form_ingestions          ifgf_import_batches ──┬─ ifgf_import_rows
  ├─ source, google_form_id                         ├─ ifgf_import_conflicts
  ├─ response_id (unique)                           └─ ifgf_identity_map
  ├─ payload JSONB + GIN index
  └─ mapped_member_id, status
```

**"At least one iCare, else uncategorised."** Do **not** put a nullable `group_id` on the member.
`ifgf_group_memberships` is effective-dated, so a member with no current row is *by definition*
"Belum mengikuti" (62 of 217 today). A left join answers it; a `CHECK` constraint cannot express
"at least one" across time and should not be attempted.

**Indexes that matter**: `ifgf_attendance_details (occurrence_id, member_id)` unique;
`ifgf_member_credentials (token_hash)` unique-where-not-revoked (a **partial index** — another
PostgreSQL-only win); `ifgf_contact_points (normalized_value)`; GIN on
`ifgf_form_ingestions.payload`.

---

## 5. Packages

**Already in `composer.json` — reuse, do not re-solve:**
`simplesoftwareio/simple-qrcode` (QR) · `maatwebsite/excel` (import + report export) ·
`barryvdh/laravel-dompdf` (PDF reports) · `spatie/laravel-medialibrary` (member photos) ·
`spatie/laravel-activitylog` (audit) · `league/csv` · `santigarcor/laratrust` (roles) ·
`laravel/sanctum`.

**To add:**

```
composer require laravel/socialite            # Google + Apple sign-in
composer require socialiteproviders/apple     # Apple is not a first-party Socialite driver
composer require google/apiclient             # Calendar + Forms read adapter
```

**Not needed:** no NFC PHP package exists or is required — Web NFC is browser-side JS, and the
ACR122U path speaks over a small local bridge. The server only ever receives a token string.

---

## 6. Authentication — Google + Apple

Socialite, with `ifgf_member_profiles.user_id` nullable so an imported member exists *before* they
first sign in, then claims their record by matching a verified email against `ifgf_contact_points`.

🔴 **Two blockers in the current fork that must be fixed before any public hostname**, both already
recorded in `ICARE_PILOT_PLAN.md`:
- **AUTH-001** — `routes/web.php:68` passes `['register' => false]`, then **`:71` calls
  `Auth::routes()` again with no arguments** and reopens it. `GET /register` returns 200.
- **SEC-001** — `RegisterController::create()` hard-codes `usergroup_id = 3` (the value that bypasses
  the `permission:` middleware *and* is granted every ability by `Gate::before`) **and** explicitly
  grants every `Permission` row. Self-registration is a church-admin factory. The "captcha" only
  checks that a form field is *present*, never verifying it with Google.

Social login must **not** be layered on top of that controller. It needs its own path.

⚠ **Apple** requires a paid Apple Developer Program membership (US$99/yr) plus a Services ID, a key
and a verified return domain. Google needs only an OAuth client. If the US$99 isn't wanted, ship
Google first — the schema is identical.

---

## 7. Localisation — `id`, `en`, `zh_TW`

`resources/lang/en` exists; `locale` and `fallback_locale` are both `en`.

1. `resources/lang/{id,zh_TW}` + a `SetLocale` middleware (session → `Accept-Language` → default).
2. **Default to `id`** — the legacy portal, the form and the whole roster vocabulary are Indonesian.
3. **Do not translate the data vocabulary.** `iCare Linkou`, `Belum mengikuti`, `Adult`, `College`,
   `Teens and Youth`, `Kids` are stored values, not UI strings. Translate the *labels* around them;
   translating the values breaks the import identity map.
4. `zh_TW`, not `zh_Hant`/`zh_CN` — Traditional, and members already carry Chinese names (198 of 217).

---

## 8. Import  *(D-E — members + full attendance history)*

Source: `Jemaat & Absensi (3).xlsx`, read this session — **the workbook has moved on** from the
version `WORKBOOK_INVENTORY.md` documents (v2, 2026-08-09):

| | v2 (documented) | **v3 (now)** |
|---|---|---|
| Attendance log rows | 3,227 | **3,516** |
| Log date range | → 2026-08-02 | **→ 2026-09-06** |
| Grid weeks | → 2026-08-09 | **→ 2026-09-13** |
| Members | 217 | 217 |

Order, each stage gated on the previous:

1. **Lookups** — 2 branches, 4 categories, 7 education levels, 22 iCare groups.
2. **Members** — 217 rows, ~14 live columns of 33. Expect **one structurally malformed row** (the
   grids carry 216 against the roster's 217), **3 duplicate names**, one birthday stored as text,
   three numeric LINE IDs, and two `LINE ID` columns differing only in case (one 0% filled — exactly
   the defect a case-insensitive matcher merges silently).
3. **Contact points** — normalise to E.164. **Expect duplicates the legacy system never detected**,
   per the `cleanPhoneNumber` no-op above. Do not trust legacy phone matching.
4. **Calendar links** — 217 event series IDs, carried verbatim. Nothing is recreated.
5. **Group memberships** — effective-dated from the roster's current iCare; 62 members correctly land
   with no row.
6. **Occurrences** — 46 weeks × 2 branches from the grid headers (row 6, merged date over an
   Onsite/Online pair in row 7). **Newest week is leftmost.**
7. **Attendance** — from the **grids**, not the raw log. The grids are the only source with reliable
   per-branch attribution; the log has it on 1.1% of rows. Grid cells hold a scan-time string
   (`'15:51'`) for present and `0` for absent.
8. **Raw log** → `ifgf_form_ingestions` verbatim as JSONB, so nothing is lost even where it cannot be
   attributed.
9. **Reconciliation report** — every conflict recorded in `ifgf_import_conflicts`, never auto-merged.

The workbook is **read-only and never committed**; it contains real member data.

---

## 9. Phasing

| Phase | Content | Gate |
|---|---|---|
| **P0** | Enable `pdo_pgsql`; create the branch; add the `pgsql` connection; run the 93 upstream migrations against PG; port the 6 `DATE_FORMAT(` files | app boots on PG; suite re-pinned |
| **P1** | 21 `ifgf_` migrations + models, generated (`make:model Foo --all`) | migrations reversible; factories seed |
| **P2** | Socialite Google (+ Apple if funded); fix AUTH-001 / SEC-001 | no self-service admin path exists |
| **P3** | Import pipeline, dry-run first, reconciliation report reviewed | 217 members + 46 weeks reconcile |
| **P4** | Registration + profile; Calendar sync with the 217 carried IDs | zero events recreated |
| **P5** | Attendance: QR issue/scan, NFC tag enrol/tap, manual tick; branch + iCare filters | all three methods write one path |
| **P6** | Quarterly report + exports | matches `Summary Absen` for a known quarter |
| **P7** | i18n `id` / `en` / `zh_TW`; Forms ingestion adapter | — |

**One work package per session** (`CLAUDE.md`). P0 alone is a session.

---

## 10. Open — owner input needed

| # | Question |
|---|---|
| Q1 | Finish WP 0A on MySQL first, or re-pin the 94 characterization tests against PostgreSQL? (§0.2) |
| Q2 | Fund Apple sign-in at US$99/yr, or ship Google-only first? (§6) |
| Q3 | Order NFC tags for all 217 members (~US$65), or pilot one iCare group? (§3.2) |
| Q4 | Build the companion Android app for true phone-tap HCE, or is a tag card acceptable? (§3.2) |
| Q5 | Upgrade PostgreSQL 17.10 → 18, or stay on the installed 17? (§1) |
| Q6 | QR reissue: how are 217 members told their old code stops working? (§2.3) |

---

## 11. Session log — 2026-09-07/08

### Landed

| # | Change | File |
|---|---|---|
| 1 | `pdo_pgsql` + `pgsql` enabled | `C:\php\8.4\php.ini` (backup: `php.ini.bak-20260907`) |
| 2 | Branch `feat/postgresql-multisite` created off `contrib/laravel-supported-platform` | — |
| 3 | `ifgf_member_credentials` migration | `database/migrations/2026_09_07_155812_create_ifgf_member_credentials_table.php` |
| 4 | `MemberCredential` model | `custompackages/ifgf/church-operations/src/Models/` |
| 5 | `MemberCredentialService` — issue / rotate / revoke / resolve | `custompackages/ifgf/church-operations/src/Services/` |
| 6 | Usher site: config, routes, middleware, 2 controllers, 2 views | `config/ifgf-sites.php`, `routes/usher.php`, `app/Http/Middleware/EnsureUsher.php`, `app/Http/Controllers/Usher/` |
| 7 | `usher.*` strings in en / id / zh_TW | `resources/lang/{en,id,zh_TW}/usher.php` |
| 8 | 13 tests, 39 assertions | `tests/Feature/Credential/MemberCredentialServiceTest.php` |

**Verified:** `PDO::getAvailableDrivers()` → `mysql, pgsql, sqlite` · migration applied on MySQL
testing DB · 6 usher routes register · **full suite 118 passed / 384 assertions / 0 failed / 199s**
(was 105 — the 13 new ones are additive, nothing regressed).

**Nothing committed.** `build.md` CONTRACT 7.

### The QR replacement, as built

| Property | Legacy `generatePrefilledUrl` | `MemberCredentialService` |
|---|---|---|
| Payload | prefilled Form URL with email + phone + name + iCare | `IFGF1:` + 43 chars of base64url, 256 bits |
| Member data in payload | four fields, plaintext | none |
| At rest | n/a — derived from member data | SHA-256 hash for lookup **+** APP_KEY-encrypted copy for re-display |
| Rotatable | no — regeneration is identical | yes, `rotate()`, no grace period |
| Revocable | only by changing email or phone | yes, with actor and reason |
| Per member | exactly one | many — QR *and* NFC tag, independently revocable |
| Failure disclosure | n/a | one shape for unknown / revoked / malformed |

Two design points worth keeping in view:

- **SHA-256, not bcrypt.** The token carries 256 bits of entropy, so it is not subject to the
  offline guessing attack that makes a slow hash necessary for passwords — and resolution must be
  one indexed exact-match lookup on every scan.
- **Hash *and* ciphertext, not either alone.** Hash-only would make the code write-once and force a
  rotation every time a member reopened it, invalidating any printed card. Plaintext-only (what
  `SCHEMA_SPEC.md` §21.1 specified) puts every working credential in any database backup. APP_KEY
  lives outside the database, so the pair gives both properties.

### Deviation from `SCHEMA_SPEC.md` §21.1 — recorded, not silent

§21.1 specified `MemberQrService` with `qr_token` / `qr_short_code` / `qr_version` /
`qr_rotated_at` as **columns on the profile**, the token stored as `char(32)` **in plaintext**.
Superseded on two counts: NFC (D-D) means a member holds more than one credential, and plaintext
storage is a weaker property than the encrypted-plus-hashed pair above. The service name changed to
`MemberCredentialService` to match. Everything else in §21.1 — no URL, no grace period on rotation,
one failure shape, `resolveToken` answers *who* and never *whether they may* — is carried through
unchanged and is asserted by the tests.

I also dropped the **partial index** (`unique … where revoked_at is null`) that §4 of this document
proposed as a PostgreSQL win. At 217 members it buys nothing measurable and would have needed a
driver-specific branch in the migration. Plain unique indexes on `token_hash` and `short_code` work
identically on both drivers. The JSONB + GIN argument for PostgreSQL in §3.1 still stands on its own.

### Not built — and why

- **No attendance write.** `ScanController` resolves a credential to a member and stops.
  `ifgf_event_occurrences` and `AttendanceRecorder` are P1/P5. The scanner is deliberately useful
  before the writer exists, because resolving is what proves the credential works end to end.
- **No `usher` role seeded.** `EnsureUsher` fails closed against `['usher','churchadmin',
  'superadmin']`, so **the site is currently unreachable by everyone**. Seeding that role is the
  next action.
- **No PostgreSQL database yet.** The driver loads; no role or database exists, and the app still
  points at MySQL. Requires a password, so it is an owner step — §12.

### 🔴 Still true, and now more urgent than before

`attend.ifgf.site` must not reach a public hostname while **AUTH-001** and **SEC-001** stand.
`EnsureUsher` deliberately avoids `Gate` — because `Gate::before` returns true for every ability
when `usergroup_id == 3`, and `RegisterController::create()` hands that exact value to anyone who
self-registers through the route `routes/web.php:71` reopens. That middleware is *containment*, not
a fix. Fixing both is P2 and now gates a second public hostname, not one.

---

## 12. Next actions

**Owner — needs a password, so not done here:**

```sql
-- psql -U postgres -h 127.0.0.1
CREATE ROLE ifgf WITH LOGIN PASSWORD '<choose one>';
CREATE DATABASE ifgf_cms OWNER ifgf ENCODING 'UTF8' LC_COLLATE 'C' LC_CTYPE 'C' TEMPLATE template0;
```

Then in `.env` — and note `SESSION_DOMAIN` stays unset, per `config/ifgf-sites.php`:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ifgf_cms
DB_USERNAME=ifgf
DB_PASSWORD=<the same>

IFGF_MEMBER_DOMAIN=member.ifgf.site
IFGF_USHER_DOMAIN=attend.ifgf.site
```

Leave both domains unset locally; the usher site then answers at `/usher`.

**Next session, item 1:** seed the `usher` role and add a feature test proving
`attend.ifgf.site` rejects an authenticated member who lacks it — the middleware is written but
nothing yet asserts it fails closed. Then P0: point `.env` at PostgreSQL and run the 93 upstream
migrations against it, expecting the 6 `DATE_FORMAT(` files in §1 to break.

---

## 13. Session log — 2026-09-08: schema, prototype, test harness

### Machine-wide PostgreSQL credentials

`~/.ifgf/postgres.env` holds host, port, database, role and (once you run the setup script)
the password. `bootstrap/global-env.php` loads every `~/.ifgf/*.env` **before** the framework
reads the repository `.env`, and `config/database.php` prefers `IFGF_PG_*` over `DB_*`. So the
password is entered once per **machine**, not once per checkout — a second clone, a worktree or
a fresh pull all pick it up, and the file cannot be committed because it is not under the repo.

Real environment variables win over the file, so CI and containers override it without editing.

**One owner step remains** — it needs a password, so it was not done here:

```
powershell -ExecutionPolicy Bypass -File tools/setup-postgres.ps1
```

Generates a strong password itself, creates the `ifgf` role and both databases, writes the
password into the credential file and restricts that file to your user. Idempotent.

### Schema — 11 tables, PostgreSQL-first

`database/migrations/ifgf/`, registered from the package provider. The subdirectory is
load-bearing: Laravel globs a migration path **non-recursively**, so these are invisible to a
bare `php artisan migrate` unless registered — which is exactly what lets the test harness
build the `ifgf_` schema alone, without the 93 upstream MySQL-era migrations.

| Table | Note |
|---|---|
| `ifgf_branches`, `ifgf_member_categories`, `ifgf_icare_groups` | lookups. **No "Belum mengikuti" row** — see below |
| `ifgf_members` | `public_ref` UUID is the route key; `user_id` nullable until first sign-in |
| `ifgf_contact_points` | `normalized_value` is E.164 — the fix for the legacy phone no-op |
| `ifgf_group_memberships` | effective-dated; closed with `effective_to`, never deleted |
| `ifgf_member_credentials` | QR + NFC + reserved HCE |
| `ifgf_service_occurrences` | `service_date` is branch-local; `starts_at` is UTC |
| `ifgf_attendance_records` | branch comes from the occurrence, never from the member |
| `ifgf_calendar_links` | carries the 217 existing Google event series ids |
| `ifgf_form_ingestions` | JSONB + GIN — the reason PostgreSQL over MySQL |

**"Belum mengikuti" is not a group.** It is the legacy sheet's way of writing "no group", and it
applies to 62 of 217 members. Here it is the ABSENCE of a current `ifgf_group_memberships` row.
Seeding it as a group would make "not in a cell group" indistinguishable from "in the cell group
named 'not in a cell group'".

Three PostgreSQL features are used where they earn it, each with a MySQL fallback in the same
migration so the suite runs on either during the transition:

- **`UNIQUE NULLS NOT DISTINCT`** on occurrences (PG 15+). A plain unique would not hold: in
  SQL `NULL != NULL`, so every Sunday service (`group_id IS NULL`) would be free to duplicate.
- **Partial unique index** on attendance `WHERE member_id IS NOT NULL`. A plain unique would
  collapse every guest into one row, since they all share a null `member_id`.
- **JSONB + GIN** on ingestions. The legacy roster has 33 columns of which ~14 carry real data;
  JSONB keeps the rest queryable without a column per dead form field.

**Decoupled from upstream on purpose.** No `ifgf_` table has a foreign key to `users`.
Constraining to it would drag all 93 upstream migrations — still carrying the `->enum()` and
`DATE_FORMAT()` problems P0 must solve — into every fresh database and every test run.
`user_id` / `issued_by` / `recorded_by` are plain indexed integers until the upstream port lands.

🔴 **`SCHEMA_SPEC.md` §21.1 is superseded again**: `MemberCredentialService` takes `?int $actorId`
rather than a `User`, so the package no longer imports `App\Models\User` at all.

### The demo fixture and its teardown

`ifgf:demo:seed` — purges, then builds the same eight members, two branches, four iCare groups,
six weeks and 29 attendance rows **every time**. `ifgf:demo:purge` removes it. `--calendar` on
either also purges tagged Google Calendar events.

Fixed rather than random, so tests assert real numbers (Taipei Q3 = 14, adult = 5) instead of
tautologies, and a failure is reproducible. Shaped to hit the edges: two members with no iCare,
one with no Chinese name, one with no birthday, one born 29 February.

🔴 **Purge is precise, not a truncate.** A development database holds real imported members
alongside demo ones.

- **Database**: rows carry `is_demo = true`; children cascade from the member. `forceDelete`,
  because a soft-deleted demo row would keep its unique `public_ref` and break the next seed.
- **Google Calendar**: a calendar is shared and cannot be truncated. Demo events are written
  with `extendedProperties.private[ifgf_demo] = '1'`, and the purge lists and deletes **only**
  by that property. There is deliberately no "delete all" option.

`test_purge_leaves_real_data_completely_untouched` is the assertion that keeps this honest.

### Google Calendar is fakeable, and fake by default

`CalendarGateway` has two implementations. The container binds **`FakeCalendarGateway` unless
`IFGF_CALENDAR_DRIVER=google` is set deliberately** — a safety property, not a convenience: the
birthday calendar holds real members' events and `build.md` forbids touching it. `IfgfTestCase`
forces the fake regardless of environment and throws if anything else is bound, so no test run
can reach a real calendar even with the env var exported.

`GoogleCalendarGateway` is written in full but throws an actionable error until
`composer require google/apiclient` and a service account are configured.

The birthday strategy is a faithful port of `calendar.js`: update the series **in place** first,
fall back to delete-and-recreate only if that fails, patch details when the date has not moved.
Members subscribe to this calendar — delete-and-recreate makes an event vanish and reappear for
every subscriber, which across 217 members is a 217-notification mistake.

### Test harness

`tests/IfgfTestCase` — asserts the database name contains "test" before doing anything, builds
only the `ifgf_` schema, resets the fake calendar per test, and wraps each test in a transaction.

🔴 **`migrate`, never `migrate:fresh`.** `migrate:fresh` drops *every* table in the database,
not only the ones the given `--path` recreates. On the MySQL disposable database — which the
WP 0A suite shares — that would have destroyed the 93 upstream tables those 105 tests depend on,
and surfaced as unrelated red in another file. Caught while writing the harness, before it ran.

### Prototype UI

Two sites, one responsive layout (`resources/views/ifgf/layout.blade.php`), three languages.

- **`member.ifgf.site`** (`/app` locally): dashboard, own QR with short-code fallback.
- **`attend.ifgf.site`** (`/usher` locally): scanner, manual tick roster with branch + iCare
  filters, quarterly report.

Measured at 375px and 1265px, all three pages: **zero horizontal overflow, zero touch targets
under 44px, correct nav swap, tables scroll inside their own container.** Two 36px controls were
found and fixed; the roster's 30px tick is `aria-hidden`/`tabindex="-1"` decoration over a 68px
`role="button"` row.

Dark mode is defined three times — bare `:root` for light, `prefers-color-scheme` guarded by
`:not([data-theme="light"])`, and `[data-theme="dark"]` — so the toggle wins in both directions
and no colour is defined only inside a media query.

**`ifgf:demo:preview`** renders each page to standalone HTML in
`storage/app/prototype-preview/` for review without a running server or database credentials.

**Translation covers labels only.** `iCare Linkou`, `Belum mengikuti`, `Adult`, `College`,
`Teens and Youth` and `Kids` are DATA and stay in their stored form — the import identity map
matches on them exactly, and translating a value would silently break member-to-row matching.

### 🔴 Prototype mode

`IFGF_PROTOTYPE_MODE=true` opens the usher site **without authentication**. Two independent
locks, both required: the flag, and `APP_ENV=local` re-checked inside `EnsureUsher` rather than
trusted from config. A banner renders on every page while it is on.
`test_the_usher_site_stays_closed_even_with_prototype_mode_on` asserts the second lock holds.

This does not make AUTH-001 or SEC-001 any less blocking. Both must be closed before either
hostname is public.

### Results

**181 passed, 582 assertions, 0 failed** (105 pre-existing + 76 new). Two pre-existing tests
changed deliberately: the package version literal `0.1.0-seam` to `0.2.0-prototype`, because the
package is no longer a bare loading seam.

Bugs the new tests caught, all fixed: `ScanController` still returning `Userprofile` field names
(members scanned as blank); a Blade comment containing a directive name, which Blade compiled
*inside* the comment and broke the view; `@json()` failing on a multi-line array of translation
calls; route model binding 404ing because `Member` keys on `public_ref`, not `id`.

**Still not committed.** `build.md` CONTRACT 7.

### Next actions

1. Run `tools/setup-postgres.ps1`.
2. Delete the `IFGF_TEST_CONNECTION` pin in `phpunit.xml` so the suite runs on PostgreSQL.
3. `php artisan migrate --path=database/migrations/ifgf` against `ifgf_cms`, then
   `php artisan ifgf:demo:seed`.
4. Seed the `usher` role and add the test that proves `EnsureUsher` fails closed for an
   authenticated member who lacks it.
5. P0 proper: the 93 upstream migrations on PostgreSQL, expecting the 6 `DATE_FORMAT(` files
   to break.

---

## 14. ERD efficiency review — 2026-09-08

Reviewed against the one goal that matters: **replacing the five Apps Script features**.
The measure is not table count, it is whether each table earns its keep and whether the
queries the five features actually run are cheap.

**Shape:** 11 tables, 137 columns, 48 indexes (50 before this pass).

### Every table maps to a feature

| Feature | Tables it uses | Verdict |
|---|---|---|
| 1. Registration | `members`, `contact_points`, `group_memberships`, `member_credentials` | all earn it |
| 2. Birthday → Calendar | `calendar_links` | see the consolidation note |
| 3. QR / NFC attendance | `member_credentials` | one table for both, and for future HCE |
| 4. Weekly attendance, 2 branches | `service_occurrences`, `attendance_records`, `branches` | all earn it |
| 5. Quarterly report | the two above + `member_categories` | no reporting table needed |
| (new) Forms adapter | `form_ingestions` | the JSONB landing zone |
| (new) iCare filtering | `icare_groups`, `group_memberships` | effective-dated, see below |

Nothing here is speculative infrastructure. There is no `settings` table, no polymorphic
`taggables`, no audit table duplicating `spatie/laravel-activitylog`, and no reporting
rollup table — the report is a query, so it can never disagree with the rows.

### Two indexes removed

Both were pure write cost on tables written during registration and every scan.

**`ifgf_members.full_name`** — the *only* thing that searches this column is the usher
roster, which does `LIKE '%term%'`. **A btree index cannot serve a leading-wildcard LIKE**,
so it was written on every insert and read by nothing. At 217 members a sequential scan is
faster than an index lookup regardless. If name search ever needs help, the correct answer
is a `pg_trgm` GIN index, and the migration now says so.

**`ifgf_member_credentials(type, revoked_at)`** — no query uses it. `resolve()` goes through
the unique `token_hash`, `findByNfcUid()` through `nfc_uid`, and a member's own credentials
through `(member_id, type)`.

### Indexes that do earn their keep

| Index | The query it serves | Frequency |
|---|---|---|
| `member_credentials.token_hash` unique | every single scan | highest |
| `contact_points (type, normalized_value)` | duplicate detection at registration | per registration |
| `attendance_records (occurrence_id, member_id)` partial unique | idempotent scan; also the correctness guarantee | every scan |
| `service_occurrences (branch_id, service_date)` | roster and report | per page |
| `group_memberships (member_id, effective_to)` | "which iCare is this member in *now*" | per roster row |
| `members (branch_id, status)` | the roster query | per page |
| `form_ingestions.payload` GIN | querying legacy fields the schema never modelled | ad hoc |

### Deliberate denormalisation, and its cost

`attendance_records` stores **no branch**. It is reachable only through
`occurrence → branch`, which adds one join to the report. That join is the point: it makes
it *impossible* to record attendance at a branch the service did not happen at. The legacy
system stored branch on the scan and got it on **40 of 3,516 rows**. One join is a good
price for a class of bad data that can no longer exist.

Same reasoning for **no `current_group_id` on the member**. Denormalising it would make the
roster query cheaper by one join and would destroy the ability to answer "which iCare was
this member in during Q2" — which the quarterly report will want.

### Two things I would flag rather than change

**`ifgf_calendar_links` is 1:1 with `ifgf_members` today** and is the only real
consolidation candidate — five of its columns are sync bookkeeping (`status`,
`last_synced_at`, `sync_error`, `synced_birthday`, `synced_title`). Folding it into
`ifgf_members` would save a join on a query that runs once per member per sync, not per
page. Keeping it separate keeps sync state out of the member row and leaves room for a
second calendar (anniversaries were mentioned in the PRD). **Low stakes either way** — the
decision can be deferred without cost.

**`import_batch_id` exists on six tables, and `ifgf_import_batches` does not exist yet.**
Not a bug — there is no foreign key, and the columns are nullable — but it is a promise the
schema is making that P3 has to keep. Either build that table in P3 or drop the columns.

### What to re-measure after the real import

Everything above is reasoning, not measurement: at 8 demo members `EXPLAIN` shows sequential
scans for every query and would for any schema. Once the 217 members and ~3,516 attendance
rows land, re-check exactly two things:

1. `EXPLAIN ANALYZE` the quarterly report query — it is the only one that joins five tables
   and aggregates.
2. The roster query with an iCare filter, which uses `whereHas` (a subquery). If it ever
   matters, it becomes a join.

Neither is likely to need anything at this data size. A church of 217 members generating
~50 attendance rows a week produces roughly 2,600 rows a year; this schema would comfortably
serve a congregation a hundred times larger.

---

## 15. P3 — the real workbook import, done 2026-09-08

`ifgf:import:workbook <path>` — **dry run by default**, `--commit` to write.

### What landed

| | Imported | Independently verified against the file |
|---|---:|---|
| Members | 217 | 217 roster rows, all with a unique email |
| iCare groups | 21 | 23 distinct values − "Belum mengikuti" − one blank |
| Members with no iCare | 63 | 62 "Belum mengikuti" + 1 blank |
| Contact points | 608 | 217 email + 217 phone + 174 LINE |
| Calendar links | 217 | "Birthday ID" is 217/217 filled |
| Occurrences | 102 | 51 distinct grid dates × 2 branches |
| Attendance | 3,318 | 3,316 onsite + 3 online − 1 double-mark |
| Raw scan log | 3,516 | log row count |

Every figure was computed independently in Python from the workbook before the importer
ran, and matched. Coverage: **2025-09-28 → 2026-09-13**.

### Two new tables

`ifgf_import_batches` and `ifgf_import_conflicts` — which also keeps the promise the schema
had been making since §14, where six tables carried an `import_batch_id` pointing at a
table that did not exist.

### The four conflicts, none auto-corrected

| Where | Kind | What |
|---|---|---|
| `Daftar Jemaat!53` | `unparseable_date` | Birthday reads `5/11/0005`. Left null. |
| `Daftar Jemaat!83` | `duplicate_phone` | Normalises to a number another member already uses. |
| `Daftar Jemaat!166` | `duplicate_phone` | Same, different pair. |
| `Absen-TPE!213` | `coerced_type` | Marked Onsite **and** Online for 2026-08-02. Onsite kept. |

The two phone duplicates are precisely what the legacy `cleanPhoneNumber()` could never
find — it compared raw strings, so `0912…` and `+886912…` were two people. They are
**flagged, not merged**: deciding two records are one person is a pastoral judgement.

### Three things the file did that the plan had not anticipated

**1. `Kategori` is a formula, not a literal.** With `setReadDataOnly(true)` PhpSpreadsheet
returns the formula TEXT, so the first run produced 300+ bogus "unrecognised Kategori
[=IF(D14..." conflicts. Every cell read now goes through a helper that falls back to
`getOldCalculatedValue()` — the cached result, which is what openpyxl's `data_only=True`
had been showing all along.

**2. `getHighestColumn()` returns a LETTER.** Using it in a numeric loop produced
coordinates like `AAAA1`. `getHighestColumnIndex()` does not exist on `Worksheet` in this
version; `Coordinate::columnIndexFromString()` does.

**3. One member is marked both Onsite and Online in the same week.** The importer silently
let the later column win — exactly the quiet correction its own rules forbid — and the
symptom was the reported count disagreeing with the stored row count by exactly one
(3,319 vs 3,318). Onsite now wins and the conflict is recorded. A test asserts the two
counts agree, because that mismatch is what made the bug visible.

### 🔴 Demo data and imported data collided, once

The demo fixture deliberately uses the real branches and real Sunday dates so it looks like
a plausible church. That is what made it collide: a Sunday service at Taipei on 2026-09-06
is **one** occurrence — the unique index says so, correctly — so the import claimed the
demo row, flipped `is_demo` to false, and 29 invented attendance rows became part of the
real Q3 figures.

Caught by reconciling the report total against the imported count. Fixed twice over:

- `DemoDataService::seed()` now **refuses** when real members exist, naming the reason.
  `--force` is available for the one test that deliberately needs the mixing, which is the
  test proving the purge does not over-reach.
- The demo fixture was purged; the database now holds real data only.

### Credentials are issued separately, and on purpose

`ifgf:credentials:issue` gave all 217 members a QR. The importer does **not** do this:
importing preserves what the church already recorded, issuing creates something new, and
folding them together would mean a re-run of the import quietly minting fresh codes.
Idempotent — a member holding an active QR is left alone, so re-running never invalidates a
printed card.

🔴 **This is the cutover moment.** Every member's old QR is now dead by design — it was a
prefilled Google Form URL carrying their email, phone, name and iCare in readable text.
217 members need telling. That is a communication plan, not a deploy step.

### Verified after import

- Quarterly report sums to exactly 3,318 across 2025-Q4 … 2026-Q3.
- A real imported member scans, records once, and re-scanning adds no second row.
- Re-running the whole import reports `members_updated 217` rather than `members_created`.

### Testing

19 tests, against a **synthetic** workbook the test writes itself — the real file holds 217
people's names, emails, phones and birthdays and is neither committed nor depended upon.
The fixture reproduces every defect found in the real file: a formula `Kategori`, an
unparseable birthday, a cross-format duplicate phone, a "Belum mengikuti" member, merged
date headers, and the Onsite/Online double-mark.

**Full suite: 216 passed, 693 assertions, 0 failed.**

### 15b. Formula handling — hardened 2026-09-09

Follow-up to the owner's question: *"have you fixed the formula issue? it should return the
value of the formula."* It does, and checking properly turned up something worse.

**Confirmed working.** The imported category distribution matches the workbook exactly —
Adult 67, College 116, Teens and Youth 33, Kids 1, and **zero** members with no category.
216 of 217 birthdays and 200 of 217 Chinese names, both matching.

**But the dependency is far larger than "one column".** Scanning the file for formula cells:

| Sheet | Formula cells | Where |
|---|---:|---|
| `Daftar Jemaat` | 316 | the whole `Kategori` column |
| `Absen-TPE` | ~40,000 | date headers (an `=H6+7` chain) **and every attendance cell** |
| `Absen-ZL` | ~36,500 | same |

Essentially the entire import is reading formula results, not literals. It works only
because Excel/Sheets writes each answer into the file next to the formula.

**🔴 And the silent failure mode is not the one I guarded against.** I had assumed an
unevaluated formula reads as empty. It does not — PhpSpreadsheet writes **0**. So a
workbook re-saved by a tool that does not evaluate formulas would read every attendance
cell as `0`, which is *indistinguishable from "absent"*, and every category as unknown. The
import would complete, report thousands of rows, raise nothing, and be worthless. No
per-cell check can see this, because 0 is a legitimate value there.

What gives it away is the SHAPE of the result, so `assertResultIsPlausible()` now checks
two invariants after every run:

- members read, but **not one** resolved a category
- service dates found, but **not one** attendance mark

Either means the file's formulas were never evaluated, and says so with the fix
("re-save it in Excel or Google Sheets").

**A false positive, found and removed.** The first version of the per-cell check fired 100
times on rows `F219`–`F318` — the empty template rows below the 217 members, whose Kategori
formulas correctly have no cached value because the formula returns "" for a blank row.
`distinctValues()` now scans only rows with a member in them.

**Test fixture made faithful.** Its attendance cells are now formulas with cached results,
matching the real grids, and it can be written with pre-calculation off to reproduce an
unevaluated workbook. Both shape checks are exercised; a healthy workbook raises neither.

Final state: **217 members, 3,318 attendance rows, 4 conflicts** — unchanged by any of this,
which is the point. **218 tests, 700 assertions, 0 failed.**

---

## 16. Licensing and attribution — 2026-09-09

Owner intent: free for churches and ministries, developed collaboratively, **not for
commercial exploitation**.

### The constraint that shaped the choice

This repository is a **fork of ChurchCMS**, `churchcms/church-cms`, MIT-licensed by
**GegoSoft Technologies (OPC) Private Limited**. Checked before writing anything, because
you cannot license someone else's code.

MIT turned out to be the best case: it permits redistribution, modification and — crucially
— **sublicensing**, which is what makes relicensing the combined work lawful. Its single
obligation is that the copyright and permission notice travel with every copy. Had upstream
been a purchased CodeCanyon-style licence, none of this would have been possible.

### 🔴 AGPL-3.0-or-later, chosen over a non-commercial licence — deliberately

The owner's words were "not for commercial", which points at PolyForm Noncommercial. That
was raised, with the trade-off stated plainly, and **AGPL-3.0 was chosen instead**.

The tension is real and worth recording: **"open source" and "non-commercial" are mutually
exclusive by definition.** The Open Source Definition, clause 6, forbids discriminating
against any field of endeavour, commercial included. So a non-commercial licence is
*source-available*, not open source — no GitHub licence badge, a smaller pool of willing
contributors, and genuine ambiguity at the edges ("is a church paying a freelancer to host
it commercial?").

AGPL-3.0 achieves what actually mattered without any of that. Its **section 13, Remote
Network Interaction**, is the distinguishing clause: an ordinary copyleft licence only
triggers on *distribution*, and a hosted web app is never distributed, so the obligation
never fires. Section 13 closes that hole — run a **modified** version as a network service
and you must offer its source.

A company is therefore not forbidden from using this. It is required to give back. The
commercial route becomes contribution rather than extraction, which is the outcome the
owner was after.

### File layout

| File | Covers |
|---|---|
| `LICENSE` | **AGPL-3.0**, full verbatim text (34,523 bytes, fetched from gnu.org and verified — section 13 present, so it is genuinely AGPL and not GPL) |
| `LICENSE.upstream-MIT` | GegoSoft's MIT notice, **byte-identical** to the committed original (sha256 verified after normalising line endings) |
| `NOTICE.md` | Plain-English explanation of which licence covers which part, and a table of what a church may and may not do |

`LICENSE` now holds the AGPL rather than the MIT because it governs the combined work and is
what GitHub reads. The MIT notice is retained in a clearly named file and referenced from
`LICENSE`, `NOTICE.md`, `config/ifgf-sites.php` and the page footer. Removing it would
breach the one obligation MIT imposes.

### Applied

- **Footer on every IFGF page**: "© 2026 IFGF Taipei Zhongli", plus the MIT acknowledgement
  in all three languages. Driven by `config/ifgf-sites.copyright_holder`.
- **72 AGPL headers** on IFGF-authored PHP only. Verified by grep that **not one landed on
  an upstream-owned file** — stamping IFGF copyright on GegoSoft's code would be precisely
  the wrong thing to do.
- `composer.json`: root `MIT` → `AGPL-3.0-or-later`; the package's misleading
  `"license": "proprietary"` → the same, with IFGF named as author.
- `NOTICE.md` carries a contributor term: PRs are AGPL-3.0-or-later, **no copyright
  assignment** — contributors keep their own copyright.

**⚠ Not legal advice.** This is a developer's reading of two licences. Worth a lawyer's eye
before the project is promoted to other churches.

**218 tests, 700 assertions, 0 failed** after the change.
