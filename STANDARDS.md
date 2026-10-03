# STANDARDS — the quality bar

**Date:** 2026-10-04 · **Owner:** Ian Joseph Chandra

> Applies to this app (`PRD.md` 3.0) **and to every future module in `ROADMAP.md`**. Read whole at
> the start of every session. Hard cap: 1,800 tokens. A work package is not finished until §5
> passes.

## 1. Environments and databases

Four environments, each with its own PostgreSQL database **and its own database role**.
For this app the prefix is `ifgf_app`; a future module uses its own prefix the same way.

| Environment | Runs on | Database | Holds |
|---|---|---|---|
| test | developer machine | `ifgf_app_test` | nothing — built and wiped by every test run |
| development | developer machine | `ifgf_app` | factory and seeder data |
| staging | the server, `staging.<domain>` through Cloudflare, behind Cloudflare Access | `ifgf_app_staging` | seeded demo data |
| production | the server, the public hostname through Cloudflare | `ifgf_app_prod` | real members |

- Each role owns exactly one database and is not a superuser. A development or staging credential
  cannot open production. PostgreSQL listens on localhost only.
- The test suite refuses to run unless the database name ends in `_test`. In production the app
  calls `DB::prohibitDestructiveCommands()`, so `migrate:fresh` and `db:wipe` cannot run there.
- **Production data never leaves production**, except as an encrypted backup. A bug is reproduced
  with factories, not with a copy of real members.
- The schema changes only through migrations, and the same migrations run in all four.
- Secrets are per environment and live outside the repository.

## 2. Tests — written in the same session as the code

**Level 1 — automated, every session, on PostgreSQL**
- A unit test for every rule: formulas, normalisers, matching, rounding.
- A feature test for every requirement in the PRD.
- **Three access tests for every route that shows or changes data:** signed out; signed in
  without the right; signed in but asking for someone else's record.
- `Security/RouteAuditTest` walks the route table and fails if any route outside the PRD's public
  list has no `auth` middleware — a new unprotected route cannot slip in.
- No test touches the network: mail, calendar, Google sign-in and every outside service are faked.

**Level 2 — local run-through, every work package that adds a screen.** Start the app locally,
walk the new flow at phone width (375 px) and at desktop width, and read the browser console and
the server log for errors.

**Level 3 — through Cloudflare, once staging exists and before every production deploy.**
`php artisan ifgf:smoke <url>` checks the deployed site from the outside:
plain HTTP redirects to HTTPS · security headers are present · session cookies are Secure,
HttpOnly and SameSite · a signed-out request to a member page goes to sign-in · `/admin` is
refused · a forced error shows no stack trace · `/.env`, `/storage` and `/vendor` are not served ·
throttling counts the real visitor, not Cloudflare's address. The same command runs against
`http://localhost` for the checks that apply there.

## 3. Security checklist — against the session's changes

1. Every new route is behind `auth` and a Gate or Policy, or is on the PRD's public list.
2. Input is validated in a FormRequest; models list `$fillable` explicitly.
3. Output is escaped — `{{ }}` only. No raw SQL built from input.
4. Public POST routes are throttled; guessable codes are throttled hardest.
5. No sequential id in a URL, QR code, file path or email link — `public_ref` or a random token.
6. One-time links are single use, expire, and only their hash is stored.
7. Uploads: type checked by content, size-limited, re-encoded, stored outside the public folder.
8. No secret, token or credential in the repository, a log line or an error page.
9. Changes of role or team membership, imports and deletions are written to an audit log.
10. `composer audit` and `npm audit` are clean; no package was added that the plan did not name.

## 4. Privacy checklist

1. Collect only what the PRD lists; every form says what is collected and why.
2. A page shows the least needed: no birth year, no contact details, unless the viewer's role
   requires it.
3. No personal data in a URL, a log line, a queue name, a file name or a test fixture.
4. A member can see and correct their own data; an IT admin can delete a member on request,
   which anonymises the row and removes their QR and calendar event.
5. Sensitive uses — faces, giving, pastoral notes — need their own explicit consent, separate
   from registration, and can be withdrawn.
6. Backups are encrypted; a restore is rehearsed.

## 5. Gate — in this order

1. The full test suite is green on `ifgf_app_test`.
2. `composer audit` and `npm audit` are clean.
3. §3 and §4 are checked against the session's changes, item by item.
4. `/security-review` is run on the pending changes; findings are fixed or reported to the owner.
5. Level 2, if the work package added a screen.
6. Level 3, if staging exists.
7. `TODO.md`, `CONTEXT.md`, `MEMORY.md` updated; commit message shown; wait for the owner's yes.
