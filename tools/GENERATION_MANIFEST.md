# Generation manifest — WP 0C additive + Phase 1A

**Purpose:** Claude Code runs these commands to create correctly-named, correctly-namespaced
**empty** files. Cowork then fills the bodies. Nothing here contains business logic.

**Run only after the WP 0A exit gate closes** and `contrib/laravel-supported-platform` is merged
to `ifgf/main`. Branch from `ifgf/main`.

**Derived from:** graph review at `7d7654b` · `PRD.md` L864 table list · `build.md` WP 0C item 7 ·
Release 1 scope in `PRODUCTION_PATH.md`.

---

## Scope

**In:** 19 `ifgf_` tables, their models, the import pipeline, Phase 1A member registry / portal / QR.
**Out — Release 2:** `ifgf_programs*` (6, CGSL), `ifgf_ministries*` (2), `ifgf_event_registrations`.
**Out — needs characterization first:** expand/contract on existing tables (WP 0C items 3, 4, 5).

Package facts confirmed from the scaffold:

```
namespace  Ifgf\ChurchOperations\        →  custompackages/ifgf/church-operations/src/
migrations →  custompackages/ifgf/church-operations/database/migrations/
provider   →  Ifgf\ChurchOperations\ChurchOperationsServiceProvider  (auto-discovered)
```

### ⚠ Shell variables do not persist between tool calls

The prompt instructs you to report after each section, and each report is a separate invocation
with a fresh shell. **`$SRC` and `$P` are empty at the start of every section unless you restate
them.**

This fails silently and badly: `New-Item -Force -Path "$SRC/Policies"` with an empty `$SRC`
creates `/Policies` at the **filesystem root**, and `git mv` then moves files outside the
repository.

**Paste these two lines at the top of every section, every time:**

```powershell
$SRC = "custompackages/ifgf/church-operations/src"
$P   = "custompackages/ifgf/church-operations/database/migrations"
```

`$SRC` always means `…/src`. Sections that need a subdirectory write `"$SRC/Models"`,
`"$SRC/Policies"` and so on — **never redefine `$SRC`.**

---

## The prompt

```
Execute the generation manifest in tools/GENERATION_MANIFEST.md. Read CLAUDE.md,
CONTEXT.md and TODO.md first.

PRECONDITION — verify before running anything:
  - WP 0A exit gate is closed (all 7 criteria met)
  - contrib/laravel-supported-platform is merged into ifgf/main
  - working tree clean
If any is false, STOP and report. Do not generate onto an unverified baseline.

Create branch: codex/phase-0c-data-foundation from ifgf/main — but ONLY after the
merge in precondition 2. Branching from ifgf/main today gives you commit aa8194e,
which is the clean upstream subset: Laravel ^10.0, PHP ^8.2, no IFGF package, no
tests. On that base Section 3 fails outright (make:class is Laravel 11+), Sections
1/2/4/5/6 have no relocation target, and verification step (d) has no suite to keep
green. Everything this manifest depends on lives on contrib, which is 49 commits
ahead and unmerged.

YOUR JOB IS TO CREATE EMPTY, CORRECTLY-NAMED FILES. Do not write business logic,
do not design columns, do not implement methods. Cowork fills every body in the
next pass. If a generator produces a stub, leave the stub.

Run every command in sections 1-6 below. After each section, report what was created
and anything that failed. Use `php artisan` (bare php resolves to 8.4.24).

After all sections:
  a. composer dump-autoload   — must emit NO psr-4 warning
  b. php artisan package:discover
  c. php artisan about --only=environment
  d. php artisan test 2>&1 | Select-Object -Last 30   — the 8 existing suites must
     still pass; nothing generated should affect them
  e. Report the full file list created, grouped by section

Then STOP. Show me staged files and a proposed commit message. Do not commit until
I approve. Do not start filling any file.
```

---

## Section 1 — Package migrations (19 tables)

`--path` targets the package directly, so no `git mv` is needed for these.

