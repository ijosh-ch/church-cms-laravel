# TODO

> Item 1 is always the literal next action. Rewritten every session end.
> Hard cap: 1,500 tokens — archive completed items to `MEMORY.md`, do not accumulate them here.
> Every item names its `build.md` item number and its `PRD.md` line range where relevant.

**Active work package:** WP 0A closure · **Session:** 2026-08-21 — suites 9 and 11 characterized
**Exit gate:** ❌ NOT PASSED — **4 of 7 met, 0 partial, 3 not met** (`WP0A_EXIT_GATE.md`), unchanged.
Characterization is the only remaining *work* (62 of 80–120, 4 of 11 suites); 4 and 7 are one owner
reading session and the pack is written (`WP0A_OWNER_REVIEW.md`).
**WP 0C must not begin** (`build.md` OPERATING CONTRACT 10). **`SCHEMA_SPEC.md` at the repo root is
WP 0C material — untracked, do not action it.**

---

## Before anything: start MySQL

No registered Windows service. Nothing listens on 3306 after a reboot and every test errors
`SQLSTATE[HY000] [2002]`, which looks like a code failure and is not. Run in background:

```
& "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --defaults-file="C:\ProgramData\MySQL\MySQL Server 8.4\my.ini" --console
```

Accepts connections ~4s later. Registering it permanently needs elevation — owner question below.

## Now

1. **Characterization — 5 of 11 suites remain, 3 partial.** WP 0A item 6, gate 5. **The only thing
   blocking the exit gate that costs real time** (2–3 sessions). Currently **62 characterization
   tests** (73 total incl. package smoke) against a **80–120** target. Last full run 2026-08-21:
   **73 passed, 250 assertions, 0 failed, 0 skipped, 2m41s**; package suite 3 passed, 10 assertions.

   **Not started:** 5 QR/card · 6 Groups · 7 Event management · 8 Birthday · 10 Queues.
   **Partial:** 1 Auth (owes password reset, email verification, session lifetime, throttling) ·
   3 Member profile (owes create/edit/delete/export behaviour) · 4 Attendance (owes `searchMember`,
   `removeAttendee`).
   **Done 2026-08-21:** 9 Exports (11 tests) · 11 Private media (9 tests) · cross-cutting date-cast
   regression (5 tests). Both PII surfaces are now covered.

   ⚠ **Suite 8 (birthday) depends on REG-001** — it derives from `Userprofile::date_of_birth`, one of
   the 21 columns that silently stopped casting. Expect stringly-typed date handling there.
   Characterize what it does; do not fix it.

   **Method — standing, not a suggestion.** Run a diagnostic printing status, `Location` and state
   for several inputs *side by side* before writing one assertion. Every file written
   diagnostic-first has needed zero correction; **2026-08-21: 23 tests, three files, zero
   corrections**, and the session's only first-run failure was the one permission name guessed from a
   route name rather than measured.

   **Traps that already cost commits:**
   - Any test rendering an admin view must seed `settings.*` first — copy
     `MemberProfileCharacterizationTest::seedRuntimeSettings()`. Otherwise every admin page 500s on
     `htmlspecialchars(): … UrlGenerator given`, which looks exactly like an application bug.
   - Any `/admin/*` route sits behind **both** legacy gates (`RouteServiceProvider` applies
     `['web','auth','churchadmin']` to all of `routes/admin.php`). **Use usergroup 4.** Denial is
     **401**, not 403. Read `RolePermissionCharacterizationTest`'s docblock first.
   - **Check each planned suite has a subject before budgeting it.** Several `TESTING_PLAN.md`
     entries describe FR-11 behaviour that does not exist and cannot be characterized.
   - Assert environment-dependent behaviour **against the environment**, not one machine's defaults.
   - **Never assert on `$response->getContent()` for a CSV export** — `League\Csv\Writer::output()`
     echoes to the SAPI and returns null, so the body is always `''`. Capture the output buffer;
     `ExportCharacterizationTest::capture()` is the pattern.

2. **OWNER: one reading session closes criteria 4 and 7.** Both are review, not work.
   **The pack is written — read `WP0A_OWNER_REVIEW.md`.** It carries all eleven ledger entries one
   line each, both unclassified files with measured proposed classifications, and the compatibility
   matrix as-is. It asks exactly three decisions and deliberately marks neither criterion met:
   - **▶ D1** — approve two UP-007 restatements: conflict risk as **measured** clean (not predicted
     High), and status as **partially** verified (`AdminOrPermission`, `User` covered; four files
     not).
   - **▶ D2** — `app/Providers/RouteServiceProvider.php`: classify as **`monitor`**. Measured
     2026-08-21 as **byte-identical to both `d12c110` and `800c29f`** — the fork never modified it,
     so a UP-nnn entry would assert a change that does not exist. It is still authorization-critical
     and must be re-read after every upstream sync.
   - **▶ D3** — `phpunit.xml`: give it **UP-012** (recommended) rather than extending UP-005. It is
     *not* unrecorded — UP-005 lists it — but that row's "additive env only" description went stale
     at `f60f1a4`, and it is 29 insertions / **31 deletions** against the pin.

