# iCare test pilot — plan, decisions D12–D15

**Written 2026-08-21 (Cowork).** Planning only. No PHP, no execution, nothing built. Companion to
`SCHEMA_SPEC.md` Part 3 (§25) and `tools/ICARE_PROMPTS.md`.

Owner's goal, verbatim: *"Target to push the iCare weekly attendance to go for testing, ready to be
tested using Cloudflare for future access via internet for this app."*

---

## 🔴 Read this first — a gating discovery, made while planning

**`/register` creates a `usergroup_id = 3` account and grants it every permission row in the
database.** `app/Http/Controllers/Auth/RegisterController::create()`, read at `3793b54`:

```php
$user->church_id    = $church->id;      // a NEW church, from request data
$user->usergroup_id = 3;                // ← the SEC-001 bypass value, hard-coded
...
$permissions = Permission::get();
foreach ($permissions as $permission) { /* grants EVERY permission to the new user */ }
```

Stack that against what is already recorded and the result is not "a defect to schedule":

- **AUTH-001**: `routes/web.php:68` passes `['register' => false]`; **`:71` calls `Auth::routes()`
  again with no arguments** and reopens it. `GET /register` returns 200, pinned by a test.
- **SEC-001, both controls**: `usergroup_id == 3` is the value that bypasses the `permission:`
  middleware *and* is granted every ability by `Gate::before` in `AuthServiceProvider` — policies
  included.
- **The captcha is not a captcha.** `register()` checks `if (request('g-recaptcha-response'))` —
  **presence of a form field**, never verified against Google. There is no recaptcha config in
  `config/` at all. `validator()` is an empty stub. There is no throttle on the route.

**So the answer to the prompt's question is the worst available one: a self-registered account lands
directly in `usergroup_id` 3, and additionally holds every permission explicitly — so removing
SEC-001 would not contain it.** On a public hostname this is not an account factory, it is a
*church-admin* factory, and `GRP-002`/`CARD-002` already demonstrate that `church_id` is not a
reliable boundary once inside.

### ⚠ And what is probably preventing it right now is a missing `use` statement

`RegisterController` imports `Church`, `Userprofile`, `Permission`, `PermissionUser` — but **not
`App\Models\User`, not `Carbon`, not `Log`, and not `Exception`.** `$church->created_at =
Carbon::now()` resolves to `App\Http\Controllers\Auth\Carbon`, which does not exist, and raises
`Error` *before* the `User` line is ever reached. `\Error` does not extend `\Exception`, and the
`catch (Exception $e)` blocks resolve to a non-existent `App\Http\Controllers\Auth\Exception` and
catch nothing. So `POST /register` very likely **500s** rather than registering.

🔴 **This is CARD-001's shape exactly, and it must be handled the same way: a broken import is the
only thing standing between the internet and a church-admin factory. Adding the missing `use`
statements — the obvious, tidy, one-line "fix" — converts a 500 into the live defect.** Nobody
should touch this file without reading this paragraph.

**Status of the above: READ, NOT RUN.** I am Cowork; I did not execute anything. This is source
reasoning, and this project has twice been burned by asserting against something other than the
running application. **The measurement Claude Code must perform before any tunnel exists:**

```
1. GET  /register            → status, and does the form render
2. POST /register with a complete valid body and any non-empty g-recaptcha-response
   → status; and if it is 500, the EXACT exception class and message
3. If a user IS created: SELECT usergroup_id FROM users WHERE id = <new>;
                         SELECT COUNT(*) FROM permission_user WHERE user_id = <new>;
                         and whether a new `church` row was created
4. Repeat for /guest/register (see below) and diff the two
```

Until step 2 returns a measured result, **treat `/register` as live and exploitable.** Assume the
worse branch; it costs one WAF rule.

### The contrast that shows this is a defect, not house style

`/guest/register` → `WebBuilder\GuestAuthController::register()` is the *other* self-registration
surface, and it is written correctly: full `$request->validate()` rules, a real `ValidRecaptcha`
rule class, `throttle:5,1` on the route, a `config('settings.guest_registration')` kill switch, and
it creates the account at **`usergroup 5`** — ordinary member. Two registration surfaces, in one
application, three usergroup levels apart. **Whatever policy the church has about self-service
accounts, `/register` is not implementing it.**

---

## 1. D12 — which goal is this? **Recommendation: D12-A.**

The sentence admits two readings and the whole plan turns on which one.

