# PRD Review — QR design and Laravel feasibility

**Reviewed:** `PRD.md` §5 Functional Requirements (L953–1165) against the ChurchCMS baseline
**Date:** 2026-08-09 · **Baseline:** `a262771`, Laravel 10.50.2
**Method:** Graphify graph rebuilt at `a262771` (15 MB → 7.3 MB after `.graphifyignore`), plus
targeted reads of the controllers and models it pointed to. No blind sweeps.

---

## Part 1 — The member QR

### 1.1 Three designs, and what each leaks

| | Legacy GAS | ChurchCMS upstream | PRD requirement |
|---|---|---|---|
| Payload | Prefilled form URL with **email + phone + name + iCare** | `User.name` (username) | Opaque public identifier + version |
| Opaque | No — plaintext PII | **No** — it is the username | Yes |
| Rotatable | No | **No** | Yes, by member or admin |
| Guessable | n/a | **Yes** — usernames are enumerable | No |
| Revocable | No | No | Yes |

Upstream is a real improvement on the legacy script — no contact data leaks. But it is still not
what the PRD asks for, for a reason that is easy to miss: **`Api/AttendanceController::scan()`
accepts `member_username` as an ordinary request parameter.** Nothing proves the scanner ever saw
a QR code. Any authenticated leader can check in any member by typing their username.

That is not automatically a flaw — manual attendance is a required fallback (FR-04.9). But it
means **the current QR contributes no security whatsoever**; it is a convenience encoding of a
guessable string. Worth being explicit about, because it changes what "securing the QR" means:
we are not hardening an existing control, we are adding one that does not exist yet.

### 1.2 Recommended design — and why it is *simpler*, not just safer

You asked whether Laravel can make this both more secure and simpler. It can, and the
simplification comes from **rejecting the two mechanisms people usually reach for first**.

**Do not use signed URLs.** `URL::temporarySignedRoute()` is Laravel's obvious-looking answer and
it is the wrong tool. A member QR is printed on a card or saved in a phone gallery — it must stay
valid for years. A temporary signature expires; a permanent signature (`URL::signedRoute()`)
cannot be revoked for one member without rotating `APP_KEY` for everyone. Signed URLs solve
tamper-proofing, which is not the problem here.

**Do not encode a JWT or encrypted blob.** Payload size drives QR density; a long payload makes
the code harder to scan in poor light, which is exactly the condition at a church entrance. And it
buys nothing — the server has a database.

**The whole design is one indexed column.**

```php
// ifgf_member_profiles
$table->char('qr_token', 32)->unique();      // Str::random(32)
$table->unsignedInteger('qr_version')->default(1);
$table->timestamp('qr_rotated_at')->nullable();
```

- **QR payload** is the 32-character token, nothing else. No name, no ID, no URL, no PII.
- **Opaque** — random, carries no information about the member.
- **Rotatable** — `Str::random(32)`, bump `qr_version`, done. The old code stops resolving
  immediately. That satisfies FR-02.7 in one line.
- **Not enumerable** — 32 chars of `Str::random` is ~190 bits.
- **Scan** resolves token → member, or 404. A wrong or rotated token reveals nothing.

Rate-limit the endpoint with Laravel's built-in `throttle` middleware and the design is finished.
No key management, no expiry arithmetic, no new dependency — `simplesoftwareio/simple-qrcode` is
already installed and already used by the membership-card views.

### 1.2b "Why not just the member ID?" — owner question, 2026-08-09

The instinct is right: with Laravel and a database, the QR **is** just a lookup key. The token is
not doing cryptography, it is doing `where(...)->first()`. Nothing more.

The only change is *which* key. Use a random one instead of the primary key.

| | `member.id` | `qr_token` |
|---|---|---|
| Server work | `find($id)` | `where('qr_token', $t)->first()` |
| Columns | 0 | 1 |
| Forgeable without seeing a QR | **Yes — print any number** | No |
| Revocable if a card is lost | **No — it is the primary key** | Yes, one `Str::random(32)` |
| Leaks roster size / join order | **Yes** | No |

**The decisive difference is forgery cost, not secrecy.**

Think about the actual threat. This QR is only used for attendance, so a leaked code has low harm
— someone could mark one member present at an event they are already attending. That is why the
design does not need signed URLs, encryption or expiry.

But a **sequential ID changes the attack from "photograph a specific person's card" to "count."**
Member ID 47 proves 1–46 exist. Anyone could generate a valid QR for every member in the church
without ever seeing a single card, and mark the whole roster present. A random token means an
attacker must physically obtain each specific code.