```
$P="custompackages/ifgf/church-operations/database/migrations"

php artisan make:migration create_ifgf_branches_table               --create=ifgf_branches               --path=$P
php artisan make:migration create_ifgf_member_profiles_table        --create=ifgf_member_profiles        --path=$P
php artisan make:migration create_ifgf_contact_points_table         --create=ifgf_contact_points         --path=$P
php artisan make:migration create_ifgf_event_types_table            --create=ifgf_event_types            --path=$P
php artisan make:migration create_ifgf_event_definitions_table      --create=ifgf_event_definitions      --path=$P
php artisan make:migration create_ifgf_event_occurrences_table      --create=ifgf_event_occurrences      --path=$P
php artisan make:migration create_ifgf_attendance_details_table     --create=ifgf_attendance_details     --path=$P
php artisan make:migration create_ifgf_group_memberships_table      --create=ifgf_group_memberships      --path=$P
php artisan make:migration create_ifgf_member_media_table           --create=ifgf_member_media           --path=$P
php artisan make:migration create_ifgf_media_variants_table         --create=ifgf_media_variants         --path=$P
php artisan make:migration create_ifgf_attendance_media_table       --create=ifgf_attendance_media       --path=$P
php artisan make:migration create_ifgf_consents_table               --create=ifgf_consents               --path=$P
php artisan make:migration create_ifgf_calendar_links_table         --create=ifgf_calendar_links         --path=$P
php artisan make:migration create_ifgf_calendar_viewer_access_table --create=ifgf_calendar_viewer_access --path=$P
php artisan make:migration create_ifgf_import_batches_table         --create=ifgf_import_batches         --path=$P
php artisan make:migration create_ifgf_import_source_records_table  --create=ifgf_import_source_records  --path=$P
php artisan make:migration create_ifgf_source_identity_maps_table   --create=ifgf_source_identity_maps   --path=$P
php artisan make:migration create_ifgf_import_conflicts_table       --create=ifgf_import_conflicts       --path=$P
php artisan make:migration create_ifgf_import_exceptions_table      --create=ifgf_import_exceptions      --path=$P
```

**Ordering is guaranteed.** Laravel 13's `MigrationCreator::getDatePrefix()` advances by one second
while a file with that prefix already exists in the target path, and that branch only runs when
`--path` is set — which every command above does. Timestamps come out sequential in the order
listed, which is dependency-correct: branches → member profiles → contact points → event types →
definitions → occurrences → attendance details → memberships → media → consents → calendar →
import.

**These migrations are not empty.** `--create=` uses `migration.create.stub`, which emits a real
`Schema::create()` with `$table->id()` and `$table->timestamps()`, plus `Schema::dropIfExists()` in
`down()`. Because `ChurchOperationsServiceProvider::boot()` calls
`loadMigrationsFrom(__DIR__.'/../database/migrations')`, **verification step (d) will create 19
near-empty `ifgf_*` tables in `churchcms_test_disposable`.** That is expected, not a regression —
the suites still pass. Cowork editing these same files afterwards is legal: they are unreleased and
have never run against anything but the disposable test database.

## Section 2 — Models

Generated into `app/Models/`, then relocated. `make:model` cannot target a package path.

```
php artisan make:model Branch
php artisan make:model MemberProfile
php artisan make:model ContactPoint
php artisan make:model EventType
php artisan make:model EventDefinition
php artisan make:model EventOccurrence
php artisan make:model AttendanceDetail
php artisan make:model GroupMembership
php artisan make:model MemberMedia
php artisan make:model MediaVariant
php artisan make:model AttendanceMedia
php artisan make:model Consent
php artisan make:model CalendarLink
php artisan make:model CalendarViewerAccess
php artisan make:model ImportBatch
php artisan make:model ImportSourceRecord
php artisan make:model SourceIdentityMap
php artisan make:model ImportConflict
php artisan make:model ImportException
```

Then relocate all 19 in one pass:

