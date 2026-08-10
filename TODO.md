# TODO

> Item 1 is always the literal next action. Rewritten every session end.
> Hard cap: 1,500 tokens — archive completed items to `MEMORY.md`, do not accumulate them here.
> Every item names its `build.md` item number and its `PRD.md` line range where relevant.

**Active work package:** 0B substantially complete → WP 0A gates 3 and 5 remain
**Session:** 4 → 5

---

## Now

1. **Finish the PHP 8.4 switch — needs Administrator.** `composer.json` is pinned to 8.4.24 and
   everything is verified under it, but bare `php` still resolves to 8.3.33 because `C:\php\8.3`
   is in the **Machine** PATH, which outranks the User PATH. In an **elevated** PowerShell:

   ```powershell
   $m = [Environment]::GetEnvironmentVariable('Path','Machine')
   $p = ($m -split ';') | Where-Object { $_ -and $_ -ne 'C:\php\8.3' }
   [Environment]::SetEnvironmentVariable('Path', (@('C:\php\8.4') + $p) -join ';', 'Machine')
   ```

   Then open a new terminal and confirm `php -v` reports 8.4.24. `C:\php\8.3` stays on disk as the
   rollback path — do **not** delete it until the app has run on 8.4 for a while.

2. **Fix the 8-minute test suite before writing any more tests.** `RefreshDatabase` replays all 93
   migrations per test class. `php artisan schema:dump` collapses them into one SQL file but needs
   `mysqldump`, which is not on PATH — it is in `C:\Program Files\MySQL\MySQL Server 8.4\bin`. Add
   that directory to PATH (same elevated step as item 1) and re-run. Doing item 3 first without
   this makes every future suite run take hours.

3. **WP 0A item 6 / gate 5 — characterization tests for the Release 1 surface.** Auth, roles and
   direct permissions, member profile, member QR / membership card, event attendance session
   open/scan/lock/unlock, group access, exports. **This is the gate that was skipped to reach
   Laravel 13** — the whole 10→13 upgrade currently rests on one import test. Nothing about the
   upgrade should be called "safe" until this exists. Do **not** write tests for CGSL, ministries,
   registration or Worship Night (Release 2, `PRODUCTION_PATH.md`).

4. **Re-verify the route count.** `route:list` = 730 vs `ROUTE_MIGRATION_INVENTORY.md`'s 812 static
   declarations; 12 are commented out, ~70 unexplained. Almost certainly pre-existing duplicate
   method+URI pairs, but there is **no pre-upgrade baseline to diff against**. Check out `086f33d`
   (pre-upgrade), run `route:list --json`, and diff. Correct the inventory either way — and while
   there, fix its "console.php defines no scheduled tasks" claim (`schedule:list` shows two).

## Next — remaining WP 0A / WP 0B closeout

| Item | Action |
|---|---|
| 0A item 11 (gate 3) | Scaffold `custompackages/ifgf/church-operations` with path loading + auto-discovery. No product behaviour. Nothing IFGF-specific can legally land until this exists. |
| C7 | Update `UPSTREAM.md` for every upstream-owned file this session touched (`app/Models/{User,Role,Permission,Userprofile,FeedbackMessage}.php`, `app/Http/Middleware/AdminOrPermission.php`, `app/Presenters/UserprofilePresenter.php`, `config/app.php`, `phpunit.xml`, `composer.json`), then run the **merge rehearsal** — never run to date. |
| 0A item 13 | Push the branches. **Nothing has been pushed this session** — 14 commits are local only. |
| 0A exit gate | Review, then formally close WP 0A/0B. |

## Blocked / deferred

- **Vite migration** (replaces Vue 2.6 / laravel-mix 4 / webpack 4). C5 audit done: the production
  build **still works** (exit 0), but 166 npm vulnerabilities remain and Vue 2 is EOL with an
  unfixable ReDoS advisory. Separate approved decision — must not be mixed into a framework
  upgrade. `DEPENDENCY_INVENTORY.md` lists the 14 packages it replaces.
- **Upstream sync** — still frozen at `d12c110`, 8 commits behind, by design until the WP 0A exit
  gate passes.

## Decisions awaiting the owner

- **`laracasts/presenter` replacement** — reimplemented in-house at `app/Support/Presenter/`
  (behaviour-identical, 4 files). Confirm this is the wanted long-term answer, or whether the
  presenter pattern should be retired into accessors instead.
- `app/Imports/UsersImport.php::collection()` crashes on any non-empty import (undefined
  `$request`). Still unfixed. Schedule it before member-import work.
- `app/Traits/SendPushNotification.php` references never-autoloaded `LaravelFCM\*` classes.
  Rewriting to the installed `laravel-notification-channels/fcm` is a real fix needing its own test
  + `UPSTREAM.md` entry.
- `app/Models/FeedbackMessage.php` points `$presenter` at a non-existent `App\Presenters\UserPresenter`.
- Whether `barryvdh/laravel-dompdf` (unused, zero call sites) should be removed.
- Which of `yarn.lock` / `package-lock.json` survives.