| | D12-A · **test pilot** | D12-B · **production iCare** |
|---|---|---|
| Data | **synthetic only**, disposable DB | real congregation, 217 members, real photos |
| Audience | a named handful of iCare leaders, behind Cloudflare Access | the church |
| Purpose | learn whether the date rule, roster, pre-tick and visitor flow match how leaders actually work | run the ministry |
| Prerequisites | WP 0A gate · D7–D11 · a reduced WP 0C · a roster UI · a tunnel | all of R1a, then R1b |
| **Sessions** | **12–17** | **57–76** (R1a 44–57 + R1b iCare 13–19) |

**D12-A is recommended, and I think it is what the sentence means** — *"ready to be tested"* and
*"for future access"* both point at a rehearsal, not a cutover. But this is the owner's call, and
answering it wrongly in either direction is expensive: D12-B started as though it were D12-A puts
real member PII on the internet in front of the defects in §"Risks"; D12-A treated as D12-B buys
40+ sessions of work nobody asked for yet.

**D12-B is not merely bigger; it is a different risk class.** It requires WP 0D privacy approval
(FR-11.15's privacy-impact review, which the church must approve *before MVP production data is
loaded*), the private disk that does not exist, the member import, and Phase 1E readiness. None of
that is optional and none of it is on the pilot's path.

### ▶ D12-A-lite — do this one **this week**, in parallel, whatever else is decided

**Cost: ~1 session. Prerequisites: none. Not blocked by the exit gate, because it touches no
application code.**

A clickable HTML mock of the roster screen — the six states from §25.3, the resolved date with its
timezone label, the pre-tick, the visitor add, the conflict report — with no database, no Laravel,
no tunnel. Put it in front of two real iCare leaders and ask them to record last week's meeting.

**Why this is the highest-value item on the page:** the 12–17 sessions of D12-A exist to answer
*"does §25's design match how leaders actually work?"*, and §25 is an argued specification that
**has never been shown to a leader**. The pre-tick rule, the "unmarked means nothing observed"
semantics, the visitor-vs-member distinction and the after-midnight date behaviour are all guesses
about human workflow — good guesses, but a leader can falsify any of them in ten minutes. Finding
out *before* the build costs one session; finding out after costs a rebuild of the service contract.

*Assumption this rests on:* that iCare leaders are reachable for a 20-minute conversation. If they
are not, this collapses to a paper walkthrough with the owner and is worth much less.

---

## 2. The critical path to D12-A

### D13 — does the pilot need an exception to `build.md` OPERATING CONTRACT 10?

**My recommendation: NO. Do not ask for the exception. Close the gate first.** I am naming this as
a decision anyway because the prompt is right that inventing a silent shortcut is the failure mode,
and the owner should get to overrule me.

The pilot needs WP 0C (the tables). OPERATING CONTRACT 10 forbids opening WP 0C while WP 0A's gate
is open. So the contract *is* engaged, and there are only two honest routes: close the gate, or
argue an exception.

**The case for an exception** — the pilot uses synthetic data on a disposable database that is
destroyed afterwards, so the blast radius of a WP 0C mistake is a throwaway host, and
characterization exists to protect *production* behaviour.

**Why I reject it anyway, and this is the stronger argument:**

1. **The gate is 3–4 sessions, and two of its three open criteria are a reading session.** Criterion
   2 needs suites 7, 8 and 10; criteria 4 and 7 need you to read `WP0A_OWNER_REVIEW.md`. An
   exception buys perhaps 2–3 sessions.
2. **The protection is not about the pilot's data; it is about WP 0C's own edits.** WP 0C's
   highest-risk items — the `usergroup_id` replacement across 33 files, cascade deletes, the
   `userprofiles` dedupe — modify *upstream* code that the pilot and production share. A synthetic
   database does not make those safer.
3. **Suites 7, 8 and 10 are load-bearing for this specific pilot.** Suite 7 is **event management** —
   and iCare occurrences are `ifgf_event_occurrences` sitting on upstream `events` and
   `event_attendance_sessions` (§25.0 A4). Characterizing the event surface *before* building on it
   is exactly the sequence that found CARD-001, SEC-002 and GRP-001/002/003. Skipping it to reach a
   pilot faster is skipping the step that has produced every serious finding this project has.
4. **This project has already recorded the cost of the opposite instinct.** `PRODUCTION_PATH.md`'s
   own correction: *"I counted only the feature phases and ignored the foundation and production
   work they sit on."*

