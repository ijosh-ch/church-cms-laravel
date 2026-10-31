# PRD — IFGF Taipei–Zhongli Member & Attendance System

**Version:** 3.0 · **Date:** 2026-10-04 · **Owner:** Ian Joseph Chandra · **Branch:** `dev`
**Replaces:** PRD 2.5 (2026-08-08) — still readable with `git show feat/postgresql-multisite:PRD.md`.
**Amended:** 2026-10-04 — FR-13 gains the live sheet sync (owner-authorised; D11, D12).
**Amended:** 2026-10-28 — iCare groups leave `teams` for their own tables (cell groups); FR-9
rewritten: onsite / online / absent with a reason, several guests, a meeting photo (owner).
FR-9 gains iCare management: the admin's list, New iCare and Edit iCare (owner).

> This file is the scope. Read it whole (hard cap 7,000 tokens). Anything not written here is
> **out of scope** until the owner adds it. Build order and session budgets: `EXECUTION_PLAN.md`.
> Evidence: `GAS_INVENTORY.md`, `WORKBOOK_INVENTORY.md`, the live registration form (read
> 2026-10-04), and `Jemaat & Absensi (4).xlsx` (never committed).

---

## 1. Goal and scope

One Laravel app replaces the Google Form, the Apps Script and the workbook, and adds weekly iCare
attendance.

**Decision 2026-10-04 — clean app.** `dev` becomes a fresh Laravel 13 application that holds only
IFGF code. The GegoSoft ChurchCMS fork is retired from `dev`: none of the features below use it,
and every critical finding of the 2026-09-24 security review sits inside it. The old tree stays
readable on `feat/postgresql-multisite` as a reference.

**In scope (v1)**

1. Registration that matches the Google Form, plus households (FR-1, FR-3)
2. Sign-in by email link and by Google; member self-service (FR-2)
3. School / university search (FR-4)
4. Member category from the workbook formula (FR-5)
5. Birthday → Google Calendar, and birthday tables (FR-6)
6. Sunday attendance by QR scan, short code or roster tick; usher picks the branch (FR-7)
7. Events of any kind — weekly or annual (FR-8)
8. Weekly iCare attendance: onsite, online or absent, guests, any past date, a photo (FR-9)
9. iCare-leader edits confirmed by the member through email (FR-10)
10. Attendance table and category counts (FR-11); quarterly review (FR-12)
11. Imports: the legacy workbook, and LINE recaps (FR-13)
12. Teams: ministry divisions with leaders and members (FR-14); iCare groups have their own (FR-9)

**Out of scope** — website builder, donations, sermons, discipleship (CGSL) tracking, service
scheduling, NFC tags, face recognition on photos, ticketing, Zoom import, Google Forms ingestion, a
mobile API, Apple sign-in, multi-church. Face recognition, photo and camera attendance, the event
photo finder, analytics, offerings and asset management (Snipe-IT) are future modules, drafted in `ROADMAP.md`; v1 only guarantees the stable keys they
will attach to (`members.public_ref`, `cell_groups.public_ref`, `teams.id`). **`ROADMAP.md` is not scope.**

---

## 2. People and permissions

Everyone is a `Member`. There is no separate user table and there are no passwords. What a member
may do comes from team membership — nothing else grants access.

| Who | How | May do |
|---|---|---|
| Member | registers | own profile, QR, attendance history, own household |
| Usher | belongs to a team with `ability = scan` | record attendance at a branch they pick |
| iCare leader | `leader` or `co_leader` in `cell_group_leaders` | FR-9, FR-10, FR-6 table — for the iCare groups they lead |
| Ministry team leader | `leader` in a ministry team | see that team's roster |
| IT admin | belongs to a team with `ability = admin` | everything: teams, events, imports, reports |

Deny by default. Three Gates carry all authorisation: `scan`, `lead-team` (takes the team),
`admin`; iCare pages add `CellGroupPolicy` (a current leader or co-leader of that group).
`admin` implies all of them. Only an IT admin changes team membership or roles.
The first IT admin is created by an artisan command, never through a web form.

---

## 3. Functional requirements

### FR-1 Registration — the basics first

