# CONTEXT

> Current state only. Rewritten every session end. Hard cap: 1,500 tokens.
> History belongs in `MEMORY.md`. Next actions belong in `TODO.md`.

**Updated:** 2026-10-29 · **Branch:** `dev`, ahead of `origin/dev`, nothing pushed; iCare
management uncommitted · **Stage:** iCare weekly attendance and iCare management (FR-9) are built; registration, Google sign-in, profile, birthday calendar, roles, admin member
management and the usher screens are built and reviewed once; the restyle to `design/DESIGN.md`
has its foundations and two pages. `TODO.md` lists what is not built yet.

---

## Where things stand

`PRD.md` 3.0 is the scope; `EXECUTION_PLAN.md` splits it into work packages; `STANDARDS.md` is
the quality bar (four separate databases, tests written with the code, a security and privacy
checklist at every gate). Deployment target: a self-hosted Ubuntu PC behind a Cloudflare Tunnel.
Nothing is deployed; the legacy Google Form and Apps Script are still the live system.

The tree is a clean Laravel 13.35 app. The GegoSoft fork survives only on
`feat/postgresql-multisite`.

**Numbers:** 387/387 tests on `ifgf_app_test`; 48 routes; `npm audit` clean, `composer audit`
reports `symfony/mime` CVE-2026-45070 (2026-10-28; `TODO.md` item 2).
16 domain tables plus `sessions`, `cache`, `jobs`; `Member` is the auth model (no `users` table,
no passwords).
**Data model:** the ERD and the class diagrams are in `README.md` → "Data model". `DATA_MODEL.md`
has the table inventory and the ranked findings of the 2026-10-21 review.

## What is built