## Next — remaining WP 0A

| Item | Action |
|---|---|
| **UP-008** | QR `format('png')` → `format('svg')`. **7 call sites, not 8** — upstream deletes `idcard.blade.php`'s call itself, so **do not hand-edit that file**. Re-derive the list before starting. Owner decision 2026-08-10 #1. |
| **Step 5** | Merge `contrib/laravel-supported-platform` → `ifgf/main`. **Only after characterization AND the owner review.** 51 commits. The one hard-to-undo action; re-run both suites after and report counts. Correctly NOT performed 2026-08-21. |
| Route count | `route:list` = 730 vs the inventory's 812; ~70 unexplained, no pre-upgrade baseline. Check out `086f33d`, `route:list --json`, diff. Fix the inventory's "no scheduled tasks" claim while there. |

## Blocked / deferred

- **Vite migration** — approved deferral. 166 npm vulnerabilities; Vue 2 EOL. Never `npm audit fix`.
- **Upstream sync** — reviewed pin `d12c110`; ref at `800c29f`. Frozen until the exit gate passes.

## Decisions taken — do not re-ask

| # | Decision |
|---|---|
| 2026-08-21 #2 | **SEC-003 fixed immediately** rather than left characterized — owner override of the standing capture-what-is method, once the endpoint was confirmed reachable by every sub-admin. **UP-012.** Two lines per file: type-hint `EditUserProfileImgRequest`, which already existed and was already wired to the API twin. |
| 2026-08-21 #1 | **SEC-003 is stored XSS, NOT RCE.** The RCE rating came from `UploadedFile::fake()`, which derives MIME from the filename. Real PHP source is `text/x-php`, gets **no extension**, and cannot match a `\.php$` handler. `.svg`/`.html` are the real vector — served from `/storage/…` on the app's own origin. **Do not re-cite the RCE claim.** |
| 2026-08-18 | **UP-011 applied and closed** — `Payaccount/` → `payaccount/`. First Linux `npm run production` success. |
| 2026-08-15 | **Timestamps: UTC at rest.** PRD wins. Accepted cost: a 00:00–08:00 Taipei service files under the previous UTC day. Pinned by a `test_documents_*` test. UP-009. |
| 2026-08-10 #1 | **QR: `format('svg')`.** No imagick anywhere. Needs UP-008. |
| 2026-08-10 #2 | **Upstream: rehearse only, keep the `d12c110` pin.** |
| 2026-08-10 #3 | **Keep `app/Support/Presenter/`.** UP-007. |
| 2026-08-10 #4 | **Characterize all group access incl. `usergroup_id` bypasses**, each `test_documents_defect_*` + a written finding. |

## Decisions awaiting the owner

- **SEC-003 residue (new 2026-08-21)** — the two avatar endpoints are fixed (UP-012), but
  `Common::uploadFile()` has **47 call sites** and the rest are unvalidated and **unmeasured**.
  Several take a plain `Request` the same way. Needs its own entry, and should be sized against
  suite 11's finding that **no private disk exists at all** — an allow-list across 47 call sites is a
  worse answer than moving member media off a public disk. **Measure before scoping.**
- **REG-001 (new 2026-08-21)** — `protected $dates` was removed in Laravel 10; this app declares it
  on 37 models and **21 columns across 9 models silently return strings**. A WP 0B regression. The
  attendance CSV export has been dead since the upgrade because of it. Fix is mechanical (`$casts`,
  pattern already in `app/Models/Post.php`) but it is a behaviour change and needs its own commit.
  Pinned by `DateCastRegressionCharacterizationTest`, which includes a countdown assertion.

- **Register MySQL 8.4 as a Windows service?** One elevated command; until then every session starts it by hand.
- **SEC-001** — `usergroup_id == 3` bypasses every permission check (one middleware alias). FR-11 must map legacy groups before it can be removed.
- **SEC-002** — attendance has no per-leader scope; `event_managers` exists but is never consulted. FR-11 must **build** the control, not adjust it.
- **AUTH-001** — `Auth::routes()` is called twice, so registration is live despite `'register' => false`. Check `RegisterController` on an unauthenticated POST **before any public deployment**.
- `app/Imports/UsersImport.php::collection()` crashes on any non-empty import.
- `app/Traits/SendPushNotification.php` references never-autoloaded `LaravelFCM\*` classes.
- `app/Models/FeedbackMessage.php` points `$presenter` at a non-existent `App\Presenters\UserPresenter`.
- Whether `barryvdh/laravel-dompdf` (zero call sites) should be removed; which of `yarn.lock` / `package-lock.json` survives.