Public form, always open, throttled. **One page with only the basics, so a newcomer is done in a
minute; the rest of the profile is asked for afterwards** (owner, 2026-10-21 — this replaces the
two-page parity with the Google Form). The same 13 fields are kept; only *when* they are asked
changes.

| # | Form label | Column | On the form | Notes |
|---|---|---|---|---|
| 1 | Full Name (passport / ARC) | `full_name` | yes, required | |
| 2 | Email | `email` | yes, required | lower-cased, unique |
| 3 | WhatsApp Number | `phone` | yes, required | stored E.164; the country code is chosen first (`phone_country`, default +886) and the number must have that country's mobile shape (owner, 2026-10-21) |
| 4 | Tanggal Lahir | `birthday` | yes, required | |
| 5 | Domisili Gereja IFGF | `branch_id` | yes, required | Taipei / Zhongli |
| 6 | iCare | `cell_group_id` | yes, required | active iCare groups, or "Belum join iCare" = null |
| 7 | Plan to attend again? | `status` | yes, required | **No → `visitor`**, Yes → `active` |
| 8 | Chinese Name | `chinese_name` | profile | optional |
| 9 | Line ID | `line_id` | profile | optional |
| 10 | Domisili Taiwan | `domicile_taiwan` | profile | free text, e.g. "Taipei - Da'an" |
| 11 | Profesi saat ini | `occupation` | profile | `worker`, `student`, `homemaker`, or typed text |
| 12 | Tingkat Pendidikan | `education_level` | profile | optional; `sd smp sma diploma s1 s2 s3` |
| 13 | Sekolah / Kampus / Perusahaan | `school_or_company` | profile | typeahead, FR-4 |

**The rest of the profile.** A member who plans to come again and has not filled in 10, 11 and 13
sees "Complete your profile" on their QR page, and the profile page says what is missing. Seven
days after registering they get **one** reminder email (`ifgf:profile:remind`, daily at 19:00
Taipei; `members.profile_reminded_at` keeps it to one). A visitor is never asked. Until occupation
is known, the category formula of FR-5 runs on what there is — the owner reviews the formula
against the workbook later.

**WhatsApp number and Line ID are unique** (owner, 2026-10-15): the form refuses a number, and the
profile a Line ID, that another member already has, and says so. The phone is compared normalised,
the Line ID ignoring case. A child under 13 may give a parent's number (they will be linked through
the household, FR-3). An IT admin's edit is not checked.

On submit: normalise the phone; look for an existing member by **email or phone**.
- Found → change nothing, email that member a sign-in link, show the same success page (no
  "already registered" message — that would reveal who is a member).
- New → create the member, compute the category (FR-5), issue a QR (FR-7), queue the welcome email
  with the QR, queue the birthday event (FR-6), sign the registrant in, show their QR.

*Done when:* the seven fields, the visitor answer, the duplicate rule, the profile card and the
one reminder are each covered by a test.

### FR-2 Sign-in and member self-service

- **Email link:** enter an email → the answer is always the same → if a member has that email, mail
  a one-time link (15 minutes, single use, only its hash stored).
- **Google:** Socialite; accept only a Google-verified email and match it to `members.email`. No
  match → the registration form with the email filled in.
  With `IFGF_GOOGLE_BIRTHDAY=true` the sign-in also asks for Google's birthday permission and,
  for a new person only, fills the birthday in — still editable, and the form still asks when
  Google has none, has no year, or the person says no (owner, 2026-10-14). The scope is
  sensitive: Google must verify the app before it is switched on in production.
- **Connect with Google** (owner, 2026-10-28): a signed-in member whose email is not verified yet
  links Google from the profile ("Hubungkan dengan Google"); accepted only when the Google email
  equals theirs (case-insensitive), then verified exactly as a Google sign-in; any other changes nothing.
- Either way sets `email_verified_at`. Sessions in the database, secure cookies.
- Signed in, a member sees their QR, their attendance history, and edits their own profile —
  every FR-1 field except email. A change to name, birthday or iCare re-syncs the calendar (FR-6).
