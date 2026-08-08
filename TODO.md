# TODO

> Item 1 is always the literal next action. Rewritten every session end.
> Hard cap: 1,500 tokens — archive completed items to `MEMORY.md`, do not accumulate them here.
> Every item names its `build.md` item number and its `PRD.md` line range where relevant.

**Active work package:** 0A — Baseline and Safety Net (`build.md` L202–228)
**Session:** 1 → 2

---

## Now

1. **Record UP-002's resolved versions.** Run and paste:
   `composer show 2>&1 | Select-String "symfony/css-selector|symfony/filesystem|lcobucci/clock|symfony/yaml|laravel/framework"`
   Then close the UP-002 checklist in `UPSTREAM.md`.
2. **Fix UP-003** — the PSR-4 case mismatch. Two-step rename because Git ignores case-only
   renames on Windows:
   ```
   git mv database/factories/EventgalleryFactory.php database/factories/EventGalleryFactory.tmp
   git mv database/factories/EventGalleryFactory.tmp database/factories/EventGalleryFactory.php
   composer dump-autoload
   ```
3. **Generate the app key** — `php artisan key:generate`. `.env` exists but `APP_KEY` is empty.
4. **Commit the baseline.** Stage `PRD.md`, `hosting.md`, `build.md`, `.gitignore`,
   `composer.json`, `composer.lock`, `EXECUTION_PLAN.md`, `CONTEXT.md`, `MEMORY.md`, `TODO.md`,
   `CLAUDE.md`, `UPSTREAM.md`, `tools/`. Record the SHA in `CONTEXT.md`.
   *(OPERATING CONTRACT 6 — blocks all further coding)*
5. **Confirm WP 0A scope** with the owner, then open Session 2.

**Do not** run `npm audit fix`, `composer audit fix`, or unargumented `composer update`.
Both lockfiles are the characterization baseline.

## Next — Work Package 0A (`build.md` L207–224)

| Session | Item(s) | Action |
|---|---|---|
| 2 | 3 | Classify all 194 Composer packages: keep / upgrade / replace / remove. Triage the 52 advisories across 14 packages. Start from `EXECUTION_PLAN.md` §1.4 and the `UPSTREAM.md` security table. Record Laravel 13 generator flags in `CLAUDE.md`. |
| 3 | 4a | Inventory 5 route files and 93 migrations. |
| 4 | 4b | Inventory auth, roles, attendance, membership cards, exports, media, storage, queues, scheduler. |
| 5 | 15 | Write `.graphifyignore`, rebuild the graph, confirm queries return source not `public/js/app.js`. **Do this early — it lowers the cost of every later session.** |
| 6 | 9, 10 | Pin upstream SHA, record divergence, create `UPSTREAM.md` compatibility ledger and the ownership map. |
| 7 | 11 | Scaffold `custompackages/ifgf/church-operations` with path loading and auto-discovery. No product behavior. |
| 8 | 14 | Provider smoke tests from a clean checkout. |
| 9–11 | 6 | Characterization tests — auth/roles, then member/groups/events, then QR/attendance/exports/media. Writing from **zero** existing tests. |
| 12 | 5, 8 | Disposable MySQL 8.4 test database + anonymized fixtures. No real member data. |
| 13 | 7, 13 | CI workflow + read-only upstream merge rehearsal. **Consider pulling forward** — a clean-checkout `composer install` on Linux would have caught UP-001, UP-002 and UP-003 before any of them cost a round-trip. Must include a PSR-4 autoload-warning check. |
| 14 | 12, gate | Branch topology (`main` / `ifgf/main` / `deploy`) + WP 0A exit gate review. |

## Blocked

- **All coding** — documentation baseline uncommitted (item 2 above).
- **Anything needing PHP** — toolchain not installed (item 1 above).
- **WP 0B** — cannot start until the WP 0A exit gate passes. Findings already staged in
  `EXECUTION_PLAN.md` §1.4; `laravel/legacy-factories` is a hard blocker.

## Decisions awaiting the owner

- Production PHP pin: 8.4 recommended, subject to the extension audit *(WP 0B item 14)*.
- Vue 2 / laravel-mix 4 → Vite: proposed as a separate IFGF-neutral package *(WP 0B item 8)*.
- Whether to sync the 8 upstream commits currently behind before WP 0A, or pin and defer.
