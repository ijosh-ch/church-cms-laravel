# TODO

> Item 1 is always the literal next action. Rewritten every session end.
> Hard cap: 1,500 tokens — archive completed items to `MEMORY.md`, do not accumulate them here.
> Every item names its `build.md` item number and its `PRD.md` line range where relevant.

**Active work package:** WP 0A closure · **Session:** 2026-08-18 — CI green, UP-011 closed
**Exit gate:** ❌ NOT PASSED — **4 of 7 met, 1 partial, 2 not met** (`WP0A_EXIT_GATE.md`).
Characterization is the only remaining *work*; 4 and 7 are one owner reading session.
**WP 0C must not begin** (`build.md` OPERATING CONTRACT 10).

---

## Before anything: start MySQL

No registered Windows service. Nothing listens on 3306 after a reboot and every test errors
`SQLSTATE[HY000] [2002]`, which looks like a code failure and is not. Run in background:

```
& "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysqld.exe" --defaults-file="C:\ProgramData\MySQL\MySQL Server 8.4\my.ini" --console
```

Accepts connections ~4s later. Registering it permanently needs elevation — owner question below.

## Now

1. **Characterization — 7 of 11 suites remain.** WP 0A item 6, gate 5. **This is the only thing
   blocking the exit gate that costs real time** (3–4 sessions). Currently **37 characterization
   tests** (48 total incl. package smoke) against a **80–120** target, green locally and on CI.

   **Not started:** 5 QR/card · 6 Groups · 7 Event management · 8 Birthday · 9 Exports · 10 Queues ·
   11 Private media. **Exports and private media are wholly uncharacterized and both touch member
   PII** — take them before the medium-priority ones.
   **Partial:** 1 Auth (owes password reset, email verification, session lifetime, throttling) ·
   3 Member profile (owes create/edit/delete/export behaviour) · 4 Attendance (owes `searchMember`,
   `removeAttendee`).

   **Method — standing, not a suggestion.** Run a diagnostic printing status, `Location` and state
   for several inputs *side by side* before writing one assertion. Three separate times this project
   asserted against the harness instead of the application; every file written diagnostic-first has
   needed zero correction.

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

2. **OWNER: one reading session closes criteria 4 and 7.** Both are review, not work.
   - **Criterion 7** — read `UPGRADE_COMPATIBILITY_MATRIX.md` (assembled 2026-08-18). Its §7 lists
     what it deliberately does **not** establish; read that section first.
   - **Criterion 4** — review `UPSTREAM.md` UP-001…UP-011. While there: classify
     `app/Providers/RouteServiceProvider.php` (in "Not yet classified"; Step 6 proved it applies
     `churchadmin` to all of `routes/admin.php`), fold `phpunit.xml` into an entry, and restate
     UP-007's **predicted** High conflict risk as the **measured** clean result against `800c29f`.

## Next — remaining WP 0A

| Item | Action |
|---|---|
| **UP-008** | QR `format('png')` → `format('svg')`. **7 call sites, not 8** — upstream deletes `idcard.blade.php`'s call itself, so **do not hand-edit that file**. Re-derive the list before starting. Owner decision 2026-08-10 #1. |
| **Step 5** | Merge `contrib/laravel-supported-platform` → `ifgf/main`. **Only after characterization.** The one hard-to-undo action; re-run both suites after. |
| Route count | `route:list` = 730 vs the inventory's 812; ~70 unexplained, no pre-upgrade baseline. Check out `086f33d`, `route:list --json`, diff. Fix the inventory's "no scheduled tasks" claim while there. |

## Blocked / deferred

- **Vite migration** — approved deferral. 166 npm vulnerabilities; Vue 2 EOL. Never `npm audit fix`.
- **Upstream sync** — reviewed pin `d12c110`; ref at `800c29f`. Frozen until the exit gate passes.

## Decisions taken — do not re-ask

| # | Decision |
|---|---|
| 2026-08-18 | **UP-011 applied and closed** — `Payaccount/` → `payaccount/`. First Linux `npm run production` success. |
| 2026-08-15 | **Timestamps: UTC at rest.** PRD wins. Accepted cost: a 00:00–08:00 Taipei service files under the previous UTC day. Pinned by a `test_documents_*` test. UP-009. |
| 2026-08-10 #1 | **QR: `format('svg')`.** No imagick anywhere. Needs UP-008. |
| 2026-08-10 #2 | **Upstream: rehearse only, keep the `d12c110` pin.** |
| 2026-08-10 #3 | **Keep `app/Support/Presenter/`.** UP-007. |
| 2026-08-10 #4 | **Characterize all group access incl. `usergroup_id` bypasses**, each `test_documents_defect_*` + a written finding. |

## Decisions awaiting the owner

- **Register MySQL 8.4 as a Windows service?** One elevated command; until then every session starts it by hand.
- **SEC-001** — `usergroup_id == 3` bypasses every permission check (one middleware alias). FR-11 must map legacy groups before it can be removed.
- **SEC-002** — attendance has no per-leader scope; `event_managers` exists but is never consulted. FR-11 must **build** the control, not adjust it.
- **AUTH-001** — `Auth::routes()` is called twice, so registration is live despite `'register' => false`. Check `RegisterController` on an unauthenticated POST **before any public deployment**.
- `app/Imports/UsersImport.php::collection()` crashes on any non-empty import.
- `app/Traits/SendPushNotification.php` references never-autoloaded `LaravelFCM\*` classes.
- `app/Models/FeedbackMessage.php` points `$presenter` at a non-existent `App\Presenters\UserPresenter`.
- Whether `barryvdh/laravel-dompdf` (zero call sites) should be removed; which of `yarn.lock` / `package-lock.json` survives.