- **Own photo, optional** (owner, 2026-10-28): `/profile/photo`, offered by a card on `/me` until
  there is one. Kept only with a ticked consent (`photo_consent_at`), as a JPEG of at most 1 MB
  and 1600 px on the photos disk (`members/{public_ref}.jpg`); only the member sees it for now.
  Deleting it withdraws the consent. Both are audited. Later used to recognise faces in FR-9
  meeting photos — who else may then see it is decided with that feature.

### FR-3 Households

`households` groups members who are family. `members.household_role` is `head`, `spouse`, `child`
or `other`.
- **At registration:** repeatable "add family member" rows — full name, birthday, relationship;
  optional Chinese name, education level, email, phone. They join the registrant's household and
  branch. Each gets their own QR.
- **Afterwards:** an IT admin or iCare leader links existing members into a household; a member
  adds dependants from their profile.
- A family member without an email cannot sign in. Adults of the same household see and present
  that person's QR.

### FR-4 School / university search

`institutions` is a local reference table; registration never calls an outside API.
- Taiwan: `ifgf:institutions:import <csv>` loads the Ministry of Education open data (data.gov.tw
  dataset 27929 — Chinese and English names). Run by hand, at most yearly.
- Indonesia: no official public API exists (PDDikti has only community wrappers). **Later**, and
  only if needed — form field 12 asks for the place of study or work *in Taiwan*.
- The typeahead searches English and Chinese names. A pick stores `institution_id` and the name;
  free text (a company, an unlisted school) stores the name only.

### FR-5 Member category

Stored in `members.category`, recomputed whenever occupation or education changes. First match
wins — this is the workbook's `Kategori` formula, unchanged:

1. education `sd` → **Kids**
2. `student` and education `smp` or `sma` → **Teens and Youth**
3. `student` and education `s1` or `diploma` → **College**
4. otherwise → **Adult**

One addition for family members who have no education level: age under 13 → Kids.

### FR-6 Birthdays

- **Calendar sync (legacy behaviour kept).** One all-day event per member in the church birthday
  calendar, repeating yearly until birth year + 99, reminders at 1 week and 1 day.
  Title `🎂 {full_name} (iCare {name})'s Birthday`, or `(Belum join iCare)` for a member who has not
  joined one. Description, one per line: `English Name:`, `Chinese Name:`, `Year of Birth:`
  (owner, 2026-10-14). No age in the calendar: it would have to be rewritten every
  year; the app can show it later. The event id lives in `members.calendar_event_id`.
  `ifgf:calendar:sync --prune` counts the events that belong to no member and, with `--commit`,
  removes them — never on the live calendar before the import has adopted the legacy event ids. A change to birthday,
  name or iCare updates the same event; if that fails, delete and recreate.
  `ifgf:calendar:sync` reconciles everyone. A `CalendarGateway` contract with a fake keeps tests
  offline; the real driver uses a service account kept in `~/.ifgf/`.
- **Birthday table.** Month picker; columns name, iCare, day and month (the year is not shown).
  An iCare leader sees their own group. An IT admin sees the whole church. Nobody else.
- A member maintains their own birthday in their profile.

### FR-7 Sunday attendance

- **QR:** an opaque random token (256 bits), no personal data, replaceable. Only its hash is
  searchable; an encrypted copy lets the member's page redraw it. An 8-character short code is the
  typed fallback. Every QR from the old Google-Form system stops working at cutover.
- **Usher flow:** sign in → pick the branch (remembered for the session) → today's Sunday Service
  for that branch opens (created on first use) → scan → the page shows name, iCare and "recorded"
  or "already recorded".
- **One row per member per occurrence.** Two sessions run each Sunday — Zhongli 10:00–12:00 and
  Taipei 15:00–17:00. A scan belongs to the session the usher's device opened, not to the clock,
  so a volunteer who arrives early is counted in the right one and a second scan changes nothing.
- **Fallbacks:** type the short code (throttled hard — it is guessable), or search the roster and
  tick, with an onsite/online choice.
- Each row keeps who recorded it, when, how (`qr`, `code`, `manual`, `import`) and the member's
  category on that day.
- The usher may pick any other active event for today instead (FR-8).

