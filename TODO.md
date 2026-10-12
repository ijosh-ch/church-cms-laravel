# TODO

> Item 1 is always the literal next action. Rewritten every session end.
> Hard cap: 1,500 tokens.

**Branch:** `dev` · **Updated:** 2026-10-12 · **Scope:** `PRD.md` 3.0 · **Plan:** `EXECUTION_PLAN.md`

---

## Now

1. **Commit WP1** — it is landed in the tree and uncommitted. Before the owner's yes: run
   `/security-review` on the pending changes (not run yet) and rebuild the graph (the `graphify`
   CLI has no build command; the `/graphify` skill is not installed in the desktop session).
   Stage by path — never `git add -A` — and leave out `wp1-salvage/`; delete that folder and the
   leftover `custompackages/` (one PHPUnit cache file) after the commit, with the owner's yes.
   State on 2026-10-12: Laravel 13.35.0 · 65/65 tests on `ifgf_app_test` · `migrate:fresh --seed`
   clean on `ifgf_app` · `route:list` shows only `/` and `up` · both audits clean.

## Next — one work package per session, in this order

2. WP2 — sign-in (email link, Google), gates, app shell
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
- **Before WP2:** a Google OAuth client (id and secret into `~/.ifgf/`).
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

- **Real member data in development.** On 2026-10-12 the owner said the development database may
  hold the current church member data. `STANDARDS.md` §1 and `CLAUDE.md` forbid exactly that, so
  both need an amendment first; nothing imports before WP7 anyway.
- **A real dev calendar outside production** (`CLAUDE.md` says the fake only).

## Do not

- Do not build on the fork; it is gone from `dev` and readable on `feat/postgresql-multisite`.
- Do not create or switch branches. Everything happens on `dev`.
- Do not touch the `ifgf_cms` database; the clean app uses `ifgf_app` and `ifgf_app_test`.
- Do not read a database name from `~/.ifgf/postgres.env` — it still names `ifgf_cms`.
