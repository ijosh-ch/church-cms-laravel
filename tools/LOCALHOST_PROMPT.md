# Localhost bring-up — Claude Code prompt

**Purpose:** get the application running at `http://127.0.0.1:8000` on this machine so the owner can
click through it, and **measure** what the three surfaces in `LOCALHOST_GUIDE.md` actually do.

**This is not a deployment.** No tunnel, no `0.0.0.0`, no Cloudflare. `ICARE_PILOT_PLAN.md` R1 is
unresolved — `/register` may create a `usergroup_id` 3 account holding every permission — so this
application does not get a listening socket beyond the loopback interface until that is measured and
closed.

---

```
LOCALHOST BRING-UP — CLAUDE CODE. Paste unchanged.

=== ORIENT (short — this is not a work-package session) ===
git status --short --branch → CONTEXT.md → TODO.md. That is enough.
Do NOT start a work package. Do NOT write characterization tests. Do NOT touch
tests/, SCHEMA_SPEC.md, or anything TODO.md shows in progress.

=== GOAL ===
A running local app the owner can click through, plus a MEASURED report of what
each surface in LOCALHOST_GUIDE.md actually does. The guide is written from
characterization tests and source reading; your job is to confirm or correct it.

=== STEP 1 — MySQL, and do not misdiagnose it ===
CHECK FOR AN EXISTING LISTENER FIRST:
  Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue
A second mysqld fails with "InnoDB: ibdata1 must be writable". That is a LOCK
CONFLICT WITH THE RUNNING INSTANCE, not a broken install (measured 2026-08-21).
Only if nothing is listening:
  & "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --defaults-file="C:\ProgramData\MySQL\MySQL Server 8.4\my.ini" --console
Accepts connections ~4s later.

=== STEP 2 — confirm which database you are about to touch ===
.env has APP_ENV=local, DB_DATABASE=churchcms, DB_HOST=127.0.0.1, DB_USERNAME=root.

🔴 churchcms is the DEV database. churchcms_test_disposable is the TEST database
and the suite wipes it. Never point one at the other, and never run migrate:fresh
against the test DB while the owner is browsing.

Report: does churchcms exist, and how many tables does it have? Do not assume.

=== STEP 3 — schema and lookup data ===
If churchcms is empty or absent:
  php artisan migrate                      (NOT migrate:fresh if it has data)
  php artisan db:seed                      (DatabaseSeeder: usergroups, countries,
                                            states, cities, permissions, roles,
                                            settings, bible)
If it already has tables, report what is there and STOP for the owner before
migrating anything.

=== STEP 4 — 🔴 THE STEP THAT DECIDES WHETHER ANY ADMIN PAGE LOADS ===
  php artisan db:seed --class=DemoDataSeeder

This creates the church AND church_details rows. AppServiceProvider::boot()
populates settings.* from church_details for Church::first(). With no such row,
config('settings.favicon') is NULL, layouts/admin/layout.blade.php line 6 does
url(null) which returns the UrlGenerator INSTANCE, and Blade's e() throws:

  htmlspecialchars(): Argument #1 ($string) must be of type string,
  Illuminate\Routing\UrlGenerator given

EVERY admin page 500s with that, and it looks exactly like an application bug.
It is a missing fixture row. This already cost this project a withdrawn finding
(MEM-001). If you see that message, the answer is always this step.

DemoDataSeeder also seeds groups, events, userprofiles, families and pages, which
is what makes the walkthrough in LOCALHOST_GUIDE.md possible.

=== STEP 5 — accounts, and there must be THREE ===
The admin comes from the installer command:
  php artisan church:install-data '{"church_name":"IFGF Taipei","church_email":"admin@example.invalid","church_phone":"0000000000","admin_email":"admin@example.invalid","admin_password":"<owner picks>"}'

⚠ Note what it does: Usergroup::where('name','Admin') does not match anything
(the seeder creates SiteAdmin/ChurchAdmin/ChurchSubadmin/ChurchMember/Preacher),
so it falls back to usergroup_id = 3 — the SEC-001 bypass value. The owner's admin
account therefore bypasses EVERY permission check through both controls. Say so in
your report; the guide depends on the owner knowing it.

Then create two more by hand (tinker), because with only a usergroup-3 account the
owner cannot see the permission model do anything at all:
  - a usergroup 4 account (ChurchSubadmin) holding NO permissions
  - a usergroup 5 account (ChurchMember)
Same church_id as the admin. Each needs a userprofile row — /admin/member/edit and
the member pages read it. Report the three logins as a table.

=== STEP 6 — assets and mail ===
  If public/js or public/css is missing: npm ci ; npm run production
  NEVER npm audit fix.
Set MAIL_STATUS=off in .env for local, or point MAIL_MAILER at log. Registration
and verification queue mail; an unconfigured mailer turns a working page into a
confusing failure.

=== STEP 7 — serve, LOOPBACK ONLY ===
  php artisan serve --host=127.0.0.1 --port=8000
Never --host=0.0.0.0. Never a tunnel. See the note at the top of this file.

=== STEP 8 — MEASURE, and this is the actual deliverable ===
For each row below: actual HTTP status, where it redirects, and for a 500 the
EXACT exception class and message. Test each as guest / usergroup 5 / usergroup 4
with no permissions / usergroup 3 admin.

  /                                  ← expect 500, imagick, UP-008. Do NOT debug it.
  /login
  /register            GET and POST  ← 🔴 SEE BELOW, do this one carefully
  /guest/register      GET and POST
  /admin/dashboard
  /admin/members
  /admin/member/add
  /admin/member/edit/{name}
  /admin/member/show/{name}          ← expect 500, imagick, UP-008
  /admin/groups        and the group create/edit/delete routes
  /member/home
  /member/mygrouplist                ← measured 500 in session 10, never diagnosed
  the event attendance routes (openSession / markAttendee / lock / unlock)

🔴 /register — ICARE_PILOT_PLAN.md R1/R2. Read that section BEFORE touching it.
  1. GET /register → status; does the form render?
  2. POST /register, complete valid body, any non-empty g-recaptcha-response
     → status. If 500, report the EXACT exception class and message.
  3. If a user IS created:
       SELECT usergroup_id FROM users WHERE id=<new>;
       SELECT COUNT(*) FROM permission_user WHERE user_id=<new>;
       did a new `church` row appear?
  4. Do the same for /guest/register and diff the two.

  RegisterController does not import User, Carbon, Log or Exception, so the source
  reading says it fatals on Carbon::now() before creating anything. CONFIRM OR
  REFUTE THAT BY RUNNING IT. It is a source claim, not a measurement.

  🔴 DO NOT "FIX" THE MISSING use STATEMENTS. That broken import may be the only
  thing preventing a public church-admin factory — CARD-001's exact shape, where
  tidying a typo converts a crash into a live defect. RegisterController is FROZEN
  until the owner rules on R1. If you believe it needs changing, say so and stop.

=== REPORT ===
A table: route × actor × measured status × matches-guide / corrects-guide.
Then the three logins, and the exact commands to restart everything tomorrow.
Correct LOCALHOST_GUIDE.md where you measured something different — that is what
the guide is for. Commit nothing without showing staged files and the message.

=== DO NOT ===
- Expose the port. Do not tunnel. Do not bind 0.0.0.0.
- Set APP_DEBUG=false "for realism" — you want the stack traces locally. It must
  never be true on anything reachable.
- Fix defects found while measuring. Record them. The exceptions are things that
  stop the app booting at all.
- Load real member data. There is none in this repo and none should arrive.
```