### FR-8 Events

`events` describes what recurs; `occurrences` is one date of it; `attendances` hang off the
occurrence. Types: `sunday_service`, `worship_night`, `class`, `special`. Recurrence:
`weekly`, `annual`, `once`. An occurrence is created the first time someone records attendance
for that date — no scheduled job inserts weeks. Seeded: Sunday Service for each branch. iCare
meetings are not events: they have their own tables (FR-9). An IT admin adds the rest (Worship Night, a discipleship class,
Christmas, Passover).

**Later, not in this PRD (owner, 2026-10-21):** special events — created by the usher leader, with
registration, limited places and tickets — move to a separate app in its own repository, open only
to the usher leader and the IT admin (`ROADMAP.md` M8). Its check-ins come back here as attendances.

### FR-9 Weekly iCare attendance

An iCare group (`cell_groups`) has a name, a branch, a place (`location`, `gmaps_url`), a meeting
weekday, one leader and any number of co-leaders (`cell_group_leaders`, which keeps `left_at`).
A member has **one** main iCare (`members.cell_group_id`, null = "Belum join iCare"); only leaders
and co-leaders may lead several. Owner, 2026-10-28.

For the group's leader or co-leader, and the IT admin (`/icare/{public_ref}/attendance`):
1. **Photo first.** One photo of the meeting, or tick "Tanpa foto"; without either, saving is
   refused. The browser turns any image it can open into the best JPEG under 3 MB (long side up
   to 4096 px, quality at least 80); the server decodes it again, re-encodes it the same way and
   drops its metadata. Stored outside the code at `{IFGF_PHOTO_ROOT}/icare/{public_ref}/{date}.jpg`,
   readable only by the web-server user; kept until deleted. Only the group's leaders and the IT
   admin see or delete it. Face recognition is future work (`ROADMAP.md`) and needs
   each member's own consent first (`STANDARDS.md` §4.5).
2. **Date** from a calendar picker; any past date, never a future one. A saved date opens with
   its values and re-saving updates it (`unique(cell_group_id, met_on)`).
3. **Members:** every active member of the group starts as **onsite**. Untick = **absent**; a
   switch makes it **online**. Online and absent each need a short reason.
4. **Guests:** any number, by name (`guest_name`), onsite or online.
5. Save once, at the bottom. The count reads "9 of 14 came — 7 onsite, 2 online".

Set a member **active** or **inactive** in the group (`members.cell_group_active`) — applied at
once, and logged. Inactive members are not listed or counted.

**Managing iCare groups** (owner, 2026-10-28). Every change below is audited (ids only).
- `/admin/icare` (IT admin): every group with its leader, co-leaders, active-member count and
  status. Only the admin assigns or changes a group's leader; the previous leader's row gets
  `left_at` and is kept. The admin also sets a group active or inactive.
- **New iCare** (`/icare/create`) — the IT admin or the **leader** of an active iCare: name, branch,
  place, Google Maps link, meeting day; a random `public_ref`. A leader who creates one leads it
  at once; one created by the admin has no leader until the admin assigns one.
- **Edit iCare** (`/icare/{public_ref}`) — the group's current leader and the IT admin: rename,
  place, Maps link (Google Maps addresses only), meeting day; add or remove co-leaders; add
  members, remove them (their main iCare becomes empty) and set them active or inactive.
  Adding someone whose main iCare is another group moves them. People are found by name; a
  search shows name, branch and main iCare only, and the typed name never reaches a URL. Co-leaders save
  attendance and set members active or inactive, but never appoint co-leaders.

### FR-10 Leader-proposed changes

A leader may propose new values for a group member's name, Chinese name, birthday, phone, Line ID,
Taiwan address, occupation, education, school/company, branch and iCare. Never the email.
- Saving creates a `change_requests` row and emails the member the old and new values with
  **Confirm** and **Reject** links (single use, 7 days). A member without an email: the mail goes
  to the head of their household.
- Confirm applies the change, recomputes the category and re-syncs the calendar. Reject or expiry
  changes nothing. The leader sees the state of every request.

### FR-11 Attendance table and counts