### The ordered path

| # | Step | Sessions | Required, or convention? |
|---|---|---:|---|
| 0 | **Commit the backlog.** `HEAD` is `3793b54`; sessions 9–11 are uncommitted and `tests/Feature/Card/` + `tests/Feature/Group/` are **untracked** — 32 tests in one place | 0 (part of a session) | **Required.** Not a process nicety; it is 3 sessions of work with no second copy |
| 1 | **Owner reading session** — `WP0A_OWNER_REVIEW.md`, criteria 4 and 7, plus `CACHE_STORE=array` | 0 (yours) | **Required** — 2 of 3 open criteria, no engineering |
| 2 | **D7–D11 sitting** — §5 below is the one page | 0 (yours) | **Required.** D7 and D8 add columns; answering them later means regenerating migrations |
| 3 | **Measure `/register`** — the four steps above — and rule on it (R1) | 0.5 | **Required before any tunnel** |
| 4 | **Characterization suites 7, 8, 10** → criterion 2 | 2–3 | **Required** (contract + argument 3 above) |
| 5 | **WP 0C generation** — all 21 tables + the iCare additions | 1 | **Required.** Generate all 21; empty tables are free |
| 6 | **Fill pass — schema + models + support** | 2–3 | Required |
| 7 | **Fill pass — iCare slice**: `AttendanceRecorder`, `GroupMembershipService`, `IcareAttendanceService`, `IcareAuthorizer`, middleware, controller, requests | 2–3 | Required |
| 8 | **The roster UI** — see the warning below | 2–3 | **Required, and currently unpriced elsewhere** |
| 9 | **Synthetic seeder** (D14) | 1 | Required |
| 10 | **Pilot host + tunnel + Access** (D15) | 1–2 | Required |
| 11 | **Verification** — `migrate:fresh`, `SHOW CREATE TABLE`, the seven iCare test files | 1 | Required |
| | **Total** | **12–17** | |

**Steps 1 and 2 are yours, cost no sessions, and unblock the largest amount of downstream work.
They are the cheapest actions on this page and should happen first.**

> ⚠ **Step 8 is the number I trust least, and §25 is why.** Part 3 specifies the controller, the six
> screen states, the exception→HTTP mapping and the DTOs — **and not one line of markup.** There is
> no Blade, no component, no layout decision. The estimate assumes the roster screen is built on the
> existing Bootstrap admin layout as server-rendered Blade, **not** on the Vue 2 SPA (EOL, 166 npm
> vulnerabilities, migration deferred). If the owner wants it to look like the rest of the member
> app, or wants offline tolerance on a phone in a house with bad wifi, this is a different and
> larger number. **Name it now rather than discovering it at step 8.**

*Assumptions the 12–17 rests on, so the next reader can check them rather than inherit them:*
(a) the pilot needs **no** QR, **no** member portal, **no** member import, **no** birthday Calendar,
**no** reports — only groups, memberships, occurrences and attendance details;
(b) `PRODUCTION_PATH.md`'s "close WP 0A = 3–4" was written at 37 tests and 2 of 11 suites; at 94
tests and 5 of 11 with three suites left, 2–3 is more likely — I have used the lower figure and
flagged it rather than quietly banking the saving;
(c) WP 0D privacy is **deferred**, not skipped, on the grounds that there is no personal data —
see R3, which is an owner decision, not mine;
(d) the tunnel host is a machine that already exists and the church is not buying hardware.

---

## 3. D14 — the pilot data plan

**Ruling requested: synthetic only. No real name, number, birthday or photograph ever reaches the
tunnelled host.** This is the decision that keeps the pilot out of D12-B's risk class, and it holds
regardless of how the `/register` measurement comes back.

**How the synthetic set is generated.** A package seeder, `ifgf:seed-pilot`, run **only** against
the pilot database:

- Names composed from a **fixed word list committed to the repo** — a pool of given and family names
  plausible for a Taipei/Indonesian congregation, combined by index. **Never** faker-with-a-random-
  seed (irreproducible), and **never, under any circumstance, derived from the workbook.** The
  campaign rule stands: the workbook is never committed and never fixtured.
- **Structure real, content fake.** 3 branches, ~8 iCare groups of 6–14 members, 12 weeks of prior
  occurrences with realistic attendance regularity (~70–85% weekly, a few dormant members, two
  visitors, one member who transferred mid-quarter). **The regularity is the point** — the pre-tick
  rule (§25.1) is only testable against a history that has a pattern to learn.
