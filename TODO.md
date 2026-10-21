# TODO

> Item 1 is always the literal next action. Rewritten every session end.
> Hard cap: 1,500 tokens.

**Branch:** `dev` · **Updated:** 2026-10-14 · **Scope:** `PRD.md` 3.0 · **Plan:** `EXECUTION_PLAN.md`

---

## Now

1. **Next restyle session: `/register/details`.** Start it with `design/RESTYLE_PROMPT.md`; the
   audit and the foundations are done, so begin at step 3. Order after it: `/register/done`,
   `/me`, `/profile`, `/usher`, `/usher/scan` (its class names also live in `scanner.js`),
   `/usher/roster`, `/usher/attendance`, `/admin/members`, the member page, then
   `layouts/app.blade.php` (bottom bar on phones, sidebar on desktop). Showing "If other, please
   specify" only for "Other" needs a few lines of script, or Alpine — ask first. When the last
   page is done: delete `x-form.field` and the unused `welcome.blade.php`.
2. **Owner: get an admin back in development.** Sign in with Google at `http://localhost:8080`,
   then run `php artisan ifgf:admin:grant <that email>`. Until then no signed-in page can be
   opened in the browser pane, so their restyle cannot be checked by eye.
3. **Owner: try the Google birthday.** Enable the People API in the Cloud project, add the
   `user.birthday.read` scope to the consent screen, put `IFGF_GOOGLE_BIRTHDAY=true` in `.env`,
   and sign in with an unregistered Google account. Tested so far only against fakes.
4. **Owner: run `php artisan ifgf:calendar:sync`** so the dev calendar's events get the new
   title, `🎂 Name (iCare X)'s Birthday`.
5. **Owner: decide the remember cookie.** It has never signed anyone in (`Member` has no
   password, so Laravel ignores the cookie); sign-in lasts 120 idle minutes. One line turns on a
   400-day sign-in — for ushers and admins too. See the `MEMORY.md` entry of 2026-10-13.
6. **The email sign-in link** (rest of WP2). Without it, a person who registered by hand with a
   non-Google email can never verify it, so can never hold a role — and can never sign in again
   once the session ends. The first verification must end earlier sign-ins.
7. **Then WP2S — staging through Cloudflare.** Must include: trusted proxies and the visitor IP
   (behind the tunnel every visitor shares one address, so the registration throttle would lock
   everyone out), security headers (PRD §5.12), forced HTTPS, Secure cookies, `ifgf:smoke`.
   Once `dev` is pushed, run `/security-review`; it has not been run on the 2026-10-14 commit.
8. **Still to build, in rough order of need:**
   - Usher: another event of today (FR-7 last line), headcount entry (FR-12).
   - Attendance: members × weeks grid and CSV export (FR-11); attendance history on `/me`.
   - Admin: team CRUD (FR-14), member deletion/anonymise (PRD §5.14), households (FR-3).
   - iCare leader workspace (WP5), institutions search (WP6), imports (WP7), reports (WP8).
   - Locale switch and `lang/` (also the plain-language error wording of `DESIGN.md` §9); a
     poster page with the QR of the site address.
9. Rebuild the graph (`graphify` CLI has no build command; the skill is not in the desktop app).
10. The camera scanner is **untested** — the preview pane blocks cameras. Try
    `http://localhost:8080/usher/scan` in Chrome with a webcam; a phone needs HTTPS (WP2S).

## Owner — can be done any time, each unblocks a work package

- **Design — owner, 2026-10-13:** white and blue, the blue of the IFGF logo; modern, minimal,
  elegant. The app now uses the placeholder blue `#1D4ED8`; give the logo file and its hex in
  `design/` and the swap is one token in `resources/css/app.css` (`DESIGN.md` §11.1).
- **Design:** dark theme now or later (`DESIGN.md` §11.2). `EXECUTION_PLAN.md` rule 7 ("keep
  Stitch's classes") disagrees with `DESIGN.md` and needs fixing.
- **Before WP2S:** SSH access to the Ubuntu PC and a staging hostname on the Cloudflare domain.
- **Before production with the Google birthday on:** Google's verification of the OAuth app.
- **Before the sign-in link:** choose a mail provider.
- **Before WP6:** the Ministry of Education university CSV (data.gov.tw dataset 27929).
- **Before WP7:** a service account with **Viewer** access to the live spreadsheet only; confirm
  D11 and D12.
- **Before WP8:** two or three real LINE recaps, pasted in chat — never committed.
- **Any time:** the ten defaults in `PRD.md` §7; fix `EXECUTION_PLAN.md` WP1 step 6
  (`make:enum Foo --string`).

## Waiting on the owner's yes

- **Registration reveals membership** (review finding M2, left as it is on 2026-10-13). A new
  registrant lands on `/me`, an existing one on `/register/done`, so an email or a phone number
  can be tested, 20 tries a minute. PRD FR-1 asks for both behaviours; decide which wins.
- **The wording** of the privacy notice on page one, and of the two cards on `/sign-in`, is a
  session's draft.
- **Year of birth in the calendar description** (owner's choice, FR-6): everyone the calendar is
  shared with can read it, which `STANDARDS.md` §4.2 would not allow on a page.
- **Real member data in development.** `STANDARDS.md` §1 and `CLAUDE.md` forbid it; the `log`
  mailer also writes each welcome email, with name and backup code, into `laravel.log`.
- `simplesoftwareio/simple-qrcode` 4.2 raises three PHP 8.4 deprecation notices. It works.
- Delete `wp1-salvage/` (untracked duplicate of WP1) and `custompackages/`?
- **Database review (`DATA_MODEL.md` §8, 2026-10-21) — each needs a yes before a migration:**
  - *Must, before production:*
    - M1: make `occurrences.event_id`, `attendances.occurrence_id` and
      `headcounts.occurrence_id` `RESTRICT`, not `CASCADE`.
    - M2: define anonymise (§5.14) as one transaction that nulls the personal columns, clears the
      QR, calendar and sessions, ends team rows, deletes change requests, then soft-deletes.
    - M3: add `events.starts_on` and `ends_on` so `once` and `annual` events have a date (FR-7).
  - *Should:*
    - S1: add `headcounts.recorded_by` and `updated_by`.
    - S2: allow one active Sunday Service per branch.
    - S3: index `lower(trim(line_id))`.
    - S4: make the email unique index `lower(email)`.
    - S5: make `change_requests.requested_by` `SET NULL`.
    - S6: add enums for headcount, import and change-request fields.
    - S7: hide `short_code`.
    - S8: add CHECK constraints.
    - S9: add `public_ref` to teams, events and occurrences.
    - S10: index `icare_team_id`, `household_id` and `change_requests.member_id`.
  - *Decide:* N1, a child without their own email cannot use the public form.

## Carried over from the fork — still open

- The 4 unresolved legacy import conflicts are pastoral decisions; WP7 lists them again.
- Every member's old Google-Form QR dies at cutover and nobody has been told. Plan the message
  before WP9.

## Do not

- Do not create or switch branches. Everything happens on `dev`. Never push to `main`.
- Do not touch `ifgf_cms`; the app uses `ifgf_app` and `ifgf_app_test`.
- Do not read a database name from `~/.ifgf/postgres.env` — it still names `ifgf_cms`.
- Do not give access to an unverified email, and do not put typed text into mail sent to one.
- Do not put a hex value or a `stone-*` / `sky-*` class in a restyled view: tokens only.
