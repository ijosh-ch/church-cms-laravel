# Upgrade prompt — PHP 8.4, MySQL 8.4 LTS, Laravel 13

Paste the block below into Claude Code, running in this repository on the Windows host.

It runs three phases. **Phase A is fully automatic.** Phases B and C are automatic but stop at
defined gates, because an unverifiable upgrade is worse than no upgrade.

---

```
Read CLAUDE.md, CONTEXT.md, TODO.md, and the last 5 entries of MEMORY.md first.

GOAL: upgrade this machine and this project to PHP 8.4, MySQL 8.4 LTS, and Laravel 13.
Work through the phases in order. Do not skip ahead. Report at the end of each phase.

===========================================================================
PHASE A — machine toolchain. Fully automatic.
===========================================================================

A1. Install PHP 8.4 SIDE BY SIDE with the existing 8.3.33. Do NOT remove 8.3.
    - Download from windows.php.net directly. winget's PHP manifests go stale and 404;
      this already happened on 2026-08-08 (MEMORY.md). Probe candidate URLs for the
      current 8.4 patch, NTS then TS, releases/ then releases/archives/, take the first
      that returns 200.
    - Extract to C:\php\8.4
    - Configure php.ini by running tools\fix-php-ini.ps1 -PhpRoot C:\php\8.4
      That script enumerates the DLLs that actually exist rather than assuming a list,
      and knows opcache is a zend_extension. Do not hand-write php.ini.
    - LEAVE C:\php\8.3 FIRST ON PATH. PHP 8.4 is installed now, activated in Phase C.
      Laravel 10.50.2 is not fully PHP 8.4 clean and composer.json pins
      config.platform.php = 8.3.33.

A2. Verify all 27 required extensions load under 8.4:
    C:\php\8.4\php.exe -m
    Compare against the required list in tools\fix-php-ini.ps1. Report any missing.

A3. Upgrade MySQL to 8.4 LTS.
    - Check the installed version first: mysql --version
    - MySQL 8.0 reached EOL April 2026. 9.x are Innovation releases, NOT LTS — do not
      install those. 8.4 LTS has support to ~2032.
    - If already 8.4.x, just report the patch version and skip.
    - IMPORTANT: 8.4 disables mysql_native_password by default. After upgrading,
      confirm the app user can still authenticate and record the auth plugin in use.

A4. Report: php 8.3 version, php 8.4 version, missing extensions, mysql version,
    auth plugin. Then STOP and wait for me before Phase B.

===========================================================================
PHASE B — close the gates that make the Laravel upgrade verifiable.
===========================================================================

Do not start Phase C until every item here is green. TODO.md lists these as gates 1-7.

B1. Commit the outstanding Session 3 work. 13 files including PRD.md amendments.
    Group into logical commits, show me each staged file list and message BEFORE
    committing. Never commit real member data or the workbook.

B2. Fix `php artisan test`. It currently throws RequirementsException from a
    nunomaduro/collision vs PHPUnit 10 mismatch. vendor/bin/phpunit works as a
    workaround but CI needs a working runner. Nothing in this phase is verifiable
    until this is fixed.

B3. Create the branch topology: ifgf/main from the current approved commit, and
    confirm main mirrors upstream/main. Do NOT create or push deploy.

B4. Create a disposable MySQL 8.4 test database. The name must contain an obvious
    test marker. Wire phpunit.xml to it, replacing the sqlite :memory: stopgap.
    Before any destructive command, print and assert environment, driver, host and
    database name (build.md L515).

B5. Write characterization tests for the Release 1 surface ONLY — auth, roles and
    direct permissions, member profile, member QR / membership card, event attendance
    session open/scan/lock/unlock, group access, exports. These are the baseline the
    upgrade is measured against. Aim for behaviour capture, not coverage percentage.
    Do not write tests for CGSL, ministries, registration or Worship Night — deferred
    to Release 2 per PRODUCTION_PATH.md.

B6. Add a CI workflow that runs, from a clean checkout on Linux:
    composer validate --strict, composer install, composer audit,
    a PSR-4 autoload-warning check, migrations, and the test suite.
    A clean-checkout Linux install would have caught all four upstream defects
    (UP-001..UP-004) before they cost round-trips.

B7. Run the full suite. Record the pass/fail/skip counts in MEMORY.md.
    THIS IS THE BASELINE. Then STOP and wait for me.

===========================================================================
PHASE C — Laravel 10.50.2 -> 11 -> 12 -> 13. One major at a time.
===========================================================================

Branch: contrib/laravel-supported-platform, from reviewed upstream/main per build.md.
Keep this series IFGF-neutral — no church-specific changes mixed in.

C1. Recheck laravel.com's release and support table on the day you start. Record why
    the selected target is the newest stable major. Do not trust MEMORY.md for this.

C2. Remove laravel/legacy-factories. It is Laravel 10 max and a HARD BLOCKER for any
    version beyond. Rewrite the affected factories to the class-based API first, with
    tests passing, before touching the framework version.

C3. For EACH major transition (10->11, then 11->12, then 12->13), in this order:
      a. composer why-not laravel/framework ^<next>
      b. Read the official upgrade guide for that specific major.
      c. Update composer.json constraints for framework AND the packages the solver
         names. Replace, upgrade, isolate or remove incompatible packages
         deliberately.
      d. NEVER use --ignore-platform-reqs, --no-verify, or forced resolutions.
         If a package cannot be satisfied, STOP and report it. Do not work around it.
      e. Run the full test suite. Compare against the Phase B baseline counts.
      f. If any characterization test that passed in B7 now fails, STOP. Report the
         test, the failure, and your diagnosis. Do not proceed to the next major and
         do not "fix" the test to make it pass.
      g. Commit that transition separately so each major is attributable.

C4. Switch the active PHP to 8.4 only after reaching Laravel 13:
    - Put C:\php\8.4 first on PATH
    - Update composer.json config.platform.php to the installed 8.4 patch version
    - composer update --lock
    - Re-run the full suite
    PHP 8.3 loses active support 2026-11-23; 8.4 has security support to Dec 2028.

C5. Frontend: audit Vue 2.6 / laravel-mix 4 / webpack 4 against the upgraded stack.
    Vue 2 is EOL and there are 166 npm vulnerabilities. Do NOT run npm audit fix.
    Report whether the production asset build still works and what a Vite migration
    would require. Do not start that migration — it is a separate approved decision
    and must not be mixed with a framework upgrade.

C6. Verify: php artisan about, migrations on a fresh database, seeders, queues,
    scheduler, storage links, role middleware, and the production asset build.

C7. Update UPSTREAM.md with every upstream-owned file touched and prove the series
    replays onto the current upstream baseline. Run the merge rehearsal.

===========================================================================
RULES THROUGHOUT
===========================================================================

- Never commit without showing me the staged files and message first.
- Never touch deploy, production, live Google Calendar, or real member data.
- Stop at 120k tokens used and hand off per CLAUDE.md.
- If a phase gate fails, STOP and report. Do not proceed to the next phase.
- Always pipe command output: 2>&1 | Select-Object -Last 60
```

---

## Why Phase B exists

You could skip straight to Phase C and it would probably "work" — Composer would resolve, the
application would boot, `php artisan about` would print Laravel 13.

You would have no way to know whether attendance scanning, role checks, member QR generation, or
exports still behave the way they did before. With four test files, the first sign of a regression
would be a leader at a Sunday service.

Phase B is roughly 5–7 sessions. It is the difference between an upgrade you can defend and one
you hope about.

## Rollback

- **PHP** — 8.3 stays installed at `C:\php\8.3`. Reorder PATH to revert.
- **MySQL** — take a dump before A3. The 8.0→8.4 upgrade is one-way.
- **Laravel** — each major is a separate commit on a separate branch. `git reset --hard` to the
  previous transition; `main` and `ifgf/main` are untouched throughout.
