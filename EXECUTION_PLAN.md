# Execution plan — PRD 3.0

**Date:** 2026-10-04 · **Branch:** `dev` only · **Model:** Opus 5.5, effort `high`
**Replaces:** the 2026-08 plan (`git show feat/postgresql-multisite:EXECUTION_PLAN.md`).

> Read only the section for the work package in `TODO.md` item 1, plus "Rules for every session".
> One work package (WP) per session. Ten WPs. Hard cap for this file: 6,000 tokens.
> The quality bar every WP must pass is `STANDARDS.md`.

---

## Rules for every session

1. **Branch.** `git branch --show-current` must print `dev`. If not, stop and ask. Never create,
   switch, rebase or delete a branch. `main` is the owner's release branch — never push to it.
2. **Generate first.** Run every `make:*` line of the WP before writing code, then fill the
   generated files in. Hand-write only what Artisan cannot scaffold (routes, lang lines, config).
3. **Old code is a reference, not a base.** Read it one file at a time with
   `git show feat/postgresql-multisite:<path> | sed -n 'A,Bp'`. Only the files the WP names.
4. **Reads.** `CLAUDE.md`, `CONTEXT.md`, `TODO.md`, this WP's section, the PRD sections it names.
   Nothing else unless the WP says so. Search with `graphify query` before `Grep`.
5. **Verify inline, quietly.** `php artisan test --compact 2>&1 | tail -40`. Run one test file
   while building (`--filter`), the whole suite once at the gate.
6. **Tests are written with the code, in the same session** (`STANDARDS.md` §2): a unit test per
   rule, a feature test per requirement, three access tests per route. A requirement without a
   test is not done. Every WP then passes the gate in `STANDARDS.md` §5 — suite, audits, the
   security and privacy checklists, `/security-review`, the local run-through, and the
   Cloudflare smoke check once WP2S has landed. The **Gate** line of each WP adds to that.
7. **Views.** If `design/stitch/` has a file for the screen, convert that HTML to Blade and keep
   its classes. If not, build on the layout and keep it plain. Never redesign.
8. **Stop at the gate or at 120k tokens, whichever is first.** Update `TODO.md`, `CONTEXT.md`,
   `MEMORY.md`; show the commit message; wait. Never stage or commit without the owner's yes.
9. **No scope growth.** Something missing from the PRD is a question for the owner, not a feature.

Budget per WP: about 100k tokens. If a WP will not fit, finish the sub-step that leaves the suite
green, hand off, and let `TODO.md` item 1 name the rest.

---

## WP1 — Clean skeleton and schema

**PRD:** §4, FR-5. **Old files:** `bootstrap/global-env.php`, `bootstrap/app.php` (first 30
lines), `config/database.php` L78–110, `custompackages/ifgf/church-operations/src/Services/PhoneNormalizer.php`,
`tests/Unit/Ifgf/PhoneNormalizerTest.php`.

1. Pre-flight: branch is `dev`; `git status --short` shows nothing but
   `database/seeders/LaratrustSeeder.php` (an untracked upstream stub — ask before deleting it).
2. Skeleton, outside the repo:
   `composer create-project laravel/laravel "$TEMP/ifgf-skel" "^13.0" --no-interaction`
3. Clear the tree. Keep: `CLAUDE.md CONTEXT.md MEMORY.md TODO.md PRD.md EXECUTION_PLAN.md
   ROADMAP.md STANDARDS.md SESSION_PROMPT.md GAS_INVENTORY.md WORKBOOK_INVENTORY.md LICENSE LICENSE.upstream-MIT NOTICE.md
   design/ .claude/`. `git rm -rq` everything else that is tracked. Then **list** what is left
   untracked or ignored (`git status --short --ignored`) and delete only `vendor/ node_modules/
   storage/ bootstrap/ public/ graphify-out/`. Rename the old `.env` to `.env.backup-fork`
   (ignored). Anything else unexpected → ask before deleting.
4. Copy the skeleton in (all but `.git`). Append to `.gitignore`: `/graphify-out`, `.env.backup-*`,
   `*.xlsx`, `/storage/app/imports`.
