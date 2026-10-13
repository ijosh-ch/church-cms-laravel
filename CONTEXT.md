# CONTEXT

> Current state only. Rewritten every session end. Hard cap: 1,500 tokens.
> History belongs in `MEMORY.md`. Next actions belong in `TODO.md`.

**Updated:** 2026-10-14 · **Branch:** `dev`, seven commits ahead of `origin/dev`, nothing
pushed · **Stage:** registration, Google sign-in, profile, birthday calendar, roles, admin member
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

**Numbers:** 275/275 tests on `ifgf_app_test`; 28 routes; `composer audit` and `npm audit` clean.
12 domain tables plus `sessions`, `cache`, `jobs`; `Member` is the auth model (no `users` table,
no passwords).

## What is built

- **Registration** — `/register` (page one, 9 fields) → `/register/details` (page two, 4 fields;
  skipped by a visitor) → `/me` with the QR, or `/register/done` for someone already registered
  **or already signed in** (an usher helping a newcomer stays the usher).
  `Services/MemberRegistrar`, `Services/QrIssuer` (256-bit token: hash + encrypted copy +
  8-character code), `Mail/WelcomeWithQr` (queued; carries nothing the registrant typed).
- **Sign-in** — `/sign-in` offers Google or "Register without Google". A Google-verified email
  that matches a member signs them in; an unknown one goes to `/register` with the email fixed.
  There is no email sign-in link yet. The remember cookie is set but Laravel ignores it, so
  sign-in lasts 120 idle minutes. With `IFGF_GOOGLE_BIRTHDAY=true` (off by default) the sign-in
  also asks for Google's birthday permission and `Services/GoogleBirthday` fills the birthday in
  for a new person; it needs the People API, and Google's verification before production.
- **Verified email is what carries access.** Registration signs a person in without proof, so
  `Member::hasAbility()` and `leads()` return false until `email_verified_at` is set. The first
  Google verification deletes that member's other sessions and replaces the remember token.
- **Roles** — Gates `scan`, `lead-team`, `admin` in `AppServiceProvider`, from team membership
  only. First admin: `php artisan ifgf:admin:grant <email>` (refuses an unverified email).
- **Admin** — `/admin/members`: search kept in the session, edit, status, teams and roles; the
  member page says when an email is not verified. Changes go to the `audit` log channel
  (`storage/logs/audit-*.log`, ids only).
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
  **Restyled:** `/sign-in`, `/register`. **Still old** (`stone-*`/`sky-*`, `x-form.field`,
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
- `wp1-salvage/` is an untracked duplicate of the WP1 files; the owner has not said to delete it.

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