- **By date and branch:** who attended, at what time, iCare, category, onsite/online. Above it the
  workbook's totals block: Adult, College, Teens and Youth, Kids, Sub-total.
- **Grid:** members × weeks for a chosen range (default the last 8 weeks), each cell the scan time
  — the `Absen-TPE` / `Absen-ZL` view. CSV export.
- Ushers and IT admins see it; an iCare leader sees their own group's rows.

### FR-12 Quarterly review

Per branch and year, per category plus Sub-total — the workbook's arithmetic, unchanged:
- **weekly** = people recorded onsite that Sunday
- **monthly** = average of that month's weekly figures, **rounded up**
- **quarterly** = average of the three monthly figures, **rounded down**

Two sources, shown side by side: scanned attendance, and the manual **headcount** — the usher's
count by category, including people who are not registered; it is what the 2022–2026 year sheets
hold. Ushers and IT admins enter one headcount per occurrence. Weeks with no data count as zero
(as the sheet does) and the table says how many such weeks there were.

### FR-13 Imports

All three are dry-run first, repeatable without duplicates, and leave an `import_batches` row.
- **Legacy workbook** — `ifgf:import:workbook <path> [--commit]`. Members from `Daftar Jemaat`
  (with the legacy Birthday ID as `calendar_event_id`); attendance from `Daftar Absensi`, where the
  branch comes from the scan time (**before 13:00 = Zhongli, after = Taipei** — the sheet's own
  rule) and `Online = Ya` means online; headcounts from the ten year sheets. Rows that cannot be
  resolved are listed, not guessed.
- **LINE recap** — a CSV upload by an iCare leader or IT admin. Columns `date, icare, name` for
  iCare attendance and `date, branch, adult, college, teens_youth, kids, online, total, reporter`
  for a Sunday headcount. Names are matched inside the group first, then church-wide; an unmatched
  name is shown for the uploader to map to a member or keep as a guest.
  `docs/LINE_IMPORT_PROMPT.md` is the prompt an AI chat uses to turn pasted LINE messages into
  that CSV.
- **Live sheet sync** *(added 2026-10-04)* — `ifgf:import:sheet [--commit]`. Until cutover some
  people keep using the old Google Form and QR, so the app pulls what it is missing from the live
  spreadsheet. The command downloads the spreadsheet as an .xlsx through the Google API —
  read-only, into a temporary file outside the repository — runs the **same** workbook importer
  on it, and deletes the file.
  - **Adds only:** members registered through the old form, scans in `Daftar Absensi`, headcount
    rows. A member is matched by email or normalised phone, a scan by member and occurrence, a
    headcount by occurrence. Where the app already has the record **the app wins**: nothing is
    changed, and the difference is listed in the batch summary.
  - A scan's branch and mode follow the workbook rule above (13:00; `Online = Ya`).
  - A member who arrives this way gets a QR, and their legacy Birthday ID becomes
    `calendar_event_id` — a second calendar event is never created for them. No welcome email
    before cutover (D11).
  - **Schedule:** hourly, behind a config flag that is off by default; production only (D12);
    switched off for good at cutover. A scheduled run refuses to start while the flag is off.
  - **Access and privacy:** a Google service account with Viewer access to that one spreadsheet
    and nothing else; its key and the spreadsheet id live in `~/.ifgf/`. A `SheetGateway`
    contract with a fake keeps tests offline. The `URLs Absensi` sheet is never read. Log lines
    and the batch summary carry row references and field names only — no personal data.

### FR-14 Teams

`teams` holds the ministry divisions: Usher, IT, Praise and Worship, Multimedia, Sound,
Publication, Documentation, and whatever the IT admin adds. iCare groups are not teams (FR-9);
their leaders are managed on the iCare pages, never through `member_team`.
`member_team` records who is in a team and whether as `leader` or `member`; a team may
have several leaders.
IT admins maintain rosters and roles. Leaving a team sets `left_at`; the row is kept, so who
served where, and when, stays answerable (`ROADMAP.md` M6).

---

## 4. Data model — 16 tables

