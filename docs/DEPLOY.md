# DEPLOY — how the app reaches staging and production

**Status:** procedure, 2026-10-21. Nothing is deployed yet. The scripts and the checks named in
§4 are built in WP2S (`EXECUTION_PLAN.md`); until then this file is the plan they follow.
**Applies to:** the self-hosted Ubuntu PC behind a Cloudflare Tunnel (PRD §6), and `STANDARDS.md` §1.

> **The one rule: code travels, data never does.** What reaches the server is a Git commit and
> nothing else. A database, a `.env`, a log, a build folder, a queue, an upload or a spreadsheet
> is never copied from a development machine to staging or production — not even "just once".

---

## 1. What travels, and what never does

| Travels to the server | Never leaves the developer machine |
|---|---|
| A commit of the repository, fetched by `git` on the server | `ifgf_app`, `ifgf_app_test` — any dump, backup or row of them |
| Migrations (the schema), run on the server | `.env`, `~/.ifgf/*` (dev database password, dev calendar, dev Google client) |
| Reference seeders: branches, teams, events | `storage/` — `laravel.log` holds every welcome email with name and backup code |
| Front-end built **on the server** (`npm ci && npm run build`) | `public/build/` (includes the hand-rendered `_design/` page) |
| | The `jobs`, `sessions` and `cache` tables — they live in the dev database |
| | Test members (`*@example.test`), the dev Google calendar's events |
| | The legacy workbook, LINE recaps, `wp1-salvage/`, `graphify-out/` |

Real member data enters production in exactly two ways: **people registering**, and the
**workbook import run on the server** (WP7) from a file that is copied straight to the server,
never through the repository or the developer machine's database.

## 2. Four environments, kept apart by design

| | test | development | staging | production |
|---|---|---|---|---|
| Runs on | dev PC | dev PC | server, `staging.<domain>` | server, the public hostname |
| Database | `ifgf_app_test` | `ifgf_app` | `ifgf_app_staging` | `ifgf_app_prod` |
| Database role | dev role | dev role | `ifgf_staging` | `ifgf_prod` |
| Holds | nothing (wiped each run) | made-up members | seeded demo data | real members |
| `APP_ENV` / `APP_DEBUG` | testing / – | local / true | staging / false | production / false |
| `APP_KEY` | phpunit | dev key | its own | **its own, generated on the server** |
| Mailer | array | log | log (or a sandbox) | the chosen provider |
| Calendar | fake | dev calendar | fake | the live birthday calendar |
| Google sign-in client | none | dev client, `localhost:8080` | staging client | production client |
| Who can open it | — | the owner | Cloudflare Access: named testers | everyone; `/admin` behind Access |

Why this keeps development data out even if a mistake is made:
- **Each role owns one database.** The production role cannot open `ifgf_app`, and no
  development credential exists on the server, so there is no connection to copy through.
- **A fresh `APP_KEY` in production** means anything encrypted with the dev key — QR tokens,
  session rows — is unreadable there even if it were copied.
- **Production starts empty:** migrations plus the reference seeders, never `db:seed --class`
  of anything that makes members, and never a restore of a dev backup.
- `DB::prohibitDestructiveCommands()` is on in production, so `migrate:fresh` and `db:wipe`
  cannot run there.

## 3. Branches and versions

- **Staging** runs `dev`. Every push to `dev` can go to staging.
- **Production** runs a **tag on `main`** (`v1.0.0`, `v1.0.1`, …). Only the owner merges `dev`
  into `main` and creates the tag; Claude never pushes to `main`.
- A deploy is always of a tag, so "what is running" has one answer and a rollback is one command.

## 4. Guards that make the rule automatic (built in WP2S)

1. **`php artisan ifgf:preflight`** — run by `deploy.sh` before anything changes; the deploy
   stops on the first failure:
   - `APP_ENV` is `staging` or `production`, `APP_DEBUG` is false;
   - the database name is `ifgf_app_<APP_ENV>`, and the connected role is not the dev role;
   - production: the mailer is not `log`/`array`, the calendar driver is `google` and its id is
     the live calendar's; staging: the calendar driver is `fake`;
   - **no member has an `@example.test` or `@example.com` email**, and no member was created
     before the production database was (a sign that rows were brought in);
   - `.env` is not tracked by Git and `storage/` holds no file that came from a checkout.
