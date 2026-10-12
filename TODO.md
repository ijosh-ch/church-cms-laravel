# TODO

> Item 1 is always the literal next action. Rewritten every session end.
> Hard cap: 1,500 tokens.

**Branch:** `dev` · **Updated:** 2026-10-12 · **Scope:** `PRD.md` 3.0 · **Plan:** `EXECUTION_PLAN.md`

---

## Now

1. **Start a new session** — this one is far past its useful size. Everything is committed;
   232/232 tests. Built 2026-10-13 at the owner's request: the three Gates, `ifgf:admin:grant`,
   the audit log, `/admin/members` (search, edit, status, teams and roles), `/usher` (branch,
   camera scan, typed code, find by name) and `/usher/attendance` with the totals block; sign-in
   is remembered on the phone and `/` opens the QR; the birthday event is now
   `🎂 {full name} ({Chinese name})'s Birthday` (no age in the calendar — owner's choice);
   `ifgf:calendar:sync --prune [--commit]` removes events that belong to no member. The dev
   calendar was pruned on the owner's word (22 legacy events from 2025) and now holds exactly
   the three development members.
2. **Owner, to try it:** `php artisan ifgf:admin:grant <your registered email>`, then open the
   menu. The camera scanner is **untested** — the preview pane blocks cameras; try
   `http://localhost:8080/usher/scan` in Chrome with a webcam. A phone needs HTTPS (WP2S).
   The made-up member `alicia.demo@example.com` is an IT admin in the development database.
3. **Run `/security-review`** — never run on anything. Rebuild the graph (`graphify` CLI has no
   build command; the `/graphify` skill is not in the desktop session).
4. **Still to build, in rough order of need:**
   - Email sign-in link (rest of WP2). Without it, someone who registered by hand can only sign
     in again with a Google account for the same email.
   - WP2S staging through Cloudflare: HTTPS is what lets a phone camera scan, and it is where
     "the usher screens on their own port or hostname" gets decided (`routes/web.php` keeps
     them in one group for that).
   - Usher: record for another event of today (FR-7 last line), headcount entry (FR-12).
   - Attendance: the members × weeks grid and CSV export (FR-11); attendance history on `/me`.
   - Admin: team CRUD (FR-14), member deletion/anonymise (PRD §5.14), households (FR-3).
   - iCare leader workspace (WP5), institutions search (WP6), imports (WP7), reports (WP8).
   - Locale switch and `lang/`; a poster page with the QR of the site address.
   - `Admin\MemberController@search`, `MemberTeamController@destroy` and the two roster searches
     validate inline; `STANDARDS.md` §3.2 asks for FormRequests.
## Next — one work package per session, in this order

- WP2 and the rest of WP3 are items 3 and 4 above
   - then **WP2S — staging through Cloudflare** (security headers, `ifgf:smoke`, deploy scripts)
3. WP3 — registration, QR, member pages, households
4. WP4 — Sunday attendance and the attendance table
5. WP5 — iCare leader workspace, change requests, teams admin
6. WP6 — birthday calendar and institution search
7. WP7 — legacy workbook import **and the live sheet sync** (FR-13, added 2026-10-04)
8. WP8 — quarterly review, headcount, LINE import
9. WP9 — hardening and production cutover

## Owner — can be done any time, each unblocks a work package

- **Before WP2:** screens from Google Stitch, exported to `design/stitch/` (`design/STITCH_PROMPT.md`).
  Without them WP2–WP5 build plain screens that can be restyled later.
  - `design/DESIGN.md` (draft 1, 2026-10-12) is the design system; the owner still decides its
    §11: brand colour and logo, dark theme now or later, the birthday calendar grid (needs a line
    in PRD FR-6), required marks. `design/RESTYLE_PROMPT.md` is the prompt for a restyle session.
    `EXECUTION_PLAN.md` rule 7 ("keep Stitch's classes") now disagrees with both and needs fixing.
- **Before WP2:** a Google OAuth client (id and secret into `~/.ifgf/`).
  - Done 2026-10-12 for development: `~/.ifgf/client-secret-dev.json` (web client, project
    `ifgf-site-dev`, redirect `http://localhost:8080/auth/google/callback`), and
    `~/.ifgf/google.env` sets `IFGF_GOOGLE_CLIENT_JSON` to its path. WP2 reads the client id and
    secret from that JSON in `config/services.php`. The owner's test Google account must be on
    the consent screen's test-user list.
- **Before WP2S:** SSH access to the self-hosted Ubuntu PC, and a staging hostname on the
  Cloudflare domain. The session writes the scripts; the owner runs them on the PC.
- **Before WP3:** choose a mail provider for sign-in links, the welcome QR and confirmations.
- **Before WP6:** a Google service account with write access to the birthday calendar; the
  Ministry of Education university CSV (data.gov.tw dataset 27929).
  - Dev key received 2026-10-12: `~/.ifgf/ifgf-bday-calendar-dev-.json` (project `ifgf-site-dev`,
    account `ifgf-calendar-dev@…`). `~/.ifgf/calendar.env` holds `IFGF_CALENDAR_ID` (the dev
    calendar "BIrthday (Development)") and `IFGF_CALENDAR_KEY`. Read access verified 2026-10-12;
    write access untested. It already holds 22 events — the owner has not said what they are.
    Still needed: the owner's yes on using a real dev calendar outside production (`CLAUDE.md`).
- **Before WP7:** a Google service account with **Viewer** access to the live spreadsheet only;
  its key and the spreadsheet id into `~/.ifgf/`. Confirm D11 (welcome email only at cutover) and
  D12 (sync in production only).
- **Before WP8:** two or three real LINE recaps, pasted in chat — never committed.
- **Any time:** answer the ten defaults in `PRD.md` §7 (D1–D10), and the WP1 decisions listed in
  the newest `MEMORY.md` entry. Silence means the default stands.
- **Any time:** fix `EXECUTION_PLAN.md` WP1 step 6 — `make:enum Foo --string`, not `Enums/Foo`.

## Carried over from the fork — still open

- The 4 unresolved legacy import conflicts (one unparseable birthday, two duplicate phones, one
  double-marked Sunday) are pastoral decisions. WP7 will list them again from the new workbook.
- Every member's old Google-Form QR dies at cutover and nobody has been told. Plan the message
  before WP9.

## Waiting on the owner's yes

- **Registration reveals membership.** A new registrant lands on `/me` with a QR; someone already
  registered lands on `/register/done`. PRD FR-1 asks for both ("show their QR" and "the same
  success page"); they cannot both hold. Decide which wins.
- **The privacy notice wording** on page one of the form is the session's draft.
- **`simplesoftwareio/simple-qrcode` 4.2** raises three PHP 8.4 deprecation notices (it and
  `bacon/bacon-qr-code` 2). It works; replacing it means naming another package.
- Delete `wp1-salvage/` and `custompackages/`?

- **Real member data in development.** On 2026-10-12 the owner said the development database may
  hold the current church member data. `STANDARDS.md` §1 and `CLAUDE.md` forbid exactly that, so
  both need an amendment first; nothing imports before WP7 anyway.
- **A real dev calendar outside production** (`CLAUDE.md` says the fake only).

## Do not

- Do not build on the fork; it is gone from `dev` and readable on `feat/postgresql-multisite`.
- Do not create or switch branches. Everything happens on `dev`.
- Do not touch the `ifgf_cms` database; the clean app uses `ifgf_app` and `ifgf_app_test`.
- Do not read a database name from `~/.ifgf/postgres.env` — it still names `ifgf_cms`.