```mermaid
erDiagram
  branches   ||--o{ members : "home branch"
  households ||--o{ members : groups
  institutions ||--o{ members : "studies/works at"
  cell_groups ||--o{ members : "main iCare of"
  cell_groups ||--o{ cell_group_leaders : "led by"
  members    ||--o{ cell_group_leaders : leads
  cell_groups ||--o{ cell_group_attendances : "meets on"
  cell_group_attendances ||--o{ cell_group_attendees : "one row per person"
  members    ||--o{ cell_group_attendees : attends
  teams      ||--o{ member_team : has
  members    ||--o{ member_team : "serves in"
  branches   ||--o{ events : at
  events     ||--o{ occurrences : "happens on"
  occurrences ||--o{ attendances : has
  members    ||--o{ attendances : attends
  occurrences ||--o| headcounts : "counted as"
  members    ||--o{ change_requests : about
```

```
branches        id, code (TPE|ZL), name, name_zh, gmaps_url, is_active
teams           id, name, branch_id?, ability? (scan|admin), is_active
cell_groups     id, public_ref (uuid), name, branch_id, location?, gmaps_url?, meeting_weekday,
                is_active
cell_group_leaders  id, cell_group_id, member_id, role (leader|co_leader), left_at?
                one current leader per group; unique(cell_group_id, member_id) while current
cell_group_attendances  id, cell_group_id, met_on, photo_path?, recorded_by?
                unique(cell_group_id, met_on); photo_path is null only when "Tanpa foto" was ticked
cell_group_attendees  id, attendance_id, member_id?, guest_name?, status (onsite|online|absent),
                reason? (required for online and absent)          unique(attendance_id, member_id)
member_team     id, member_id, team_id, role (leader|member), left_at?
                unique(member_id, team_id) among rows where left_at is null
households      id, name
institutions    id, country, code?, name_en, name_zh?               unique(country, code)
members         id, public_ref (uuid), household_id?, household_role?, branch_id, cell_group_id?,
                institution_id?, full_name, chinese_name?, email? (unique), phone?, line_id?,
                birthday?, domicile_taiwan?, occupation?, education_level?, school_or_company?,
                cell_group_active (default true), category, status (active|inactive|visitor),
                qr_token_hash? (unique), qr_token_enc?, short_code? (unique), photo_path?,
                photo_consent_at?,
                calendar_event_id?, calendar_synced_at?, profile_reminded_at?,
                email_verified_at?, remember_token,
                legacy_ref?, timestamps, deleted_at
events          id, type, name, branch_id?, recurrence, weekday?, is_active
occurrences     id, event_id, occurs_on, locked_at?                  unique(event_id, occurs_on)
attendances     id, occurrence_id, member_id?, guest_name?, mode (onsite|online),
                method (qr|code|manual|import), category?, recorded_by?, recorded_at
                unique(occurrence_id, member_id)
headcounts      id, occurrence_id (unique), adult, college, teens_youth, kids, online, total,
                reporter?, source (manual|line|import)
change_requests id, member_id, requested_by, changes (jsonb), token_hash, status, expires_at,
                decided_at?
import_batches  id, source, file_hash, mode, status, summary (jsonb), created_by?
```

Plus the framework's own `sessions`, `cache` and `jobs`. All tables have `timestamps`.

**Why this is the minimum.** v2 had 13 `ifgf_` tables beside 93 upstream ones. Folded in:
- `contact_points` → three columns on `members` (a member has one email, one phone, one Line ID)
- `member_credentials` → three columns (one live QR per member; replacing it overwrites)
- `calendar_links` → two columns (there is one calendar)
- `member_categories` → a PHP enum (four fixed values defined by a formula)
- `group_memberships` → `members.cell_group_id` (one main iCare; attendance records history)
- iCare groups were folded into `teams` until 2026-10-28; the owner split them out, because an
  iCare is a fellowship with weekly attendance and a ministry team is a place to serve
- `service_occurrences` → `events` + `occurrences` (so non-Sunday events need no schema change)
- `import_conflicts` → `import_batches.summary`; `form_ingestions`, `is_demo` → dropped

Values with a fixed list (`category`, `status`, `mode`, `method`, leader `role`, …) are short string
columns backed by PHP enums — no database enums, no lookup tables.

