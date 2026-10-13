# TODO

> Item 1 is always the literal next action. Rewritten every session end.
> Hard cap: 1,500 tokens.

**Branch:** `dev` · **Updated:** 2026-10-13 · **Scope:** `PRD.md` 3.0 · **Plan:** `EXECUTION_PLAN.md`

---

## Now

1. **Owner: get an admin back in development.** Access now needs a verified email, and the one
   role holder in `ifgf_app` registered by hand. Sign in with Google at
   `http://localhost:8080`, then run `php artisan ifgf:admin:grant <that email>`.
2. **Owner: decide the remember cookie.** It has never signed anyone in (`Member` has no
   password, so Laravel ignores the cookie); sign-in lasts 120 idle minutes. One line turns on a
   400-day sign-in — for ushers and admins too. See the newest `MEMORY.md` entry.
3. **Next session: the email sign-in link** (rest of WP2). Without it, a person who registered by
   hand with a non-Google email can never verify it, so can never hold a role. The first
   verification must end earlier sign-ins, as the Google callback does.
4. **Then WP2S — staging through Cloudflare.** Must include: trusted proxies and the visitor IP
   (behind the tunnel every visitor shares one address, so the registration throttle would lock
   everyone out), security headers (PRD §5.12), forced HTTPS, Secure cookies, `ifgf:smoke`.
   Once `dev` is pushed, `/security-review` has a usable diff — run it then.
5. **Still to build, in rough order of need:**
   - Usher: another event of today (FR-7 last line), headcount entry (FR-12).
   - Attendance: members × weeks grid and CSV export (FR-11); attendance history on `/me`.
   - Admin: team CRUD (FR-14), member deletion/anonymise (PRD §5.14), households (FR-3).
   - iCare leader workspace (WP5), institutions search (WP6), imports (WP7), reports (WP8).
   - Locale switch and `lang/`; a poster page with the QR of the site address.
6. Rebuild the graph (`graphify` CLI has no build command; the skill is not in the desktop app).
7. The camera scanner is **untested** — the preview pane blocks cameras. Try
   `http://localhost:8080/usher/scan` in Chrome with a webcam; a phone needs HTTPS (WP2S).

## Owner — can be done any time, each unblocks a work package

- **Design:** `design/DESIGN.md` §11 (brand colour, logo, dark theme, required marks);
  `design/RESTYLE_PROMPT.md` is the prompt for a restyle session. `EXECUTION_PLAN.md` rule 7
  ("keep Stitch's classes") disagrees with both and needs fixing.
- **Before WP2S:** SSH access to the Ubuntu PC and a staging hostname on the Cloudflare domain.
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
- **The privacy notice wording** on page one of the form is a session's draft.
- **Year of birth in the calendar description** (owner's choice, FR-6): everyone the calendar is
  shared with can read it, which `STANDARDS.md` §4.2 would not allow on a page.
- **Real member data in development.** `STANDARDS.md` §1 and `CLAUDE.md` forbid it; the `log`
  mailer also writes each welcome email, with name and backup code, into `laravel.log`.
- `simplesoftwareio/simple-qrcode` 4.2 raises three PHP 8.4 deprecation notices. It works.
- Delete `wp1-salvage/` (untracked duplicate of WP1) and `custompackages/`?

## Carried over from the fork — still open

- The 4 unresolved legacy import conflicts are pastoral decisions; WP7 lists them again.
- Every member's old Google-Form QR dies at cutover and nobody has been told. Plan the message
  before WP9.

## Do not

- Do not create or switch branches. Everything happens on `dev`. Never push to `main`.
- Do not touch `ifgf_cms`; the app uses `ifgf_app` and `ifgf_app_test`.
- Do not read a database name from `~/.ifgf/postgres.env` — it still names `ifgf_cms`.
- Do not give access to an unverified email, and do not put typed text into mail sent to one.