- **At least one group whose branch timezone is not `Asia/Taipei`.** §25.2 boundary case 2 needs a
  remote leader to be exercisable by a tester, and a pilot where every group is in one zone cannot
  falsify the rule that matters most.
- Every seeded email on a domain that cannot receive mail (`@pilot.invalid`), every phone in a
  reserved test range, so a misconfigured mailer or SMS integration cannot reach a real person.
- **Photos: none seeded, and upload disabled** — see R3.

**A tripwire, not just a rule.** A `ifgf:assert-synthetic` check that fails if any `users.email`
resolves to a real domain, if the row count matches the known 217, or if `APP_ENV` is not `pilot`.
Run it in the pilot deploy path. *A rule that is only written down is the kind that got a rejected
timezone contract into a fresh prompt six days after it was rejected.*

**After the pilot:** the database is dropped and the host is destroyed or reimaged. Nothing is
migrated forward — the real member data arrives through FR-12's import into a *different*
environment, with its own conflict review. **The pilot database is never promoted.**

**The one thing testers must be told, in writing, before they log in:** this is a rehearsal on
invented data, the people in it are not real, and anything they record will be deleted. Otherwise a
leader who diligently records a real meeting has put real pastoral data on a throwaway host.

---

## 4. D15 — Cloudflare topology

Drawn from `hosting.md` §147–177, not invented. **The pilot is the "home server" case**, not the
commercial VPS case.

```
  iCare leader's phone
        │  HTTPS
        ▼
  Cloudflare edge ──► WAF + rate limiting + Access identity policy
        │                    (named email list — the pilot testers, and nobody else)
        │  outbound-only tunnel, established FROM the origin
        ▼
  cloudflared ──► 127.0.0.1:80  (Laravel, php-fpm/nginx)
                        │
                        └── MySQL bound to localhost only
  origin firewall: NO inbound ports open at all. Not 80, not 443, not 22 from the internet.
```

**Decisions inside that picture:**

| | Pilot | Why |
|---|---|---|
| Tunnel or VPS? | **Cloudflare Tunnel** | `hosting.md` §162: outbound-only, no public IP, no inbound ports. For a temporary pilot the attack surface is a process, not a host |
| Hostname | **one** — `icare-pilot.<domain>` | **Not `members.`** and not `admin.`. `hosting.md` §153–156 designs `members.` for *public registration*, and public registration is the finding at the top of this page |
| Behind Access? | **everything.** No unauthenticated path, including `/` | The pilot has no public audience. A named-email Access policy is the outer boundary |
| Laravel auth | **still mandatory** | `hosting.md` §158, and it is right: Access is an additional boundary, not a replacement. SEC-001, SEC-002 and GRP-002 are all *inside* the perimeter |
| Registration | **blocked at the edge AND in the app** | A WAF rule denying `/register`, `/password/reset`, `/guest/register` — plus the application fix, which needs an `UPSTREAM.md` entry and a characterization test. **Two independent blocks, because the edge rule is one console mistake from being off** |
| Uploads | **disabled** | R3 |
| Rollback | **`cloudflared` stop** | One command, instant, total. The origin has no inbound ports, so a stopped tunnel is not a degraded service — it is an unreachable one. **This is the pilot's kill switch and every tester should be told it exists** |
| Backups | **none, deliberately** | Synthetic data. `hosting.md`'s offsite-backup requirement is a *production* requirement; carrying it here would create the first copy of pilot data worth protecting |

`hosting.md`'s other home-server requirements — UPS, automatic restart, dual internet paths, uptime
monitor, hardware replacement plan — are **production** requirements. A pilot may run on a laptop
that gets closed at night, and saying so plainly is better than pretending a UPS is on the critical
path. **The one to keep is the external uptime monitor**, because "the tester couldn't get in" and
"the tester didn't try" look identical in feedback otherwise.

**When a tester finds something:** it goes to a written list, not into a fix. The pilot's output is
*findings about the design*, and the temptation to hot-patch a tunnelled host is how a disposable
environment becomes an undocumented production one.

---

## 5. The D7–D11 sitting — one page, one sitting

**Answer these in order. D7 and D8 gate WP 0C generation and are the cheapest unblocking act
available.** Full reasoning at `SCHEMA_SPEC.md` §25.0 and §25.7.

