# TODO

> Item 1 is always the literal next action. Rewritten every session end.
> Hard cap: 1,500 tokens.

**Branch:** `dev` · **Updated:** 2026-10-04 · **Scope:** `PRD.md` 3.0 · **Plan:** `EXECUTION_PLAN.md`

---

## Now

1. **WP1 — clean skeleton and schema.** `EXECUTION_PLAN.md` § WP1. Pre-condition: the planning
   documents of 2026-10-04 are committed on `dev` and `git status --short` shows only
   `database/seeders/LaratrustSeeder.php`. Ask the owner whether that stub may be deleted.

## Next — one work package per session, in this order

2. WP2 — sign-in (email link, Google), gates, app shell
   - then **WP2S — staging through Cloudflare** (security headers, `ifgf:smoke`, deploy scripts)
3. WP3 — registration, QR, member pages, households
4. WP4 — Sunday attendance and the attendance table
5. WP5 — iCare leader workspace, change requests, teams admin
6. WP6 — birthday calendar and institution search
7. WP7 — legacy workbook import
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
- **Before WP8:** two or three real LINE recaps, pasted in chat — never committed.
- **Any time:** answer the ten defaults in `PRD.md` §7 (D1–D10). Silence means the default stands.

## Carried over from the fork — still open

- The 4 unresolved legacy import conflicts (one unparseable birthday, two duplicate phones, one
  double-marked Sunday) are pastoral decisions. WP7 will list them again from the new workbook.
- Every member's old Google-Form QR dies at cutover and nobody has been told. Plan the message
  before WP9.

## Do not

- Do not build on, or fix, the GegoSoft tree. WP1 replaces it.
- Do not create or switch branches. Everything happens on `dev`.
- Do not touch the `ifgf_cms` database; the clean app uses `ifgf_app` and `ifgf_app_test`.
