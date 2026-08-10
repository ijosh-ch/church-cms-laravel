# Route and Migration Inventory

**Work Package 0A, item 4a** · Session 3 · 2026-08-09
**Baseline:** `a262771` on `codex-PRD`, upstream pinned at `d12c110`
**Method:** static analysis only. No PHP executed; nothing here depends on a booted application.

Companion to `DEPENDENCY_INVENTORY.md` (item 3). Feeds WP 0C (schema integrity), Phase 1A
(member registry), Phase 1B (events and attendance), and the characterization sessions 9–11.

---

## 1. Routes — 812 across 4 files

| File | GET | POST | DELETE | PATCH | Total | Distinct controllers |
|---|---:|---:|---:|---:|---:|---:|
| `admin.php` | 316 | 171 | 43 | 1 | **531** | 88 |
| `web.php` | 110 | 60 | 7 | — | **177** | 39 |
| `api.php` | 46 | 33 | — | — | **79** | 27 |
| `guestapi.php` | 20 | 5 | — | — | **25** | 15 |
| `channels.php` | — | — | — | — | 0 | broadcast auth only |
| `console.php` | — | — | — | — | 0 | no scheduled commands defined |
| **Total** | **492** | **269** | **50** | **1** | **812** | |

**`console.php` defines no scheduled tasks.** Anything the PRD expects to run on a schedule —
birthday Calendar sync, occurrence generation, retention jobs — has no existing counterpart.
Those are new build, not modification.

### Authorization posture per surface

| Surface | Gate | Notes |
|---|---|---|
| `admin.php` | `admingroup` middleware + `permission:*` | ~20 distinct Laratrust permission strings: `manage-cms`, `read-members`, `read-payments`, `read-funds`, `read-contacts`, `manage-email-blaster`, `read-events`, `read-gallery`, … |
| `web.php` | mixed — `auth`, `member`, `churchmember`, `permission:read-members`, plus a `nova`/`nova-api` group | The most heterogeneous surface. Also carries a `namespace` group, i.e. legacy string-based controller resolution. |
| `api.php` | `auth:sanctum`, prefix `v1` | |
| `guestapi.php` | prefix `v2`, mostly unauthenticated, `throttle:10,1` / `throttle:20,1` + `webguest` | Public surface. |

**Rate limiting is sparse — 11 throttle declarations across 812 routes.** `build.md` SECURITY 5
requires rate limits on public registration, activation, QR, authentication, import and webhook
paths. Most of those are currently unthrottled. WP 0A item 6 should characterize the current
behaviour before Phase 1A changes it.

### Legacy authorization is live

`usergroup_id` appears in **33 files** across `app/`, `routes/` and `database/migrations/`, and
the `user_group` table has its own create migration (`2024_01_01_000005`). This is the full
inventory surface for `build.md` WP 0C item 9 and ARCHITECTURAL INVARIANT 22 — every one of those
33 files must be replaced before the legacy column and table can be retired.

### Signed URLs — question settled

`DEPENDENCY_INVENTORY.md` flagged the `laravel/framework` signed-URL-confusion advisory (CVE range
includes 10.50.2) and left one `signedRoute` call site untraced. Traced:

| Site | Purpose |
|---|---|
| `app/Mail/VerifyEmail.php:62` | `URL::temporarySignedRoute()` — email verification link |
| `app/Http/Controllers/Auth/VerificationController.php:36` | `$this->middleware('signed')->only('verify')` |
| `app/Http/Kernel.php:69` | `'signed' => ValidateSignature::class` registration |

**Exposure is limited to email verification.** QR and membership cards do not use signed URLs —
`Admin/MembershipCardController.php` and `Member/MembershipCardController.php` contain no signed
URL usage. So the advisory does not touch attendance check-in.

That is also a **Phase 1A finding in its own right**: PRD requires an opaque, rotatable member QR,
and the current membership-card QR is not signed and not rotatable. Design work, not a fix.

---

## 2. Migrations — 93 files, and the history is not real

| Metric | Value |
|---|---|
| Migration files | 93 |
| `Schema::create` | 91 |
| `Schema::dropIfExists` (in `down()`) | 91 |
| **`Schema::table` (alter)** | **0** |
| Distinct tables | 91 |
| Dated `2024_01_01_*` | 90 |
| Dated 2026 | 3 |

**There are zero alter migrations.** Ninety of the ninety-three share the timestamp
`2024_01_01_*`, which means this is a **squashed or regenerated migration set**, not an
accumulated history. One create migration per table, no evolution recorded.

Consequences:

- `build.md`'s prohibition on editing historical upstream migrations still holds, but there is
  little history to protect — the risk is not *losing* history, it is that **the migrations do
  not describe how production actually got to its current shape**. Any anonymized legacy snapshot
  may not match what these migrations produce. WP 0C's exit gate requires migrations to work
  against both a fresh database and an anonymized legacy snapshot; **expect divergence and budget
  for it**.
- Every WP 0C change is necessarily a new expand → backfill → verify → contract migration. There
  is no existing alter migration to model the house style on.