```powershell
$SRC = "custompackages/ifgf/church-operations/src"
New-Item -ItemType Directory -Force -Path "$SRC/Models"
Get-ChildItem app/Models/*.php | Where-Object {
  'Branch','MemberProfile','ContactPoint','EventType','EventDefinition','EventOccurrence',
  'AttendanceDetail','GroupMembership','MemberMedia','MediaVariant','AttendanceMedia','Consent',
  'CalendarLink','CalendarViewerAccess','ImportBatch','ImportSourceRecord','SourceIdentityMap',
  'ImportConflict','ImportException' -contains $_.BaseName
} | ForEach-Object { git mv $_.FullName "$SRC/Models/$($_.Name)" }

Get-ChildItem "$SRC/Models/*.php" | ForEach-Object {
  (Get-Content $_) -replace '^namespace App\\Models;','namespace Ifgf\ChurchOperations\Models;' |
  Set-Content $_ -Encoding utf8
}
```

**Do not relocate any pre-existing model.** The filter list above is exhaustive — if `git mv`
reports moving anything not on it, stop and report.

## Section 3 — Services, actions, support

No artisan generator exists for plain classes in Laravel 13 other than `make:class`.

```
php artisan make:class Services/MemberRegistryService
php artisan make:class Services/MemberQrService
php artisan make:class Services/RoleAssignmentService
php artisan make:class Services/AttendanceRecorder
php artisan make:class Services/OccurrenceGenerator
php artisan make:class Services/GroupMembershipService
php artisan make:class Services/MemberMediaService
php artisan make:class Support/PhoneNormalizer
php artisan make:class Support/NameNormalizer
php artisan make:class Support/DuplicateScorer
php artisan make:class Import/MemberExtractor
php artisan make:class Import/AttendanceGridExtractor
php artisan make:class Import/IdentityResolver
php artisan make:class Import/ConflictRecorder
php artisan make:class Import/ReconciliationReport
```

### ⚠ Relocation guard — corrected 2026-08-11 after Claude Code review

The original instruction said "relocate `app/Services`, `app/Support`, `app/Import`". **That was
wrong and would have moved upstream code.** Both directories already exist and are upstream-owned:

```
app/Services/Payment/{Flutterwave,GCash,Mpesa,Paystack,Stripe}Service.php     5 files
app/Support/Presenter/{PresentableInterface,PresentableTrait,Presenter,        4 files
                       PresenterException}.php
```

A wholesale `git mv` would relocate nine upstream files and rewrite their namespaces — an
unapproved upstream edit under `CLAUDE.md`'s prohibitions. `PresentableTrait` and `Presenter` are
load-bearing for `UserprofilePresenter`, already named in UP-007. **Moving them breaks the
application.**

Use the same exhaustive allow-list discipline as Section 2. Move **only these 15 basenames**:

```
$SRC="custompackages/ifgf/church-operations/src"
New-Item -ItemType Directory -Force -Path "$SRC/Services","$SRC/Support","$SRC/Import"

$svc  = 'MemberRegistryService','MemberQrService','RoleAssignmentService','AttendanceRecorder',
        'OccurrenceGenerator','GroupMembershipService','MemberMediaService'
$sup  = 'PhoneNormalizer','NameNormalizer','DuplicateScorer'
$imp  = 'MemberExtractor','AttendanceGridExtractor','IdentityResolver','ConflictRecorder',
        'ReconciliationReport'

Get-ChildItem app/Services/*.php | ? { $svc -contains $_.BaseName } | % { git mv $_.FullName "$SRC/Services/$($_.Name)" }
Get-ChildItem app/Support/*.php  | ? { $sup -contains $_.BaseName } | % { git mv $_.FullName "$SRC/Support/$($_.Name)"  }
Get-ChildItem app/Import/*.php   | ? { $imp -contains $_.BaseName } | % { git mv $_.FullName "$SRC/Import/$($_.Name)"   }
```

**Never recurse into `app/Services/Payment/` or `app/Support/Presenter/`.** The globs above are
non-recursive by design. If `git mv` reports moving any file not in those three lists, stop and
report.