---

## 5. Security and privacy baseline

Member data is personal data under Taiwan's PDPA. These are requirements, each with a test.
How they are tested, and the rules for databases and environments, are in `STANDARDS.md`.

1. Every route needs a signed-in member except: registration, sign-in, the Google callback, and
   the confirm/reject links. No `routes/api.php` in v1.
2. No route creates an IT admin. No sequential id in any URL or QR — `public_ref` only.
3. A member page shows only the signed-in member and their household. No `?member=` links.
4. Blade output is escaped (`{{ }}`); `{!! !!}` is banned outside generated QR SVG.
5. Throttles on registration, sign-in link, scan and — hardest — short code.
6. Behind Cloudflare: trusted proxies set, HTTPS forced, secure same-site cookies, the visitor IP
   read from Cloudflare's header so throttles are per visitor.
7. `APP_DEBUG=false` in production; no debug bar, no installer, no API docs page.
8. PostgreSQL listens on localhost only; the app's role owns one database and is not a superuser.
9. Secrets live outside the repo (`~/.ifgf/`). No member data, workbook or credential is ever
   committed.
10. The registration form states what is collected and why; lists show the least needed (no birth
    year, no contact details in rosters unless the viewer leads that group).
11. Test, development, staging and production each have their own database and role; production
    data is never copied to another environment; destructive commands cannot run in production.
12. Security headers on every response (HSTS, no-sniff, frame, referrer and content policies).
13. Role and team changes, imports and deletions are written to an audit log.
14. An IT admin can delete a member on request: the row is anonymised, the QR and the calendar
    event are removed, attendance counts stay.

---

## 6. Technology

Laravel 13 · PHP 8.4 · PostgreSQL 17 (Eloquent, `pdo_pgsql`) · Blade + Tailwind + Alpine through
Vite · no SPA. Packages beyond the skeleton: `laravel/socialite`, `simplesoftwareio/simple-qrcode`,
`phpoffice/phpspreadsheet` (import only), a Google API client (FR-6 and the live sheet sync only).
Languages `id` (default), `en`, `zh_TW`. Every screen works on a phone first and on a desktop.
Mail and calendar jobs run on the database queue. Tests at three levels — automated on
PostgreSQL, a local run-through, and a smoke check through Cloudflare — as `STANDARDS.md` sets
out. Deployment target: a self-hosted Ubuntu PC behind a Cloudflare Tunnel, with a staging
hostname beside the production one.

---

## 7. Defaults I assumed — owner may overrule

| # | Default | Why it is a question |
|---|---|---|
| D1 | Headcount by category is kept (FR-12) | It is in the year sheets but was not in the 17 points |
| D2 | Registration is always open | The Apps Script opened the form Saturday, closed it Monday |
| D3 | IT admin also sees the church-wide birthday table | Owner said "only iCare leaders" |
| D4 | One website, areas `/`, `/usher`, `/icare`, `/admin` | v2 planned two hostnames |
| D5 | Quarterly review counts missing weeks as zero | Matches the sheet; lowers the average |
| D6 | Children with no education level and under 13 are Kids | The formula has no such case |
| D7 | No yearly "Goal" column in the quarterly review | It is in the sheet; needs a goals table |
| D8 | Ministry team leaders only view their roster | Could also let them edit it |
| D9 | Indonesian universities are not loaded | No official API; field 12 asks about Taiwan |
| D10 | Each PHP file carries a one-line SPDX licence tag, added by script | v2 typed a 17-line AGPL header into every file |
| D11 | A member pulled in by the live sheet sync gets the welcome email and new QR only at cutover | Mailing now would announce the new system early |
| D12 | The live sheet sync runs in production only | Staging keeps demo data (`STANDARDS.md` §1) |

Needed from the owner before the work package that uses it: a mail provider (WP3), a Google OAuth
client (WP2), SSH access to the Ubuntu PC and a staging hostname on Cloudflare (WP2S), a calendar
service account (WP6), a Viewer-only service account for the spreadsheet and its id (WP7), two or
three real LINE recaps (WP8).