2. **`deploy/deploy.sh <tag>`** on the server: refuses a dirty working tree or anything but a
   tag (production) or `dev` (staging); runs `composer install --no-dev`, `npm ci && npm run
   build`, `ifgf:preflight`, `migrate --force`, the caches, then restarts the queue worker.
3. **Backups**: nightly, encrypted, copied off the machine; a backup of production is restored
   only into a scratch database on the server, never onto a development machine.
4. Already in place: the test suite refuses any database not ending in `_test`; the seeders hold
   reference data only; `.gitignore` covers `.env*`, `storage/`, `public/build`, `*.xlsx`.

## 5. When the owner says "deploy"

**Claude, on the development PC** (no production access — `CLAUDE.md` forbids touching it):

1. `git status` is clean, the branch is `dev`, and every change is committed and pushed.
2. The `STANDARDS.md` §5 gate: full test suite green, `composer audit` and `npm audit` clean,
   the security and privacy checklists against what changed since the last tag.
3. Search the repository for anything that must not ship: `git ls-files` shows no `.env`,
   `.sql`, `.csv`, `.xlsx`, `.log` or `storage/` file other than the `.gitignore`s, and
   `git grep` finds no real name, email or phone outside the tests' `example.com`/`.test`.
4. List the new migrations and say, in words, what each does to existing rows. A migration that
   changes or deletes data needs the owner's yes and a backup taken just before.
5. Write the release notes: what changed for members, ushers, leaders and admins.
6. Hand the owner the exact steps below with the version filled in.

**The owner:**

7. Merge `dev` into `main` and tag it: `git tag v1.0.0 && git push origin main v1.0.0`.
8. Staging first: on the server, `deploy/deploy.sh dev` for staging, then
   `php artisan ifgf:smoke https://staging.<domain>` (Claude can run this from the dev PC; it
   only reads pages).
9. Production: take a backup (`deploy/backup.sh`), then `deploy/deploy.sh v1.0.0`.

**Both, after the deploy:**

10. `php artisan ifgf:smoke https://<public hostname>` — HTTPS, headers, cookies, `/admin`
    refused, no stack trace, `.env` not served, throttling counts the real visitor.
11. Register one person with a real email the owner controls, check the welcome email and the
    calendar event arrive, then delete that test registration with the admin's anonymise
    action (§5.14) — never with SQL.
12. Watch `storage/logs` on the server for an hour, and the queue for failed jobs.

## 6. The first production deploy — once only

Before it, all of these must be true (each is a blocker):
- WP2S done and staging has run for at least one Sunday;
- trusted proxies set (behind the tunnel every visitor shares Cloudflare's address, so the
  registration throttle would otherwise lock everyone out), security headers, HTTPS only;
- the email sign-in link built (WP2), and a mail provider chosen;
- if `IFGF_GOOGLE_BIRTHDAY=true`: Google's verification of the OAuth app;
- member deletion / anonymise built (PRD §5.14);
- a backup restored once into a scratch database.

Then, on the server: create the `ifgf_prod` role and `ifgf_app_prod` (owned by that role only);
write `.env` by hand from `.env.example`; `php artisan key:generate`; `deploy/deploy.sh v1.0.0`
(which migrates and seeds the reference data); the owner signs in with Google and runs
`php artisan ifgf:admin:grant <email>`. The cutover itself — final workbook import, adopting the
legacy calendar event ids, mailing every member their new QR, switching off the Apps Script — is
WP9's checklist and happens after this, on the server.

## 7. Rollback

`deploy/deploy.sh <previous tag>`. If the release ran a migration that changed data, restore the
backup taken in step 9 into `ifgf_app_prod` — registrations made since then are listed from the
log and entered again by hand. A schema change that cannot be undone needs a written plan before
it is deployed, not after.