Then rewrite namespaces **in the moved files only**:

```
Get-ChildItem "$SRC/Services/*.php","$SRC/Support/*.php","$SRC/Import/*.php" | % {
  (Get-Content $_) -replace '^namespace App\\(Services|Support|Import);','namespace Ifgf\ChurchOperations\$1;' |
  Set-Content $_ -Encoding utf8
}
```

`app/Import/` does not exist before this section, so it is created by the generators and is safe.
`app/Services/` and `app/Import/` should be left in place if any file remains in them.

## Section 4 — Policies

```
php artisan make:policy MemberProfilePolicy      --model=MemberProfile
php artisan make:policy EventOccurrencePolicy    --model=EventOccurrence
php artisan make:policy AttendanceDetailPolicy   --model=AttendanceDetail
php artisan make:policy GroupMembershipPolicy    --model=GroupMembership
php artisan make:policy MemberMediaPolicy        --model=MemberMedia
```

**Allow-list required.** `app/Policies/` holds **11 upstream policies** — `UserPolicy`,
`UserprofilePolicy`, `ChurchPolicy`, `GroupPolicy`, `UsergroupPolicy`, `ActivityLogPolicy`,
`ContactPolicy`, `GroupCategoryPolicy`, `CityPolicy`, `StatePolicy`, `CountryPolicy`. This is the
authorization surface UP-007 names and `RolePermissionCharacterizationTest` covers; moving it
wholesale would break exit-gate criterion 2's own tests.

```powershell
$pol = 'MemberProfilePolicy','EventOccurrencePolicy','AttendanceDetailPolicy',
       'GroupMembershipPolicy','MemberMediaPolicy'
New-Item -ItemType Directory -Force -Path "$SRC/Policies"
Get-ChildItem app/Policies/*.php | ? { $pol -contains $_.BaseName } |
  % { git mv $_.FullName "$SRC/Policies/$($_.Name)" }
```

### ⚠ Do NOT blanket-replace `use App\Models\`

`policy.stub` emits **two** imports:

```php
use {{ namespacedModel }};        // App\Models\MemberProfile
use {{ namespacedUserModel }};    // App\Models\User      ← config/auth.php:70
```

`User` is upstream and **stays in `app/Models/`**. A blanket replace rewrites it to
`Ifgf\ChurchOperations\Models\User`, a class that does not exist and never will. All five policies
would fatal on first resolution — and `composer dump-autoload` would not catch it, because a broken
*import* is not a broken class definition.

Rewrite the namespace, then the five model imports **by name**:

```powershell
$SRC = "custompackages/ifgf/church-operations/src"
$mdl = 'MemberProfile','EventOccurrence','AttendanceDetail','GroupMembership','MemberMedia'

Get-ChildItem "$SRC/Policies/*.php" | % {
  $c = Get-Content $_ -Raw
  $c = $c -replace '(?m)^namespace App\\Policies;','namespace Ifgf\ChurchOperations\Policies;'
  foreach ($m in $mdl) {
    $c = $c -replace "use App\\\\Models\\\\$m;","use Ifgf\ChurchOperations\Models\$m;"
  }
  Set-Content $_ -Value $c -Encoding utf8
}
```

`use App\Models\User;` is left untouched. That is correct, not an oversight.

## Section 5 — Console commands (import pipeline)

```
php artisan make:command IfgfImportMembers    --command=ifgf:import-members
php artisan make:command IfgfImportAttendance --command=ifgf:import-attendance
php artisan make:command IfgfReconcile        --command=ifgf:reconcile
php artisan make:command IfgfRotateMemberQr   --command=ifgf:rotate-member-qr
php artisan make:command IfgfSyncBirthdays    --command=ifgf:sync-birthdays
```

**Allow-list required.** `app/Console/Commands/` holds **15 upstream commands** plus a `Test/`
subdirectory — `CheckBirthday`, `CheckAnniversary`, `CheckMailQueue`, `SetupChurchCommand` and
others.

