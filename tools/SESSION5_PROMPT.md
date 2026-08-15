# Session 5 prompt — post-upgrade hardening

Paste the fenced block below into a **fresh** Cowork session on **Opus 5**, running in this
repository. It is deliberately self-contained: it carries every finding already paid for, so the
session does **not** need the full `CLAUDE.md` session-start preamble (~20k tokens).

Written 2026-08-10 at the end of Session 4, which took the stack to PHP 8.4.24 / Laravel 13.24.0 /
MySQL 8.4.11 LTS.

---

```
Work in this repository. Do NOT run the usual CLAUDE.md session-start preamble --
this prompt already carries the state. Read only CONTEXT.md and TODO.md to confirm
nothing drifted, then start. Never read PRD.md, build.md, composer.lock,
package-lock.json or graphify-out/ whole.

=== WHERE THINGS STAND (verified 2026-08-10, do not re-derive) ===

Stack is fully upgraded and working:
  PHP 8.4.24 (C:\php\8.4, first on Machine PATH, pinned in composer.json
              config.platform.php; PHP 8.3 has been DELETED -- no PATH-reorder
              rollback exists any more)
  Laravel 13.24.0   (from 10.50.2, via 11.55.0 -> 12.65.0, each committed separately)
  MySQL 8.4.11 LTS  (in-place from 8.4.9)

Branch: contrib/laravel-supported-platform, 18 commits, working tree clean,
NOTHING PUSHED. main is a clean subset of upstream/main; ifgf/main exists locally;
deploy does not exist and must not be created.

Verified working: composer validate --strict; php artisan about; all 93 migrations
+ every seeder via `php artisan migrate:fresh --seed --env=testing`; php artisan
test; `npm run production` asset build; and over HTTP, /login and /register return
200 with rendered pages.

Test database: churchcms_test_disposable on MySQL 8.4.11, owned by a scoped user
that can reach ONLY that database. Credentials are in .env.testing, which is
gitignored -- read them from that file, never print them, never commit them.

=== YOUR JOB, IN THIS ORDER ===

1. FIX THE HOMEPAGE. `/` returns 500:
   BaconQrCode\Exception\RuntimeException "You need to install the imagick
   extension to use this back end", from simplesoftwareio/simple-qrcode.
   This is PRE-EXISTING, not an upgrade regression -- imagick was never installed
   and is not in the 27-extension list in tools/fix-php-ini.ps1. `gd` IS installed.
   Prefer switching the QR writer to the gd backend over adding an imagick DLL:
   it removes a native dependency from the server build instead of adding one.
   Whichever you choose, update tools/fix-php-ini.ps1 AND the extensions list in
   .github/workflows/ci.yml so the machine, CI and the future server agree.
   Prove it with a real HTTP request returning 200, not just a passing test.

2. MAKE THE SUITE FAST BEFORE WRITING TESTS. One test currently takes ~8 minutes
   because RefreshDatabase replays all 93 migrations per test class against MySQL.
   `php artisan schema:dump` fixes this but needs mysqldump, which is not on PATH;
   it lives in "C:\Program Files\MySQL\MySQL Server 8.4\bin". Adding that to the
   Machine PATH needs an elevated shell -- if you cannot elevate, give me the exact
   one-line command to run and continue with the rest. Do NOT write step 3's tests
   first; at 8 minutes per class the suite becomes unusable.

3. CHARACTERIZATION TESTS -- the real gap. The entire 10->13 upgrade currently
   rests on ONE test (MemberImportCharacterizationTest, 1 test / 4 assertions).
   Cover the Release 1 surface ONLY: auth; roles and direct permissions; member
   profile; member QR / membership card; event attendance session
   open/scan/lock/unlock; group access; exports. Capture behaviour as it is today,
   including behaviour that looks wrong -- these are characterization tests, not
   correctness tests. Do NOT write tests for CGSL, ministries, registration or
   Worship Night (Release 2, see PRODUCTION_PATH.md).
   Note while working: roughly 60% of the attendance surface already exists
   upstream -- EventAttendanceSession (opened_by/locked_at/locked_by),
   EventAttendee (scanned_at/scanned_by), Api/AttendanceController
   (myEvents/openSession/scan/lock/sessionReport) and
   Admin/EventAttendanceController (13 methods). Duplicate scan returns 409 and a
   locked session returns 403 today; both must stay true.

4. RECONCILE THE ROUTE COUNT. `route:list` reports 730; ROUTE_MIGRATION_INVENTORY.md
   claims 812 static declarations. 12 are commented out; ~70 are unexplained,
   most likely duplicate method+URI pairs where the later declaration wins. There
   is NO pre-upgrade baseline, so this is not proven upgrade-neutral. Check out
   086f33d (pre-upgrade), run `route:list --json`, diff against HEAD, and correct
   the inventory. While there, fix its false claim that console.php defines no
   scheduled tasks -- `schedule:list` shows gego:checkquote (hourly) and
   gego:checkgetresponse (daily).

Then STOP and report before starting anything in TODO.md's "Next" section
(package scaffold, UPSTREAM.md/C7 merge rehearsal, pushing branches).

=== TRAPS FOUND THE HARD WAY -- DO NOT REDISCOVER ===

- .env.testing REPLACES .env; Laravel does not merge them. A DB-only .env.testing
  passes the PHPUnit suite (phpunit.xml supplies APP_KEY etc. separately) while
  every served HTTP request 500s on a missing APP_KEY. Keep it a complete env.
- `php artisan serve --env=testing` does NOT reach the request-handling subprocess.
  Export APP_ENV=testing as a real environment variable instead.
- Illuminate\Console\Events\CommandStarting is never dispatched when
  APP_ENV=testing (Foundation\Console\Kernel only bridges Symfony's console events
  when ! runningUnitTests()). App\Providers\DatabaseSafetyServiceProvider therefore
  registers two listeners on purpose. Read its docblock before touching it.
- `composer require` silently moves runtime packages into require-dev under
  --no-interaction. For multi-package changes, edit composer.json directly.
- MySQL root auth on this box is intermittently flaky -- a verified login can fail
  minutes later with no restart. Use the scoped test user from .env.testing; it has
  been reliable. A failed root login does not mean the password is wrong.
- Pre-existing defects, all still unfixed, none caused by the upgrade:
  app/Models/FeedbackMessage.php points $presenter at App\Presenters\UserPresenter,
  which does not exist; app/Imports/UsersImport.php::collection() dereferences an
  undefined $request on any non-empty import; app/Traits/SendPushNotification.php
  imports LaravelFCM\* classes that were never autoloadable.
  Characterize them as broken -- do not "fix" them inside a characterization test.

=== RULES ===

- Never commit without showing me the staged file list and the message first.
- Never push. Never create or touch deploy, production, live Google Calendar, or
  real member data.
- Never run migrate:fresh / db:wipe without the DatabaseSafetyServiceProvider
  guard passing -- it prints and asserts environment/driver/host/database first.
- Never use --ignore-platform-reqs, --no-verify or forced Composer resolutions.
  If a package cannot be satisfied, STOP and report it.
- Never run `npm audit fix`. 166 npm vulnerabilities and Vue 2 EOL are known and
  deferred; the Vite migration is a separate approved decision and must not be
  mixed into this work.
- Never commit .env.testing, real member data, or the legacy workbook.
- Prefer artisan generators over hand-writing files.
- Always pipe long command output (2>&1 | tail -60).
- Long commands: composer/npm/test runs exceed two minutes here -- run them in the
  background rather than letting them time out, and never run a second command
  against the test database while one is still running (that corrupted a seeder
  run in Session 4).
- At the end: update MEMORY.md (append), CONTEXT.md and TODO.md so item 1 is the
  literal next action.
```

---

## Notes for the operator

**Why Cowork suits this.** Step 3 is the long pole -- roughly three sessions' worth of test writing
in the original plan -- and it benefits from being able to watch progress rather than reviewing one
large diff at the end.

**Context reduction.** Start a **new** session rather than continuing an old one; the prompt above
replaces the state that a long transcript would otherwise carry. The two lines that do the work are
"do NOT run the usual CLAUDE.md session-start preamble" and the never-read-whole list -- together
they avoid roughly 20k tokens of re-discovery before any real work starts.

**Credentials are deliberately absent** from this file. The prompt points at `.env.testing`, which
is gitignored, so this file stays safe to commit.