| | Question | What it costs to say yes | What it costs to defer |
|---|---|---|---|
| **D7** | An iCare group gets one backing upstream `events` row plus `ifgf_event_definitions.group_id`. Approve? | iCare groups appear in upstream event lists, exports and counts; the `icare` event type keeps them filterable | **Nothing can be generated.** `AttendanceRecorder`'s lazy session needs an `event_id` — the alternative is a second attendance path, which contradicts FR-04.1 |
| **D8** | One nullable `note` on `ifgf_attendance_details`, with `excused` requiring it. Approve? | One column; pastoral free text that must stay out of exports | FR-05.5's notes have nowhere to go and FR-05.10's exception list is **not computable** |
| **D9** | Upstream `event_attendance_sessions.attendance_date` on the IFGF path: **branch-local day** (recommended) or UTC day? | Two semantics in one upstream column; WP 0C reconciliation must know which path wrote a row | Blocks nothing immediately — but changing it after rows exist means rewriting them |
| **D10** | No QR token issued at visitor quick-add — issue at claim instead. Confirm? | Departs from §21.6's "never exists without a credential", which was written for self-service registration | Minor; `issue()` is idempotent either way |
| **D11** | **Is iCare R1a or R1b?** | R1a delays the Sunday cutover and needs `PRODUCTION_PATH.md` + the campaign router updated | Open since 2026-08-11 |

**D11 note:** answering **D12-A** does *not* answer D11. A synthetic pilot can run while iCare stays
R1b for production — that is the normal relationship between a rehearsal and a release, and keeping
them separate is what stops the pilot quietly becoming the cutover.

---

## 6. Risks I am not willing to carry silently

| | Risk | Owner decision needed |
|---|---|---|
| **R1** | **`/register` → `usergroup_id` 3 + every permission.** Live route (AUTH-001), no real captcha, no throttle, no validator. If the measurement shows it creates a user, **no tunnel may be opened until it is closed** — WAF rule *and* application fix | Measure first, then: fix immediately (the SEC-003 precedent, UP-012) or defer the pilot. **Not both.** |
| **R2** | **The missing `use App\Models\User` / `Carbon` may be the only thing preventing R1.** Tidying those imports converts a 500 into the live defect — CARD-001's exact shape | Rule that `RegisterController` is **frozen** until R1 is resolved, and that the route removal lands in the *same* change as any import fix |
| **R3** | **No private disk exists.** `attachPhoto()` has no working backend; an iCare group photo on the public disk is CARD-003 with ten identifiable members | **Recommended: pilot ships with photo upload disabled.** §8c requirement 6 goes untested this round. The alternative is building the private disk first: +2–3 sessions |
| **R4** | **A pilot tester who is `usergroup_id` 3 proves nothing about authorization** — `Gate::before` grants them everything. Every seeded leader must be usergroup 5 with an iCare leader membership | Confirm the pilot roster contains **no** church-admin account, and that this is asserted by the seeder, not by care |
| **R5** | **GRP-001: deleting a group hard-deletes every member's permission rows**, unscoped, unrecorded. iCare groups *are* `groups` rows | The pilot UI exposes **no** group delete. Confirm |
| **R6** | **SEC-002: attendance has no per-leader scope today.** §25.4's `IcareAuthorizer` would be the *first* implementation of it in this codebase | Accept that the pilot is where that control gets exercised for the first time — a reason to run the pilot, but it means an authorization bug there is a *new* bug, not a regression |
| **R7** | **The roster UI is unspecified and unpriced elsewhere** (step 8) | Blade on the existing admin layout, or something else? Answer before step 7 finishes |
| **R8** | **`git` backlog.** 32 characterization tests untracked; nothing committed since `3793b54` | Say go on the commits |
| **R9** | **The suite is not deterministic** without `cache:clear` — every green run is partly luck | Approve `CACHE_STORE=array` (rides on D3) |
| **R10** | **§25 has never been read by an iCare leader.** Its pre-tick, visitor and date rules are argued, not validated | Run D12-A-lite. One session |

### What this plan does NOT establish

Nothing here has been executed. The `/register` finding is **source reasoning by a session with no
PHP**, and step 3 exists because that is not evidence. The session counts are estimates built on the
four assumptions named in §2, and `PRODUCTION_PATH.md`'s own recorded correction — *counting feature
phases while ignoring the foundation under them* — is the exact error they are most likely to repeat.
The topology is drawn from `hosting.md` and has never been stood up. **The first `cloudflared`
connection is what turns §4 from a diagram into a fact.**