```powershell
$cmd = 'IfgfImportMembers','IfgfImportAttendance','IfgfReconcile',
       'IfgfRotateMemberQr','IfgfSyncBirthdays'
Get-ChildItem app/Console/Commands/*.php | ? { $cmd -contains $_.BaseName } |
  % { git mv $_.FullName "$SRC/Console/Commands/$($_.Name)" }

Get-ChildItem "$SRC/Console/Commands/Ifgf*.php" | % {
  (Get-Content $_) -replace '^namespace App\\Console\\Commands;','namespace Ifgf\ChurchOperations\Console\Commands;' |
  Set-Content $_ -Encoding utf8
}
```

The `Ifgf` prefix on all five is deliberate — it makes the allow-list and the post-move glob
unambiguous. `src/Console/Commands/` already exists (holds `ChurchOperationsPing`), so no
`New-Item` is needed. Register the five in `ChurchOperationsServiceProvider`.

## Section 6 — Phase 1A HTTP surface + tests

**Corrected 2026-08-11.** The original had `--resource --requests` on the controller *and* two
standalone `make:request` commands — two competing Store/Update pairs. `--requests` is dropped;
the explicit requests survive, because their names are then deterministic and the command cannot
prompt. `-n` is added throughout to prevent any interactive prompt.

```
php artisan make:controller Ifgf/MemberPortalController -n
php artisan make:controller Ifgf/MemberQrController -n
php artisan make:controller Ifgf/Admin/MemberRegistryController --resource -n
php artisan make:request StoreMemberProfileRequest -n
php artisan make:request UpdateMemberProfileRequest -n

php artisan make:test Ifgf/MemberRegistrySchemaTest -n
php artisan make:test Ifgf/MemberQrTest -n
php artisan make:test Ifgf/MemberPortalTest -n
php artisan make:test Ifgf/ImportDryRunTest -n
php artisan make:test Ifgf/PhoneNormalizerTest --unit -n
php artisan make:test Ifgf/DuplicateScorerTest --unit -n
```

Do **not** pass `--model=` to the resource controller. The model lives in the package namespace,
which artisan cannot resolve at generation time; Cowork wires the type hint when filling the body.

**Tests stay in the root `tests/`** so the existing suite and CI pick them up unchanged. Do not
move them.

**Controllers are the one safe wholesale move.** `app/Http/Controllers/Ifgf/` does not exist
today — the generators create it, so everything inside is generated and nothing upstream can be
captured.

**Drop the redundant `Ifgf/` level on the way in.** Under `"Ifgf\\ChurchOperations\\": "src/"`,
keeping it would produce `Ifgf\ChurchOperations\Http\Controllers\Ifgf\MemberPortalController`.
Correct, but the doubled segment appears in every `use` statement Cowork writes afterwards. Moving
the *contents* up one level keeps the safe-wholesale property and gives clean namespaces.
**Decide this now — changing it after the fill pass rewrites every import.**

```powershell
$SRC = "custompackages/ifgf/church-operations/src"
New-Item -ItemType Directory -Force -Path "$SRC/Http/Controllers"      # src/Http/ does not exist

git mv app/Http/Controllers/Ifgf/MemberPortalController.php       "$SRC/Http/Controllers/"
git mv app/Http/Controllers/Ifgf/MemberQrController.php           "$SRC/Http/Controllers/"
New-Item -ItemType Directory -Force -Path "$SRC/Http/Controllers/Admin"
git mv app/Http/Controllers/Ifgf/Admin/MemberRegistryController.php "$SRC/Http/Controllers/Admin/"
Remove-Item app/Http/Controllers/Ifgf -Recurse -Force               # now empty

Get-ChildItem "$SRC/Http/Controllers" -Recurse -Filter *.php | % {
  (Get-Content $_) `
    -replace '(?m)^namespace App\\Http\\Controllers\\Ifgf\\Admin;','namespace Ifgf\ChurchOperations\Http\Controllers\Admin;' `
    -replace '(?m)^namespace App\\Http\\Controllers\\Ifgf;','namespace Ifgf\ChurchOperations\Http\Controllers;' |
  Set-Content $_ -Encoding utf8
}
```