The three 2026 migrations are the only genuinely recent schema work:

```
2026_05_22_201909_create_group_posts_table.php
2026_06_09_000001_create_donations_table.php
2026_06_09_000002_add_online_payment_gateways.php
```

`add_online_payment_gateways` is the Stripe work that caused UP-001's lockfile desync.

---

## 3. Two confirmed WP 0C landmines

Both were predicted by `build.md`. Both are now confirmed with file and line.

### 3.1 Deleting a user destroys their attendance history

`database/migrations/2024_01_01_000030_create_event_attendees_table.php`:

```php
line 14  $table->foreign('session_id')->references('id')->on('event_attendance_sessions')->onDelete('cascade');
line 18  $table->foreign('event_id')  ->references('id')->on('events')                   ->onDelete('cascade');
line 20  $table->foreign('user_id')   ->references('id')->on('users')                    ->onDelete('cascade');   // <—
line 23  $table->foreign('scanned_by')->references('id')->on('users')                    ->nullOnDelete();
```

Line 20 directly violates **PRD invariant 14** — historical attendance must survive removal of an
authentication account. It is `build.md` **WP 0C item 4**.

Mitigating factor: `users` already has `softDeletes()`
(`2024_01_01_000006_create_users_table.php:33`, and `App\Models\User` uses the trait). So the
normal deletion path sets `deleted_at` and does *not* trigger the cascade. The cascade fires only
on a **hard** delete — `forceDelete()`, a manual SQL `DELETE`, or a future GDPR erasure
implementation. That is precisely the erasure path WP 0D has to design, so the two work packages
must be solved together rather than independently.

Line 18 is a second, separate exposure: **deleting an event destroys all attendance for it**,
regardless of soft deletes on users.

### 3.2 `userprofiles.user_id` has no unique constraint

`database/migrations/*create_userprofiles_table.php`:

```php
line 15  $table->integer('user_id')->unsigned();
line 16  $table->foreign('user_id')->references('id')->on('users');
```

A foreign key, but **no `->unique()`**. Nothing prevents multiple profile rows per user, which
contradicts **ARCHITECTURAL INVARIANT 4** and is `build.md` **WP 0C item 3**. The item's wording —
"enforce unique `userprofiles.user_id` after detecting and reviewing duplicates" — anticipates
that duplicates already exist in production. Detection must run against the anonymized snapshot
before the constraint is added.

### 3.3 `role_user` — partially aligned already

`2024_01_01_000019_create_role_user_table.php`:

```php
$table->primary(['user_id', 'role_id', 'user_type']);
$table->foreign('role_id')->references('id')->on('roles')->onUpdate('cascade')->onDelete('cascade');
```

The composite primary key exists, but WP 0C item 8 requires a unique on **`user_id` + `user_type`**
— one role per user, not one row per role per user. The current key permits a user holding many
roles simultaneously, which INVARIANT 5 forbids. Also note there is **no foreign key on
`user_id`**, only on `role_id`.

---

## 4. Thirteen migrations declare CASCADE deletes

Full list, for WP 0C item 4's audit:

```
000019_create_role_user_table              000063_create_prayers_table
000021_create_permission_role_table        000064_create_prayer_participants_table
000028_create_event_managers_table         000068_create_send_mail_table
000029_create_event_attendance_sessions    000071_create_campaign_table
000030_create_event_attendees              000077_create_get_response_table
000041_create_page_versions_table          000078_create_webhooks_table
000062_create_prayer_categories_table
```

Only `event_attendance_sessions` and `event_attendees` carry pastoral history. The rest are
join tables, versioning, or marketing data where cascade is defensible. **Do not blanket-replace
all thirteen** — that would be churn against upstream-owned files for no invariant gain.

---

## 5. Feeds forward

| Finding | Consumed by |
|---|---|
| 812 routes, 88 admin controllers | Sessions 9–11 characterization scope |
| `console.php` empty — no scheduled tasks exist | Phase 1D Calendar sync, Phase 1B occurrence generation |
| 33 files carry `usergroup_id` | WP 0C item 9, INVARIANT 22 |
| 11 throttle declarations across 812 routes | WP 0A item 6, SECURITY RULE 5 |
| Signed URLs = email verification only | Advisory closed; QR design is Phase 1A work |
| Zero alter migrations, squashed history | WP 0C exit gate — snapshot divergence risk |
| `event_attendees.user_id` CASCADE | WP 0C item 4 + WP 0D erasure design |
| `event_attendees.event_id` CASCADE | WP 0C item 4 |
| `userprofiles.user_id` not unique | WP 0C item 3 |
| `role_user` missing user_id+user_type unique and user_id FK | WP 0C item 8 |

## 6. Not done in this session

- Item 4b — auth, roles, attendance, membership cards, exports, media, storage, queues, scheduler.
- Route-level middleware resolution was read from `Route::group` declarations only. Middleware
  applied inside controller constructors is **not** captured here and must be checked during
  characterization, since `VerificationController` proves that pattern is in use.
- No route was executed. Everything above is static analysis and should be confirmed against
  `php artisan route:list` in the next session that has a booted application.