- **Registration** — one page since 2026-10-21: full name, email, WhatsApp (country code first,
  `Enums/PhoneCountry` checks the mobile shape), birthday, branch, iCare, "come again?" (No →
  visitor). → `/me` with the QR, or `/register/done` for someone already registered or already
  signed in. A registered phone is refused (`Rules/NotRegistered`; a child under 13 may share a
  parent's). Chinese name, Line ID, domicile, occupation, education and school are asked later:
  `Member::needsProfile()` drives a card on `/me` and a note on `/profile`, and
  `ifgf:profile:remind` (daily 19:00 Taipei) sends one email seven days after registering
  (`members.profile_reminded_at`). `Services/MemberRegistrar`, `Services/QrIssuer` (256-bit
  token: hash + encrypted copy + 8-character code), `Mail/WelcomeWithQr` (queued; carries
  nothing the registrant typed).
- **Deployment** — not deployed. `docs/DEPLOY.md` is the procedure: only a Git tag travels,
  never development data; the guards it names are built in WP2S.
- **Sign-in** — `/sign-in` offers Google or "Register without Google". A Google-verified email
  that matches a member signs them in; an unknown one goes to `/register` with the email fixed.
  There is no email sign-in link yet. The remember cookie is set but Laravel ignores it, so
  sign-in lasts 120 idle minutes. With `IFGF_GOOGLE_BIRTHDAY=true` (off by default) the sign-in
  also asks for Google's birthday permission and `Services/GoogleBirthday` fills the birthday in
  for a new person; it needs the People API, and Google's verification before production.
- **Verified email is what carries access.** Registration signs a person in without proof, so
  `Member::hasAbility()` and `leads()` return false until `email_verified_at` is set. The first
  Google verification deletes that member's other sessions and replaces the remember token.
  A member who registered without Google verifies later from `/profile` with "Hubungkan dengan
  Google" (`POST auth/google/connect`; connect mode lives in the session, the callback is shared):
  only a Google account with the same email is accepted, and it is audited.
- **Roles** — Gates `scan`, `lead-team`, `admin` in `AppServiceProvider`, from team membership
  only; iCare pages use `CellGroupPolicy::lead` (current leader or co-leader, verified email, or admin). First admin: `php artisan ifgf:admin:grant <email>` (refuses an unverified email).
- **Admin** — `/admin/members`: search kept in the session, edit, status, teams and roles; the
  member page says when an email is not verified. Changes go to the `audit` log channel
  (`storage/logs/audit-*.log`, ids only).
- **iCare (FR-9, 2026-10-28)** — iCare groups are `cell_groups`, no longer `teams` (which now holds
  ministry divisions only). A member has one main iCare, `members.cell_group_id` (null = "Belum
  join iCare"), and `cell_group_active`; leaders and co-leaders are `cell_group_leaders` and may
  lead several. `/icare` → `/icare/{public_ref}/attendance`: photo first (or "Tanpa foto"),
  calendar date (no future), everyone onsite by default, online/absent need a reason, any number
  of guests, save at the bottom; re-saving a date updates it. `Services/AttendancePhotoStore`
  re-encodes any image to the best JPEG under 3 MB (≤ 4096 px, quality ≥ 80), no metadata, on the
  `photos` disk (`IFGF_PHOTO_ROOT`, here `storage/app/private/assets`) at
  `icare/{public_ref}/{date}.jpg`; `resources/js/icare-attendance.js` shrinks it in the browser
  first (HEIC is not readable: no Imagick). Server steps: `docs/DEPLOY.md` §8.
- **iCare management (2026-10-29)** — `CellGroupPolicy`: `lead` (leader or co-leader: attendance,
  active switch), `manage` (the group's leader or admin: Edit iCare), `create` (admin or any
  current leader); the leader change is the `admin` Gate. `/icare` lists the groups with
  Attendance / Edit iCare / New iCare (a co-leader of one group still goes straight to it).
  `/icare/create` — a leader's new group is led by them at once, an admin's has no leader.
  `/icare/{public_ref}` — information (name unique ignoring case, `Rules/GoogleMapsUrl`), leader
  (admin), co-leaders, members (add = move from another iCare, remove = no iCare, the status
  button). People search is a POST flashed to the session (`people_search`, 30/min), never a URL.
  `/admin/icare` — all groups, leader, co-leaders, active count, "Perlu leader". All changes go
  through `Services/CellGroupLeadership` and `Audit` (`recordGroup` for the group itself).
- **Own photo (2026-10-28)** — `/profile/photo`: optional, consent box required, JPEG ≤ 1 MB /
  1600 px at `members/{public_ref}.jpg` on the photos disk, only the member sees it, delete =
  consent withdrawn (audited). A card on `/me` offers it. Uploads (this and iCare attendance) are
  throttled to 10 a minute per person (`photo` limiter). `resources/js/photo-shrink.js` holds the
  browser-side shrink for both.
- **Language** — default locale `id` (`config/app.php`, `.env.example`, `phpunit.xml`);
  `lang/id.json` holds only the iCare page's strings. Other pages still show English keys.
- **Usher** — `/usher`: pick a branch → `/usher/scan` (camera through `jsqr` in
  `resources/js/scanner.js`, typed backup code, find by name) → `/usher/attendance` (one Sunday
  of one branch with the totals block). `Services/AttendanceRecorder` creates the day's Sunday
  Service on first use and keeps one row per member. Stored in UTC, shown in Asia/Taipei.
- **Profile and birthday calendar** — `/profile` edits every registration field except the
  email. `MemberObserver` queues `SyncBirthdayEvent` when the name, Chinese name, birthday or
  iCare changes; `Services/BirthdaySync` writes through `Contracts/CalendarGateway` (`fake` by
  default and always in tests; `google` is plain HTTP, no `google/apiclient`).
  `ifgf:calendar:sync [--prune [--commit]]` reconciles everyone. The title is
  `🎂 Name (iCare X)'s Birthday`, or `(no iCare)`; the description has no iCare line.
- **Design system** — `design/DESIGN.md` is the rule; its tokens are in `resources/css/app.css`
  (light theme only, placeholder blue). Components in `resources/views/components/`: `button`,
  `field`, `input`, `select`, `radio-cards`, `alert`, `card`, `badge`, `list` + `list.item`,
  `empty`, `steps`, `qr-card`, `icon`. `layouts/guest.blade.php` is the signed-out column.
  **Restyled:** `/sign-in`, `/register`, and the card on `/me`. **Still old** (`stone-*`/`sky-*`, `x-form.field`,
  `layouts/app.blade.php`): every other page. `resources/js/app.js` holds the button loading
  state, focus on the error summary and "Copy the code". All components in every state:
  `resources/views/design/components.blade.php`, rendered by hand (newest `MEMORY.md` entry).
- **Input rules** — every form validates in a FormRequest. Names pass `Rules/PersonName` (one
  line, no markup or links). Session rows are always encrypted (`config/session.php`).
- **Throttles** — registration and sign-in 20 a minute per address; scan, roster tick and
  roster search 120 a minute per usher; the typed code 10.

## This PC

Windows 11 · PHP 8.4 (`php` on PATH) · Node 22 · PostgreSQL 17 as a service. Credentials are
machine-wide in `~/.ifgf/postgres.env` (its database names still point at the fork and are
ignored). `ifgf_app` (development) and `ifgf_app_test` (tests) are owned by role `ifgf`;
`ifgf_cms` belongs to the fork — untouched. `psql` lives in `C:\Program Files\PostgreSQL\17\bin\`.

- Site: the `ifgf-dev` preview server, `http://localhost:8080` (Google's redirect needs 8080).
  Run `npm run build` once for the CSS; `public/build` is not committed.
- Calendar: driver `google`, the **dev** calendar named in `~/.ifgf/calendar.env`. Writes work;
  it was pruned on 2026-10-13 and holds the three development members. Jobs need
  `php artisan queue:work`.
- Google sign-in: the client JSON named in `~/.ifgf/google.env`.
- In `ifgf_app` nobody is an admin until the owner signs in with Google and runs
  `ifgf:admin:grant` (`TODO.md` item 2).
- The dev calendar's events keep the old title until `ifgf:calendar:sync` is run.
- PHP here has `upload_max_filesize = 2M`: a 3 MB iCare photo is refused until `php.ini` says
  `upload_max_filesize = 10M` and `post_max_size = 12M` (the owner's machine setting).

## Facts a session will need

- **Category** is a formula over occupation and education, not age (PRD FR-5).
- **Legacy branch rule:** a scan before 13:00 is Zhongli, after is Taipei — import only.
- **Quarterly review:** monthly = average of weekly, rounded up; quarterly = average of the three
  months, rounded down.
- **Workbook (4):** 233 members, 3,693 scans, 434 headcount rows (`WORKBOOK_INVENTORY.md` §6).
  It holds real member data: read from `Downloads`, never copied into the repository.

## Not built — known gaps in the security baseline

Security headers, trusted proxies, forced HTTPS (all WP2S); the email sign-in link; member
deletion/anonymise (PRD §5.14). Registration still reveals who is a member (owner's decision).

## Licensing

`LICENSE` = AGPL-3.0 (© 2026 IFGF Taipei Zhongli). `LICENSE.upstream-MIT` stays. New PHP files
carry `// SPDX-License-Identifier: AGPL-3.0-or-later`.