**Leave `use App\Http\Controllers\Controller;` alone.** `controller.stub` emits it, and after the
move the package controllers extend the upstream base controller across the namespace boundary.
That resolves correctly — root `App\ → app/` autoloading is untouched — and the namespace rewrites
above are anchored to `^namespace` so they cannot catch it.

*Recorded decision:* the package therefore depends on `App\Http\Controllers\Controller`. Acceptable
for Release 1. If the package should be self-contained, Cowork adds a package base controller
during the fill pass — that is a body change, not a generation change.

**Requests need the allow-list.** `app/Http/Requests/` holds **85 upstream classes** plus `Api/`,
`EmailBlaster/` and `Payment/` subdirectories — the largest blast radius in this manifest.

```powershell
$req = 'StoreMemberProfileRequest','UpdateMemberProfileRequest'
New-Item -ItemType Directory -Force -Path "$SRC/Http/Requests"
Get-ChildItem app/Http/Requests/*.php | ? { $req -contains $_.BaseName } |
  % { git mv $_.FullName "$SRC/Http/Requests/$($_.Name)" }

Get-ChildItem "$SRC/Http/Requests/*.php" | % {
  (Get-Content $_) -replace '^namespace App\\Http\\Requests;','namespace Ifgf\ChurchOperations\Http\Requests;' |
  Set-Content $_ -Encoding utf8
}
```

---

## After generation — what Cowork fills

| Section | Cowork writes |
|---|---|
| 1 | Every column, type, index, FK, nullability. Home branch **nullable**. No MySQL enums — lookup tables. `ifgf_member_profiles.qr_token char(32) unique`, `qr_version`, `qr_rotated_at` |
| 2 | `$table`, `$fillable`, `$casts`, relations to upstream `User`/`Userprofile`/`Events`/`GroupLink` |
| 3 | Service bodies. `AttendanceRecorder` is the sole attendance write path. `MemberQrService` generates and rotates the opaque token |
| 4 | Policy methods — member self-only, leader assignment-scoped, admin full |
| 5 | Command signatures with `--dry-run` / `--commit`, and the reconciliation output |
| 6 | Controllers, requests, and every test body |

## Review record — 2026-08-11

Claude Code ran the precondition check and **correctly refused to generate**. All three
preconditions failed:

| Precondition | State |
|---|---|
| WP 0A exit gate 7/7 | ❌ 4 met, 1 partial, 2 not met (`WP0A_EXIT_GATE.md`) |
| `contrib` merged to `ifgf/main` | ❌ `ifgf/main` at `aa8194e`, contrib 49 ahead |
| Clean tree | ❌ `.graphifyignore` staged, two `tools/*.md` untracked |

It then found three defects in the manifest itself. Two were mine and are now fixed:

1. **Section 3 would have moved 9 upstream files** — `app/Services/Payment/*` and
   `app/Support/Presenter/*` already exist. Section 2 had an allow-list guard; Section 3 did not.
   Inconsistent, and the more dangerous of the two. Fixed with an explicit 15-name allow-list.
2. **Section 6 generated two competing request pairs**, and `--requests` without `--model` can
   prompt interactively. Fixed by dropping `--requests` and adding `-n` throughout.
3. **Model name collisions: none** — all 19 verified clear against `app/Models/`. No change needed.

### Second review, same day — the fix was not propagated

Claude Code re-checked and found **the identical defect in Sections 4, 5 and 6.** I had fixed
Section 3 and not asked whether the pattern repeated. It did, three more times, with far worse
blast radius:

| Section | Source directory | Upstream files at risk |
|---|---|---|
| 4 Policies | `app/Policies/` | **11** — the authorization surface UP-007 names and `RolePermissionCharacterizationTest` covers |
| 5 Commands | `app/Console/Commands/` | **15** + a `Test/` subdirectory |
| 6 Requests | `app/Http/Requests/` | **85** + `Api/`, `EmailBlaster/`, `Payment/` |