It is also forbidden outright by `build.md` TECHNICAL BASELINE 7 — "do not expose sequential IDs in
public URLs or QR payloads" — and it makes FR-02.7's rotation requirement impossible, since you
cannot reissue a primary key.

**So: same simplicity, same one lookup, one extra column.** `qr_token char(32) unique`. That is
the whole difference.

*(A UUID column via Laravel's `HasUuids` trait would also work and is more idiomatic. `Str::random(32)`
produces a shorter payload, which keeps the QR less dense and easier to scan in poor entrance
lighting. Either is acceptable — prefer the shorter one.)*

### 1.3 Optional hardening — only if you want it

If a database dump yielding working QR tokens is in your threat model, use the pattern Laravel
Sanctum already uses for API tokens: store `hash('sha256', $token)` in an indexed column for
lookup, and keep the displayable copy in a second column with Laravel's `encrypted` cast so a dump
alone is useless without `APP_KEY`.

**My recommendation is to skip this initially.** It doubles the columns and adds a decrypt on every
member-portal render, to defend against an attacker who already has your database — at which point
they have the members' actual contact details anyway, which is strictly worse than their QR codes.
Add it later if the privacy review (WP 0D) asks for it; the migration is additive.

### 1.4 What this costs at cutover

Every existing member QR is reissued. Previously printed or saved codes stop working by design.
Flagged in `PRD_OPEN_QUESTIONS.md` as escalation C1 — it needs a member communication plan in
Phase 1E, not a footnote.

### 1.5 Suggested PRD amendments

| Location | Change |
|---|---|
| FR-02.7 | State the mechanism: opaque random token, unique index, `qr_version`, rotation by member or admin. Explicitly rule out signed URLs and self-describing payloads, with the printed-card reason. |
| FR-04 | Add: the scan endpoint resolves an opaque token, never a username or sequential ID, and is rate-limited. |
| FR-04.9 | Clarify that manual search is a *separate authorization path*, not a QR bypass — currently the same endpoint serves both. |
| §4.8 media | `Api/AttendanceController::scan()` returns `avatar_url` from `Storage::disk('public')`. This contradicts invariant 30 (private storage, signed access). Note it as an upstream-owned compatibility fix. |

---

## Part 2 — Can the PRD be realised on this baseline?

**Yes — and FR-04 in particular is far closer to done than the PRD assumes.** The PRD reads as
though attendance is new build. It is not.

### 2.1 What already exists

`EventAttendanceSession` (`church_id`, `event_id`, `attendance_date`, `opened_by`, `locked_at`,
`locked_by`) and `EventAttendee` (`session_id`, `church_id`, `event_id`, `user_id`, `scanned_at`,
`scanned_by`), plus:

| Controller | Methods |
|---|---|
| `Api/AttendanceController` | `myEvents`, `openSession`, `scan`, `lock`, `sessionReport` |
| `Admin/EventAttendanceController` | `sessions`, `openSession`, `showSession`, `lock`, `unlock`, `export`, `manageManagers`, `storeManager`, `removeManager`, `checkin`, `searchMember`, `markAttendee`, `removeAttendee` |

Behaviours already implemented that the PRD lists as requirements:

- **FR-04.7 duplicate scan** — `scan()` returns HTTP 409 `already_checked_in` with the existing
  record. Already correct.
- **FR-04.8 locked occurrences reject writes** — `locked_at` check returns 403. `unlock()` exists.
- **FR-04.9 manual fallback** — `searchMember` + `markAttendee`.
- **FR-04.11 leader scope** — `EventManager` model and `manageManagers`/`storeManager` are the
  assignment mechanism the PRD asks for.
- **FR-04.10 partial audit** — `scanned_by`, `opened_by`, `locked_by` already capture the actor.
- Church scoping is enforced on every path.

### 2.2 Feasibility matrix