5. Carry over `bootstrap/global-env.php` and its `require` line; the `IFGF_PG_*` block in
   `config/database.php` with databases **`ifgf_app`** and **`ifgf_app_test`**. `.env`:
   `DB_CONNECTION=pgsql`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`,
   `MAIL_MAILER=log`. If the role cannot create the two databases, stop and give the owner the
   two `createdb` commands.
6. Generate, in this order (it is the foreign-key order):
   ```
   php artisan make:model Branch -mfs
   php artisan make:model Team -mfs
   php artisan make:model Household -mf
   php artisan make:model Institution -mf
   php artisan make:model Member -mf
   php artisan make:migration create_member_team_table --create=member_team
   php artisan make:model Event -mfs
   php artisan make:model Occurrence -mf
   php artisan make:model Attendance -mf
   php artisan make:model Headcount -mf
   php artisan make:model ChangeRequest -mf
   php artisan make:model ImportBatch -m
   for e in MemberCategory MemberStatus HouseholdRole EducationLevel TeamType TeamAbility \
            TeamRole EventType Recurrence AttendanceMode AttendanceMethod; do
     php artisan make:enum Enums/$e --string; done
   php artisan make:class Support/PhoneNormalizer
   php artisan make:test SchemaTest
   php artisan make:test MemberCategoryTest --unit
   php artisan make:test PhoneNormalizerTest --unit
   php artisan make:test Security/RouteAuditTest
   php artisan make:test Security/DatabaseGuardTest
   ```
7. Fill in: columns exactly as PRD §4; casts and relations; `Member` extends
   `Illuminate\Foundation\Auth\User` and becomes the auth provider model. Remove `User`, its
   factory, and the `users` / `password_reset_tokens` tables from the skeleton migration (keep
   `sessions`). `MemberCategory::for($occupation, $education, $birthday)` holds FR-5.
8. Seeders: two branches; iCare teams (the 27 names on the form — `GAS_INVENTORY.md` §7); ministry
   teams Usher (`scan`), IT (`admin`), Praise and Worship, Multimedia, Sound, Publication,
   Documentation; Sunday Service per branch; one weekly iCare event per iCare team.
9. `tests/TestCase.php`: `RefreshDatabase`, and `beforeRefreshingDatabase()` fails unless the
   database name ends in `_test`. `AppServiceProvider` calls
   `DB::prohibitDestructiveCommands($this->app->isProduction())`. `.env.example` documents the
   four environments of `STANDARDS.md` §1 with empty values. `RouteAuditTest` holds the public
   allow-list of PRD §5.1 and fails on any other route without `auth`.

**Gate:** environment, driver, host and database printed, then `migrate:fresh --seed` on
`ifgf_app` succeeds · 12 domain tables · `SchemaTest` (tables, unique indexes), `MemberCategoryTest`
(every branch of FR-5), `PhoneNormalizerTest` green · `route:list` shows only `/` and `up` ·
`composer audit` clean · `RouteAuditTest` and `DatabaseGuardTest` green · graph rebuilt with
`/graphify`.

## WP2 — Sign-in, gates, app shell

**PRD:** §2, FR-2, §5 items 1, 2, 5, 6. **Old files:** `resources/views/ifgf/layout.blade.php`,
`resources/lang/*/ifgf.php`, `app/Http/Controllers/Ifgf/LocaleController.php`.
**Owner input:** Google OAuth client id and secret (into `~/.ifgf/`).

```
composer require laravel/socialite
php artisan lang:publish
php artisan make:controller Auth/EmailLinkController
php artisan make:controller Auth/GoogleController
php artisan make:controller LocaleController --invokable
php artisan make:mail SignInLink --markdown=mail.sign-in-link
php artisan make:middleware SetLocale
php artisan make:command GrantAdmin --command=ifgf:admin:grant
php artisan make:view layouts.app && php artisan make:view auth.sign-in
php artisan make:test Auth/EmailLinkTest && php artisan make:test Auth/GoogleSignInTest
php artisan make:test Auth/GateTest && php artisan make:test Security/ProxyAndCookieTest
```
Build: one-time link (hash in cache, 15 min, single use, identical response for any email);
Google (verified email only, matched to `members.email`); Gates `scan`, `lead-team`, `admin` in
`AppServiceProvider`; trusted proxies for Cloudflare and forced HTTPS in production; locale switch;
responsive layout with role-based navigation; `npm i alpinejs`.

**Gate:** link reuse and expiry fail · unknown email gets the same answer · unverified Google
email rejected · gate matrix (member, usher, leader of A vs team B, admin) · only the public
routes of PRD §5.1 are reachable signed out.

## WP2S — Staging through Cloudflare

**Reads:** `STANDARDS.md` §1–2, PRD §5. Comes straight after WP2, so every later work package can
be tried on a real phone through Cloudflare, not only on the developer machine.
**Owner input:** the Ubuntu PC reachable over SSH, the domain already on Cloudflare, and a
hostname for staging (for example `staging.<domain>`).

```
php artisan make:command SmokeCheck --command=ifgf:smoke
php artisan make:middleware SecurityHeaders
php artisan make:test Security/SecurityHeadersTest
php artisan make:test Console/SmokeCheckTest
```
Build:
- `SecurityHeaders` middleware (HSTS, no-sniff, frame and referrer policy, a content security
  policy that fits the app's own scripts) and `ifgf:smoke <url>` with the checks of `STANDARDS.md`
  Level 3. Against a host behind Cloudflare Access it sends a service token read from `~/.ifgf/`.
- `deploy/` — scripts the owner runs on the Ubuntu PC, and `docs/DEPLOY.md` that explains them:
  nginx + PHP-FPM serving only `public/`; PostgreSQL on localhost with one role per environment;
  `cloudflared` as a service (a tunnel — no port is opened on the router, the home address stays
  hidden); `ufw` denying all inbound; SSH by key only; unattended security updates; queue worker
  and scheduler as systemd units that start on boot; a `deploy.sh` that pulls `dev`, installs
  without dev packages, migrates and restarts.
- Because it is a PC in a room, not a data centre: disk encryption, a nightly encrypted backup
  copied off the machine, a restore rehearsal, and an uptime check that notices when power or
  internet drops.
- Cloudflare side, written as a checklist for the owner: Access policy on the whole staging
  hostname (named testers only), WAF managed rules on, a rate-limit rule on the sign-in and
  registration paths, "Full (strict)" not needed with a tunnel but HTTPS-only enforced.

**Gate:** `SecurityHeadersTest` green · `ifgf:smoke http://localhost:8000` passes its local checks
· once the owner has run the scripts, `ifgf:smoke https://staging.<domain>` passes all of them ·
staging uses `ifgf_app_staging` with demo data only · `docs/DEPLOY.md` followed once, start to end.

## WP3 — Registration, QR, member pages, households

**PRD:** FR-1, FR-3, FR-5, FR-7 (QR part). **Old files:**
`app/Http/Controllers/MemberApp/RegistrationController.php`, `…/Services/MemberRegistryService.php`,
`…/Services/MemberCredentialService.php` L60–130 and L250–299, `resources/views/ifgf/member/register.blade.php`.
**Owner input:** mail provider.

```
composer require simplesoftwareio/simple-qrcode
php artisan make:controller RegistrationController
php artisan make:request RegisterMemberRequest
php artisan make:controller Member/HomeController
php artisan make:controller Member/ProfileController
php artisan make:request UpdateProfileRequest
php artisan make:controller Member/HouseholdController
php artisan make:class Services/MemberRegistrar
php artisan make:class Services/QrIssuer
php artisan make:mail WelcomeWithQr --markdown=mail.welcome
php artisan make:job SyncBirthdayEvent
php artisan make:observer MemberObserver --model=Member
php artisan make:test RegistrationTest && php artisan make:test HouseholdTest
php artisan make:test QrIssuerTest --unit && php artisan make:test Member/ProfileTest
```
Build: the two-step form with the visitor branch; family rows; duplicate rule (email or phone, no
disclosure); QR issue and replace; welcome mail; member home (QR, history, household); profile
edit; observer recomputes the category and dispatches `SyncBirthdayEvent` (fake gateway for now).

**Gate:** all 13 fields validated · visitor path stores `status=visitor` and skips step 2 ·
duplicate by email and by re-formatted phone · household of three gets three QRs · a member cannot
open another member's page or QR.

## WP4 — Sunday attendance and the attendance table

**PRD:** FR-7, FR-8, FR-11. **Old files:** `app/Http/Controllers/Usher/ScanController.php`,
`…/Usher/RosterController.php`, `…/Services/AttendanceRecorder.php`,
`resources/views/ifgf/usher/scan.blade.php`.

```
npm i jsqr
php artisan make:controller Usher/BranchController
php artisan make:controller Usher/ScanController
php artisan make:controller Usher/RosterController
php artisan make:controller AttendanceTableController
php artisan make:class Services/AttendanceRecorder
php artisan make:test Usher/ScanTest && php artisan make:test Usher/RosterTest
php artisan make:test AttendanceTableTest
```
**Gate:** scan records once and reports a repeat · short code throttled harder than scan · branch
choice decides the occurrence · occurrence created on first use · category snapshot stored · a
plain member gets 403 on every usher route · totals block matches hand-counted fixtures.

## WP5 — iCare leader workspace

**PRD:** FR-9, FR-10, FR-6 (group table), FR-14. **Rewritten 2026-10-28:** iCare groups leave
`teams` for `cell_groups` (owner). Do FR-9 first, end to end; ask before FR-10.

```
php artisan make:enum CellGroupRole --string
php artisan make:enum AttendeeStatus --string
php artisan make:model CellGroup -mfs
php artisan make:model CellGroupLeader -mf
php artisan make:model CellGroupAttendance -mf
php artisan make:model CellGroupAttendee -mf
php artisan make:migration move_icare_from_teams_to_cell_groups
php artisan make:policy CellGroupPolicy --model=CellGroup
php artisan make:controller CellGroup/AttendanceController
php artisan make:controller CellGroup/MemberStatusController --invokable
php artisan make:controller CellGroup/PhotoController
php artisan make:request CellGroup/SaveAttendanceRequest
php artisan make:class Services/CellGroupAttendanceRecorder
php artisan make:class Services/AttendancePhotoStore
php artisan make:view cell-group.attendance
php artisan make:test CellGroup/AttendanceTest && php artisan make:test CellGroup/AccessTest
php artisan make:test CellGroup/PhotoTest && php artisan make:test Services/AttendancePhotoStoreTest --unit
# then FR-10, FR-6 table, FR-14:
php artisan make:controller Icare/BirthdayController --invokable
php artisan make:controller ChangeRequestController
php artisan make:controller Admin/TeamController --model=Team --requests
php artisan make:mail ChangeRequestMail --markdown=mail.change-request
php artisan make:test Icare/ChangeRequestTest && php artisan make:test Icare/BirthdayTableTest
php artisan make:test Admin/TeamTest
```
**Gate:** photo or "Tanpa foto" required · default onsite, reason for online and absent · several
guests · re-save and a back-filled date update one row per date · a future date is refused ·
status toggle is immediate and logged · leader and co-leader of A get 403 on group B · the photo
is a JPEG ≤ 3 MB without metadata, on the `photos` disk outside the code, seen and deleted only by the group's leaders
and the admin · confirm applies, reject and expiry do not, a link works once · mail goes to the
household head when the member has no email · birthday table hides the year · leaving a team
sets `left_at` and keeps the row; rejoining adds a new one.

---

## WP6 — Birthday calendar and institutions

**PRD:** FR-4, FR-6. **Old files:** `…/src/Contracts/CalendarGateway.php`,
`…/src/Calendar/FakeCalendarGateway.php`, `…/src/Services/BirthdaySyncService.php`;
`calendar.js` L48–62 and L301–360 in the Apps Script repo.
**Owner input:** calendar service account JSON in `~/.ifgf/`; the MoE CSV.

```
composer require google/apiclient
php artisan make:interface Contracts/CalendarGateway
php artisan make:class Calendar/FakeCalendarGateway
php artisan make:class Calendar/GoogleCalendarGateway
php artisan make:command SyncCalendar --command=ifgf:calendar:sync
php artisan make:command ImportInstitutions --command=ifgf:institutions:import
php artisan make:controller InstitutionSearchController --invokable
php artisan make:controller Admin/BirthdayController --invokable
php artisan make:test Calendar/BirthdaySyncTest && php artisan make:test InstitutionSearchTest
```
**Gate:** create, in-place update, delete-and-recreate fallback and reconcile-all all pass against
the fake · tests can never reach the Google driver · search matches English and Chinese names and
is throttled. **The live calendar is not touched before WP9.**

## WP7 — Legacy workbook import and live sheet sync

**PRD:** FR-13, §5.9. **Old files:** `…/Services/WorkbookImporter.php` L120–260 (reading and type
coercion only — the stages are rewritten for the new schema), `WORKBOOK_INVENTORY.md`.
**Owner input:** a Viewer-only service account key and the spreadsheet id in `~/.ifgf/`; D11, D12.

```
composer require phpoffice/phpspreadsheet
php artisan make:command ImportWorkbook --command=ifgf:import:workbook
php artisan make:class Services/WorkbookImporter
php artisan make:test Import/WorkbookImportTest
php artisan make:interface Contracts/SheetGateway
php artisan make:class Sheets/FakeSheetGateway
php artisan make:class Sheets/GoogleSheetGateway
php artisan make:command ImportSheet --command=ifgf:import:sheet
php artisan make:test Import/SheetSyncTest
```
Tests build a tiny workbook in code; no real file enters the repo.

**Live sheet sync (added 2026-10-04) — built after the workbook import, in the same WP.**
- One importer. `WorkbookImporter` takes a path and a source (`workbook` | `sheet`) and loads
  only the sheets it names, so `URLs Absensi` is never parsed. No second importer.
- `ImportSheet`: `SheetGateway::download()` → a random file in the system temp directory →
  importer → `finally` delete. Dry run unless `--commit`. `--scheduled` refuses unless
  `config('ifgf.sheet_sync.enabled')`; `routes/console.php` schedules it hourly with
  `--commit --scheduled` and `withoutOverlapping`.
- `GoogleSheetGateway` exports through the Drive API with `google/apiclient` (already added in
  WP6), read-only scope, bound only in production. Every other environment, and every test,
  gets the fake. If the export passes Drive's 10 MB limit, stop and ask the owner.
- Existing member (email or normalised phone), scan (member + occurrence) or headcount
  (occurrence): change nothing; `summary` gets the sheet row and the names of the differing
  fields — never the values. Logs likewise.
- New member: QR from WP3's issuer; legacy Birthday ID → `calendar_event_id`; no calendar job
  and no mail are queued.
- Config keys in `config/ifgf.php`; `.env.example` names them with empty values.

**Gate:** dry run writes nothing · second `--commit` adds nothing · time rule puts 12:59 in
Zhongli and 13:01 in Taipei · unresolved rows listed in `summary` · on the real file the batch
summary is shown to the owner (workbook (4): 233 members, 3,693 scans, 434 headcount rows).
**Sheet sync adds:** a member edited in the app is not overwritten · a new form registrant
appears once, with a QR and the adopted calendar id · `--scheduled` refuses while the flag is
off · tests can never reach the Google driver · a `URLs Absensi` sheet in the fixture is never
loaded · `summary` and the log hold no name, email or phone · the temp file is gone afterwards,
also when the import throws. The session never runs it against the live spreadsheet — the owner
runs the first real dry run and reads the summary.

## WP8 — Reports, headcount, LINE import

**PRD:** FR-12, FR-13. **Old files:** `…/Services/QuarterlyReportService.php`.
**Owner input:** two or three real LINE recaps, pasted in chat, never committed.

```
php artisan make:class Services/QuarterlyReport
php artisan make:controller Reports/QuarterlyController --invokable
php artisan make:controller HeadcountController
php artisan make:request StoreHeadcountRequest
php artisan make:controller Imports/LineImportController
php artisan make:class Services/LineRecapImporter
php artisan make:test Reports/QuarterlyReportTest && php artisan make:test Imports/LineImportTest
```
**Gate:** monthly rounds up, quarterly rounds down, on fixtures copied from the sheet's formulas ·
both sources side by side · unmatched names are offered, not guessed · re-upload adds nothing ·
`docs/LINE_IMPORT_PROMPT.md` written and tried on the owner's samples.

## WP9 — Hardening and production cutover

**Reads:** `STANDARDS.md` whole, PRD §5 in full, `docs/DEPLOY.md`.

Build: a test for every PRD §5 and `STANDARDS.md` §3–4 item still untested, including member
deletion (anonymise the row, remove the QR and the calendar event) and the audit log; the
production half of `docs/DEPLOY.md` — its own database `ifgf_app_prod` and role, its own hostname,
Cloudflare Access on `/admin`; the cutover checklist — final import with `--commit`, adopt calendar
event ids, mail every member their new QR, switch off the Apps Script trigger, run the sheet sync
one last time and switch its flag and schedule off for good; a translation pass
for `id`, `en`, `zh_TW`.

**Gate:** every checklist item has a passing test · `composer audit` and `npm audit` clean ·
`ifgf:smoke` passes against staging **and** the production hostname · a backup restored once into
a scratch database · the staging role proven unable to open the production database. After this
the owner merges `dev` into `main`.
