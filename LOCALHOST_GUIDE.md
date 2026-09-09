# Running it on localhost — what you can actually see today

**Written 2026-08-21 (Cowork).** A walkthrough of the three things you asked about, against the
application **as it exists**, not as the PRD describes it. Bring-up steps are in
`tools/LOCALHOST_PROMPT.md`; hand that to Claude Code first.

> ## 🔴 One of your three items does not exist yet
>
> **iCare weekly attendance has not been built.** Zero `ifgf_` tables, zero services, zero routes,
> zero screens. There is nothing to log into and use. Section 3 tells you what *does* exist that
> iCare will replace, and how to see the iCare design without waiting for the build — but a
> step-by-step "how to record iCare attendance" would be a manual for software that isn't there.
>
> **Member types are also not what the PRD describes.** The three roles — admin, leader, member —
> are FR-11's *target*. What runs today is the legacy `usergroup_id` model, and section 2 documents
> that instead, with the mapping.

**Confidence key.** This project has been burned by documents that read as authoritative and were
stale, so every claim below is marked:

- **[pinned]** — asserted by a characterization test. Reliable.
- **[measured]** — observed this month, not yet pinned.
- **[source]** — read from the code, **never executed**. Claude Code's step 8 confirms or corrects it.

---

## Before you start — five things that will look like bugs and are not

| What you'll see | Why | Do |
|---|---|---|
| **`/` returns 500** | `imagick` is not installed on this machine; a QR call needs it. UP-008, decision taken: switch to `format('svg')`, not yet landed | Ignore it. Go straight to `/login`. **Don't let anyone debug it** |
| **Every admin page 500s** with `htmlspecialchars(): ... UrlGenerator given` | `church_details` has no row, so `settings.favicon` is null and `url(null)` returns an object | Run the `DemoDataSeeder` step. This is *always* the cause **[pinned]** |
| **`/admin/member/show/{name}` 500s** | Same imagick issue, UP-008 **[pinned]** | Expected. Use `/admin/member/edit/{name}` instead |
| **Printing a membership card 500s or dumps** | CARD-001: a fallback calls an undefined variable, so printing a card for anyone **without a photo** is a hard error in both controllers **[pinned]** | Expected. Don't print cards |
| **MySQL "won't start"** — `InnoDB: ibdata1 must be writable` | Something is *already* listening on 3306 | Check for a running `mysqld` before starting one **[measured]** |

None of these are your setup. All are recorded findings.

---

## 1. Member registration

There are **two** self-registration doors, they behave completely differently, and the difference
matters more than either one on its own.

### Door A — `/guest/register` · the one that works properly

This is the WebBuilder guest signup, reached from the public site. **[source]**

**What you do:** fill first name, last name, gender, date of birth, mobile (10 digits), email,
password (min 8, confirmed). Submit.

**What happens:** full server-side validation, a real reCAPTCHA rule *if*
`settings.guest_register_captcha_status` is on, rate limiting at 5 attempts per minute, and a kill
switch at `settings.guest_registration`. The account is created at **usergroup 5 — ChurchMember**,
logged in, and sent to the prayer page.

**What to check while you're there:** that the account cannot reach `/admin/*`. It shouldn't.

### Door B — `/register` · 🔴 do not use this one, and do not let it stay

**It is supposed to be switched off.** `routes/web.php:68` disables registration; **line 71 calls
`Auth::routes()` again with no arguments and turns it back on.** `GET /register` returns 200.
That's AUTH-001 **[pinned]**.

**What it does if it runs** — and this is why it matters **[source]**:

- creates a **new church** from whatever the form posted
- creates the user at **`usergroup_id = 3`** — the value that bypasses every permission check in the
  application, through two separate mechanisms
- then loops every row in the `permissions` table and **grants the account all of them explicitly**

So one form submission produces a church administrator with every permission in the system. The
captcha check is `if (request('g-recaptcha-response'))` — it tests that the *field isn't empty* and
never verifies it with anyone. There's no rate limit and the validator is an empty stub.

**It may currently crash instead.** The controller never imports `User`, `Carbon`, `Log` or
`Exception`, so it looks like it fatals on `Carbon::now()` before creating anything **[source]**.