| FR | Verdict | Existing anchor | Gap |
|---|---|---|---|
| **FR-01** Member registry | **Adapt** | `MemberController`, `MemberAddController`, `MemberEditController`, `ImportMemberController`, `ExportMemberController`, `Userprofile` | Merge, duplicate detection, status lifecycle, pastoral notes, private media |
| **FR-02** Registration + QR | **Adapt** | `Auth/RegisterController`, `MembershipCardController` ×2, `simple-qrcode` | Opaque token (Part 1), member portal scoping, consent |
| **FR-03** Events + occurrences | **Extend** | `Events`, `EventsController`, `EventManager` | RRULE, occurrence generation, delivery mode, audience types, series-edit scope |
| **FR-04** Attendance engine | **Extend — ~60% present** | Full session/attendee stack above | `status`, `participation_mode`, `capture_method` columns; `AttendanceRecorder` as sole write path; mandatory reopen reason |
| **FR-05** iCare | **Adapt** | `Group`, `GroupCategory`, `GroupLink`, `GroupsController`, `GroupLinksController` | Effective dating, one-active-primary constraint, visitors, transfer transaction |
| **FR-06** CGSL | **New** | — | Entire domain |
| **FR-07** Ministries | **New** | — | Entire domain |
| **FR-08** Birthday Calendar | **New** | `BirthdayController` (dashboard route only) | Google adapter, ACL tracking, viewer access, sync command |
| **FR-09** Worship Night / Zoom | **New** | — | Import service, matching, idempotent commit |
| **FR-10** Reports + export | **Adapt** | `ExportMemberController`, `maatwebsite/excel` | Dashboards, trends, inactive-risk, export audit |
| **FR-11** Roles + privacy | **Replace** | `Role`, `Permission`, `PermissionUser`, `Usergroup`, Laratrust | 3-role model, `RoleAssignmentService`, retire `usergroup_id` across **33 files** |
| **FR-12** Migration | **New** | — | Whole import pipeline |
| **FR-13** Registration | **New** | — | Capacity, waitlist, promotion |
| **FR-14** Biometric | **New, separate repo** | — | Phase 2 |

Roughly: **5 adapt, 3 extend, 1 replace, 6 new.** No FR is infeasible on this baseline, and none
requires abandoning the fork.

### 2.3 The three real risks

1. **FR-11 is the hard one, not FR-14.** `usergroup_id` appears in **33 files** across `app/`,
   `routes/` and `database/migrations/`. Every one is an authorization path that must be replaced
   without a window where a legacy route grants broader access than the new role model. This is
   the largest correctness risk in Phase 0, and it is invisible in the PRD's own prose.

2. **`EventAttendee` is presence-only.** There is no `status` column, so absent and excused have no
   representation. FR-04.6's closed-roster snapshot (iCare, CGSL) cannot be expressed today. This
   is an additive migration, but it changes the meaning of every existing row — the absence of a
   row currently means "not recorded", and afterwards must not be confused with "absent".

3. **One session per event per date.** `EventAttendanceSession` keys on `attendance_date`, while
   FR-03.2 requires multiple same-day occurrences. `build.md` WP 0C item 5 already anticipates
   this — remove the date-only uniqueness, enforce event + exact `starts_at` in
   `ifgf_event_occurrences`. Confirmed as a real constraint, not a hypothetical.

### 2.4 Recommended PRD amendments beyond the QR

| Location | Change |
|---|---|
| §2 Evidence inventory | Record that `EventAttendanceSession`/`EventAttendee` plus the two attendance controllers already implement duplicate-scan, locking, manual fallback and manager assignment. The PRD currently understates the baseline, which inflates the Phase 1B estimate. |
| FR-04.1 | Note that `AttendanceRecorder` **replaces** direct writes in `Api/AttendanceController::scan()` and `Admin/EventAttendanceController::markAttendee()` — name the two call sites so the cutover is checkable. |
| FR-04.2 | Add explicitly: `EventAttendee` has no `status` today; absent/excused is an additive migration, and a missing row must not be read as absence. |
| FR-11.9 | Replace "usergroup_id … are migrated" with the measured number — 33 files — so the estimate reflects the work. |
| §13.2 Q3 | Resolve the L402 / L973 home-branch contradiction (`PRD_OPEN_QUESTIONS.md`). |
| FR-02.2 | Follow-up preference is in the required minimum but was **never captured** by the legacy form (0% filled). Drop it or accept every imported member starts unset. |

---

## Part 3 — Graphify, honestly

The rebuild worked: 15 MB → 7.3 MB, 6,919 nodes, 1,452 source files, built at `a262771`.

But **it is still not a good code-search index for this repository.** The top files by node count
are `package.json` (116), `_design_reference/*.md` (95), `composer.json` (82), `PRD.md` (80),
`_ai/*.md` (63). Markdown and manifests dominate; `app/Models/User.php` is the first source file at
48. Some `app.js` nodes survive the ignore rules.

For this review, the graph was useful for **orientation** — community labels pointed at
`EventsController`, `User`, `Church` as hubs — but every actual finding came from targeted `grep`
into the files it named. That is the documented search order in `CLAUDE.md` working as intended,
and it is worth being clear that step 1 narrows the search rather than answering the question.

**Suggestion:** extend `.graphifyignore` to `_ai/`, `_design_reference/`, `_code_reference/`, and
the root planning `.md` files. Those are ~400 nodes of prose competing with source code in every
query. The graph indexing `PRD.md` is actively counterproductive — it returns the requirement text
when you are searching for the implementation.