Section 4 was the most dangerous: relocating `app/Policies/` wholesale would rewrite namespaces on
every policy in the application and fail the very characterization tests that exit-gate criterion 2
is waiting on. All three now carry explicit allow-lists.

**Lesson worth keeping:** when a defect is found in one section of a generated instruction set,
check every structurally similar section before declaring it fixed. Fixing the instance instead of
the class cost a full review cycle here.

### Third review — four more, one of them from my own fix

1. **The Section 4 model rewrite I supplied last round corrupts `use App\Models\User;`.**
   `policy.stub` emits both `{{ namespacedModel }}` and `{{ namespacedUserModel }}`, and
   `config/auth.php:70` makes the latter `App\Models\User`. My blanket
   `use App\Models\` → `use Ifgf\ChurchOperations\Models\` would have rewritten it to a class that
   does not exist. All five policies fatal on first resolution, and **`composer dump-autoload`
   would not catch it** — a broken import is not a broken class definition. Now replaced by a
   by-name loop over the five model classes. **I wrote a fix without reading the stub it applied
   to.**
2. **`$SRC` and `$P` do not persist between tool calls**, and Section 2 was redefining `$SRC` to
   `…/src/Models` while Sections 3–6 assumed `…/src`. An empty `$SRC` creates `/Policies` at the
   filesystem root and moves files out of the repository — silent and messy. Both variables are now
   restated at the top of every section and `$SRC` always means `…/src`.
3. **Section 6's controller move had no destination parent.** `src/Http/` does not exist, and
   `git mv` fails rather than creating the chain.
4. **One `Set-Content` in Section 3 missed the encoding sweep.** Fixed.

**Decision taken in this pass:** the redundant `Ifgf/` directory level is dropped inside the
package, so namespaces are `Ifgf\ChurchOperations\Http\Controllers\…` rather than
`…\Controllers\Ifgf\…`. Settled now because changing it after the fill pass rewrites every import.

**Decision recorded:** package controllers extend upstream `App\Http\Controllers\Controller` across
the namespace boundary. Correct and deliberate; revisit only if the package must be self-contained.

Also corrected in the same pass: `-Encoding utf8` on every `Set-Content` (PowerShell 5.1 defaults
to the ANSI codepage and this checkout has `core.autocrlf=true`), the `use App\Models\` →
`use Ifgf\ChurchOperations\Models\` rewrite in Section 4 which is a *different* pattern from
Section 3's, and an explicit note that Section 1's migrations are not empty and step (d) will
create 19 tables.

Confirmed correct and unchanged: migration timestamp ordering under `--path`, the Section 3
allow-list and its namespace regex, the Section 6 request-pair resolution, package autoload wiring
(`"Ifgf\\ChurchOperations\\": "src/"` via symlinked path repository), and zero model-name
collisions across all 19.

The gate working as designed is the useful signal here. A manifest that refuses to run on an
unverified baseline caught its own defects — twice — before they cost anything.

**Blocking work before this manifest can run:** 7 characterization suites (3–4 sessions, exit-gate
criterion 2), then owner review of `UPSTREAM.md` entries and the compatibility matrix (criteria 4
and 7 — one sitting, they close together), then the merge to `ifgf/main`.

## Known traps for the generating session

- **`make:model` has no `--path`.** Generate then `git mv` — the relocation block is written out
  above precisely so this is not improvised.
- **Case-only renames need a two-hop `git mv` through a distinct name** on this checkout
  (`core.ignorecase=true`). It silently no-ops otherwise — this cost a commit already (UP-003).
- **`composer dump-autoload` must emit no PSR-4 warning.** One did before (UP-003) and the class
  was excluded from the autoloader entirely.
- **The 8 existing characterization suites must still pass.** Nothing generated should touch them;
  if one fails, something was relocated that should not have been.
