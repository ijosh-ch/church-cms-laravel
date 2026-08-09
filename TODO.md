# TODO

> Item 1 is always the literal next action. Rewritten every session end.
> Hard cap: 1,500 tokens — archive completed items to `MEMORY.md`, do not accumulate them here.
> Every item names its `build.md` item number and its `PRD.md` line range where relevant.

**Active work package:** 0A — Baseline and Safety Net (`build.md` L202–228)
**Session:** 3 → 4

---

## Now — Session 3

1. **WP 0A item 4a — route + migration inventory.** Inventory the 5 route files (`web`, `admin`,
   `api`, `guestapi`, `console`) and all 93 migrations. Confirm whether QR/attendance check-in uses
   `temporarySignedRoute` — `DEPENDENCY_INVENTORY.md` flagged the `laravel/framework` signed-URL-
   confusion advisory (CVE range includes 10.50.2); 1 `signedRoute` call site found in Session 2,
   not yet traced to a route.

## Next — Work Package 0A (`build.md` L207–224)

| Session | Item(s) | Action |
|---|---|---|
| 4 | 4b | Inventory auth, roles, attendance, membership cards, exports, media, storage, queues, scheduler. |
| 6 | 9, 10 | Pin upstream SHA, record divergence, create `UPSTREAM.md` compatibility ledger and the ownership map. Formalizes the freeze already recorded in `UPSTREAM.md`'s "Pin decision — 2026-08-09" note. |
| 7 | 11 | Scaffold `custompackages/ifgf/church-operations` with path loading and auto-discovery. No product behavior. |
| 8 | 14 | Provider smoke tests from a clean checkout. |
| 9–11 | 6 | Characterization tests — auth/roles, then member/groups/events, then QR/attendance/exports/media. `tests/Feature/Admin/MemberImportCharacterizationTest.php` (Session 2b) is the first, not the last. Verify no Markdown-mail template interpolates unescaped user text (league/commonmark exposure check, deferred from Session 2). |
| 12 | 5, 8 | Disposable MySQL 8.4 test database + anonymized fixtures. No real member data. `phpunit.xml` currently points the suite at sqlite `:memory:` as a Session 2b stopgap — replace with the real fixture DB here, don't just add to it. |
| 13 | 7, 13 | CI workflow + read-only upstream merge rehearsal. Must include a PSR-4 autoload-warning check. **Fix the `nunomaduro/collision`/PHPUnit 10 version mismatch first** (Session 2b finding — `php artisan test` currently throws `RequirementsException`; `vendor/bin/phpunit` works as a workaround but CI needs `artisan test` or an equivalent direct `phpunit` invocation either way). |
| 14 | 12, gate | Branch topology (`main` / `ifgf/main` / `deploy`) + WP 0A exit gate review. |

## Blocked

- **WP 0B** — cannot start until the WP 0A exit gate passes. Findings staged in
  `EXECUTION_PLAN.md` §1.4 and `DEPENDENCY_INVENTORY.md`; `laravel/legacy-factories` is a hard
  blocker.

## Decisions awaiting the owner

- Production PHP pin: 8.4 recommended, subject to the extension audit *(WP 0B item 14)*.
- Vue 2 / laravel-mix 4 → Vite: proposed as a separate IFGF-neutral package *(WP 0B item 8)*.
  `DEPENDENCY_INVENTORY.md` lists the 14 npm packages this migration replaces wholesale.
- Whether `barryvdh/laravel-dompdf`/`dompdf/dompdf` (confirmed unused, 6 advisories, zero call
  sites) should be removed or kept for planned-but-unbuilt PDF export. **Ask before Session 4** —
  PRD scope decides this, not the audit.
- Which of `yarn.lock` / `package-lock.json` survives. `npm ci` is proven; recommend deleting
  `yarn.lock`. Defer until the Vite decision is in view so it is not decided twice.
- **New, Session 2b:** `app/Imports/UsersImport.php`'s `collection()` method dereferences an
  undefined `$request` variable as soon as any import file has at least one data row — a PHP
  `Error`, not caught by its own `catch (Exception $e)`. **Member import is currently broken for
  every real (non-empty) file**, independent of the `phpoffice/phpspreadsheet` upgrade. Not fixed
  in Session 2b (out of UP-005's "upgrade, don't fix business logic" scope) — see `UPSTREAM.md`
  UP-005 "Alternatives considered". **Ask whether this is worth an out-of-band fix before Session
  9–11's member/groups/events characterization work reaches it,** or whether it stays a documented
  known-broken path until then.
- **New, Session 2b:** `app/Traits/SendPushNotification.php` imports and instantiates
  `LaravelFCM\Message\OptionsBuilder`/`PayloadNotificationBuilder` — classes that were never in the
  autoloader even before UP-006 removed the orphaned `brozot/laravel-fcm` directory (confirmed via
  `class_exists()`, see `UPSTREAM.md` UP-006). Push notifications via this trait have been silently
  broken pre-existing. Rewriting it to call the already-installed
  `laravel-notification-channels/fcm` is a real fix, not a removal, so it needs its own
  characterization test + UPSTREAM.md entry — **ask whether to schedule this**, and if so where
  (Session 4's "auth, roles, attendance..." inventory touches related notification listeners).

## Resolved — 2026-08-09

- `phpoffice/phpspreadsheet`: characterized (`tests/Feature/Admin/MemberImportCharacterizationTest.php`),
  then upgraded 1.30.0 → 1.30.6. `UPSTREAM.md` UP-005. Committed `9045ce5`.
- `botman/botman`, `botman/driver-web`, and orphaned `custompackages/brozot/laravel-fcm` removed;
  confirmed the FCM push path was already unreachable before removal, so no characterization test
  was required. `UPSTREAM.md` UP-006. Committed `8c85781`.
- Upstream sync formally frozen at `d12c110` in `UPSTREAM.md`'s Baseline table; sync only after the
  WP 0A exit gate. Committed `e9596f5`.
- **Out-of-order, owner-requested:** WP 0A item 15 (`.graphifyignore`) pulled forward from Session
  5. `graph.json` 15MB → 7.7MB; `public/js/app.js` and other compiled/vendored assets no longer
  indexed; verified via a live query. See `CONTEXT.md` "Graphify" for detail and the one open,
  non-blocking `fail-closed` warning. Session 5's remaining scope (rebuild cadence, confirm no
  regressions once more code lands) is unaffected — this just closes the item early.