> ⚠ **If it does crash, the crash is the only thing protecting you, and the obvious tidy-up is the
> dangerous move.** Adding the four missing `use` statements turns a 500 into a working public
> admin factory. Treat that file as frozen. This is the same trap as CARD-001, where a typo is
> currently the only thing preventing a live data leak.

**Try it on localhost, then decide.** Claude Code's step 8 measures it properly. On loopback with
synthetic data nothing is at risk — which is exactly why measuring it here, now, is worth doing
before anything is ever exposed.

### The admin account you'll be given

`church:install-data` creates it. It looks for a usergroup named `Admin`, doesn't find one (they're
called `SiteAdmin`, `ChurchAdmin`, …) and **falls back to usergroup 3** **[source]** — the bypass
value. Keep reading; section 2 explains why that shapes everything you'll see.

---

## 2. Member types — what actually governs access

### What the PRD wants (FR-11) — **not built**

Three roles: **member** (self-only profile and QR), **leader** (adds attendance *within an assigned
scope*), **admin** (everything). One role per user, admin-only changes, audited, protected against
removing the last admin.

**None of that exists yet.** It's FR-11's job, and FR-11 hasn't started.

### What actually runs today

Two things stacked, and access is decided by the first one that says no.

**Layer 1 — `usergroup_id`, a single integer on the user row.** Seeded as:

| id | name | What it means in practice **[pinned]** |
|---:|---|---|
| 1 | SiteAdmin | Redirected to `/portal` by the admin gate — **cannot see church admin pages** |
| 2 | SiteSubadmin | — |
| 3 | **ChurchAdmin** | 🔴 **Bypasses every permission check.** See below |
| 4 | ChurchSubadmin | Passes the admin gate, then *is* subject to permission checks |
| 5 | ChurchMember | Ordinary member. The member area only |
| 6 | Preacher | — |

**Layer 2 — Laratrust roles and permissions** (`read-members`, `create-attendance`, …), applied by
`permission:` middleware. Denial is **401**, not 403 **[pinned]** — that surprises people.

### 🔴 The thing that will confuse you within five minutes

**Your admin account is usergroup 3, and usergroup 3 bypasses the entire permission system.** Two
independent mechanisms do it **[pinned]**:

1. `app/Http/Kernel.php:74` aliases `permission` to a middleware that waves usergroup 3 through
   every `permission:*` route, with no role, no grant and no audit record.
2. `AuthServiceProvider` registers `Gate::before(fn ($user) => $user->usergroup_id == 3 ?: null)`,
   which returns **true for every ability** — including every policy, and including
   `Gate::allows('group', …)`, the only church-scoping check on several pages.

**Consequence for your walkthrough: as the admin, everything works, and that tells you nothing.**
You cannot see the permission model succeed or fail from that account, because it never runs.

**To see access control actually do something, log in as the usergroup 4 account** — it clears the
admin gate and is *not* the bypass value, so permission checks genuinely apply to it. This isn't a
detail: an earlier session tested permissions with a usergroup 1 fixture, every assertion was really
observing a redirect, and it produced a wrong baseline plus a false suspicion that the role library
was broken.

### What to try, and what you should see

| Try | Expect |
|---|---|
| Log in as **usergroup 5**, visit `/admin/members` | Denied **[pinned]** |
| Log in as **usergroup 4 with no permissions**, visit `/admin/members` | **401** **[pinned]** |
| Grant that account `read-members`, retry | Reaches the page |
| Log in as **usergroup 3**, visit anything | Everything opens, regardless of grants **[pinned]** |
| Log in as **usergroup 1**, visit `/admin/dashboard` | Redirect to `/portal` **[pinned]** |

⚠ **Two different `/admin/*` surfaces exist and they deny differently.** Routes in `routes/admin.php`
carry `['web','auth','churchadmin']`; the `/admin/*` group at `routes/web.php:182` — the member-list
and card routes — carries only `['permission:read-members']`, with **no `auth` at all**. A guest gets
401 from both, but for different reasons, and usergroup 1 gets a redirect from one and a bare 401
from the other **[pinned]**. If two admin pages behave differently for the same account, this is why.

### Groups — the closest thing to "leader" that exists

`/admin/groups`. Worth clicking through, because iCare groups will be `groups` rows.

- **The granular permissions are dead code** **[pinned]**. `read-groups` alone lets an account
  create, edit, message **and delete** groups. `create-groups`, `update-groups` and `delete-groups`
  sit on routes that never resolve — one route file shadows the other.
- 🔴 **Don't delete a group.** Deleting one **hard-deletes every member's permission rows**,
  unscoped to that group or church, with no record of what they were — while the group itself is only
  soft-deleted **[pinned]**. On localhost that's recoverable by reseeding. It is worth seeing once,
  deliberately, so the finding is real to you.

---

## 3. iCare leader attendance

### It does not exist. Here is precisely what's missing

Nothing has been built: no `ifgf_` tables, no `IcareAttendanceService`, no controller, no route, no
screen. `SCHEMA_SPEC.md` Part 3 (§25) is a ~1,100-line specification, and **every signature in it is
a claim about what should exist**, not a description of something that does.

Concretely, none of this is in the application: a meeting weekday per group · an occurrence separate
from a session · a roster screen · present/absent/excused as distinct states · onsite vs online ·
the visitor flag · quick-add for an unregistered guest · pre-ticking from last week · finalization.

### What exists that iCare will replace

**Event attendance** — `EventAttendanceController`, reachable from the events area: open a session
for an event, mark attendees, lock and unlock. Look at it, because three of its properties explain
the iCare design:

1. **It records presence only.** A row means "was here". **No row means *not recorded* — it does not
   mean absent** **[pinned]**. A member who stayed home, one whose scan failed, and a session nobody
   opened are all stored identically: as nothing. This is why §25 makes such a fuss about absence
   being an explicit, actor-stamped assertion.
2. **There is no per-leader scope at all.** An `event_managers` table exists and is managed by the
   UI, but the record/lock/unlock endpoints **never consult it** **[pinned]**. Any account holding
   `create-attendance` can record attendance for any event in its church. Assigning managers today
   is bookkeeping, not authorization — the control has to be *built* (SEC-002).
3. **Unlocking records no reason and has nowhere to put one** **[pinned]**. Reopening attendance is
   exactly what a pastoral audit trail is for, and today it leaves none.

**Try it:** open a session on a seeded event, mark a couple of members, lock it, unlock it. Then
ask what you'd want to know three months later about that unlock. That question is FR-04.8.

### How to see the iCare screen without waiting for the build

`ICARE_PILOT_PLAN.md` calls this **D12-A-lite**: a clickable mock of the roster screen — the resolved
date with its timezone label, the pre-tick, the visitor add, the six screen states — with no
database and no backend. **One session, no prerequisites, blocked by nothing.**

It's worth doing before the build rather than after, because §25's design rests on guesses about how
leaders actually work that **no iCare leader has ever reviewed**: that last week's attendance is a
good prior, that visitors are common enough to need a one-tap path, that a meeting is identified by
the day it *starts*. Any of those can be falsified in a ten-minute conversation. Ask me for the mock
whenever you want it.

### The one thing to carry into that conversation

The most dangerous bug in this feature isn't a crash — it's the date. "Most recent Tuesday" computed
in UTC instead of the branch's timezone is wrong **by seven days**, not one, because weekday
arithmetic snaps to the nearest matching day. It only misfires between midnight and 08:00 local **on
the meeting day itself**, so nobody hits it by accident on a Wednesday afternoon — and when it does
fire, it files a whole meeting into last week's occurrence with no error anywhere. §25.2 has the
full rule; a test for it gets written before the feature.

---

## Where to look when something breaks

1. **Check this file's "five things" table first.** Most localhost 500s are on it.
2. `storage/logs/laravel.log`.
3. `APP_DEBUG=true` locally, so the browser shows the real trace. **It must never be true on
   anything reachable from outside this machine.**
4. **Read the failing line before blaming the framework.** A 500 in a fresh environment is a fixture
   question until proven otherwise — that lesson cost this project a commit and a withdrawn finding.

**Don't fix what you find.** Findings go on the list; the characterization suite is how they get
pinned. Fixing and recording in one pass destroys the baseline that makes the upgrade safe.
