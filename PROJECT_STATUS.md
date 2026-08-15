# Project status against the PRD

**As of 2026-08-11** · Branch `contrib/laravel-supported-platform` · Laravel 13.24.0 / PHP 8.4.24 /
MySQL 8.4 LTS

---

## Headline

**Zero of the 14 PRD functional requirements are complete.** What has been finished is the
*platform and planning* underneath them — which was the largest single block of risk, but is not a
feature the church can use.

The critical number: **`ifgf_` tables in the repository: 0.** No IFGF domain model exists yet. Every
FR below marked "baseline" refers to *upstream ChurchCMS capability that happens to exist*, not to
work done against this PRD.

| | Count |
|---|---|
| PRD functional requirements complete | **0 of 14** |
| PRD requirements with usable upstream baseline | 6 |
| PRD requirements with no baseline at all | 8 |
| `ifgf_` package tables created | **0 of ~23** |
| Characterization tests | **2 files** |
| IFGF package (`custompackages/ifgf/church-operations`) | **does not exist** |

---

## Functional requirements

Legend — **Done**: satisfies the PRD. **Baseline**: upstream code exists, unverified and unadapted.
**None**: nothing exists.

| FR | Requirement | Status | What exists in the repo | Gap to PRD |
|---|---|---|---|---|
| **FR-01** | Member registry and lifecycle | 🟡 Baseline | `MemberController`, `MemberAddController`, `MemberEditController`, `ImportMemberController`, `ExportMemberController`, `Userprofile` | Merge + audit trail, duplicate detection, status lifecycle (visitor/active/inactive/exited), pastoral notes, contact points, private media pipeline |
| **FR-02** | Registration, portal, **member QR** | 🟡 Baseline | `Auth/RegisterController`, `MembershipCardController` ×2, `simple-qrcode` | **QR redesign specified but not built** — `qr_token`/`qr_version` columns do not exist. Member portal scoping, consent, unclaimed activation |
| **FR-03** | Event catalog and occurrences | 🟡 Baseline | `Events`, `EventsController`, `EventManager` | RFC 5545 recurrence, occurrence generation, delivery mode, audience types, series-edit scope. **`EventAttendanceSession` still keys on `attendance_date`** — one session per event per day |
| **FR-04** | Common attendance engine | 🟡 Baseline **(~60%)** | Full session/attendee stack; `scan` 409 duplicate, `lock`/`unlock`, `searchMember`, `markAttendee`, `EventManager` scope | `AttendanceRecorder` as sole write path; `status` / `participation_mode` / `capture_method` columns; `qr_token` scan; mandatory reopen reason |
| **FR-05** | iCare | 🟡 Baseline | `Group`, `GroupCategory`, `GroupLink`, `GroupsController`, `GroupLinksController` | Effective-dated membership, one-active-primary constraint, transfer transaction, visitors, mobile tick-list, private photos |
| **FR-06** | CGSL discipleship | 🔴 None | — | Entire domain |
| **FR-07** | Ministries | 🔴 None | — | Entire domain |
| **FR-08** | Birthday → Google Calendar | 🔴 None | `BirthdayController`, `CheckBirthday` command, reminder mail/push events — **these send reminders, not Calendar sync** | Google adapter, ACL tracking, viewer access table, sync command, tombstones, leader iframe page |
| **FR-09** | Worship Night / Zoom | 🔴 None | — | Import service, matching, idempotent commit *(deferred to Release 2)* |
| **FR-10** | Reporting and export | 🟡 Baseline | `DashboardController`, `ReportsController`, `ExportMemberController`, `maatwebsite/excel` | Attendance trends, new-member and inactive-risk reports, export audit, admin-only scoping |
| **FR-11** | Roles, privacy, consent, audit | 🟡 Baseline | `Role`, `Permission`, `PermissionUser`, `Usergroup`, Laratrust 8.5.5 | **Three-role model, `RoleAssignmentService`, retire `usergroup_id` across 33 files.** Consent records, audit coverage, privacy review |
| **FR-12** | Data migration and cutover | 🔴 None | — | Whole import pipeline; 5 `ifgf_import_*` tables |
| **FR-13** | Event registration | 🔴 None | — | Capacity, waitlist, promotion *(deferred to Release 2)* |
| **FR-14** | Biometric / recognition | 🔴 None | — | Phase 2, separate repository |

**Important:** "Baseline" is not partial credit. Upstream's `BirthdayController` sends reminder
emails; FR-08 requires Google Calendar synchronisation with ACL auditing. Different features that
share a word.

---

## What *is* finished

| Item | Status | Evidence |
|---|---|---|
| **Platform upgrade (WP 0B)** | ✅ Complete | Laravel 10.50.2 → 13.24.0 across three attributable commits; PHP 8.4.24 pinned; PHPUnit 11.5.56; Sanctum 4.3.3; Laratrust 8.5.5; Medialibrary 11.23.5 |
| **Upstream defect repair** | ✅ Complete | UP-001 lockfile desync · UP-002 three PHP-8.4-only packages stranded · UP-003 PSR-4 case mismatch · UP-004 `symfony/yaml` CVEs |
| **Dead dependency removal** | ✅ Complete | `laravel/legacy-factories`, `botman/*`, orphaned `brozot/laravel-fcm` |
| **CI pipeline** | ✅ Complete | Ubuntu + PHP 8.4, `composer validate --strict`, audit, PSR-4 check, `migrate:fresh --seed`, `artisan test` |
| **Timezone correction** | ✅ Complete | `Asia/Kolkata` → `Asia/Taipei`; MySQL connection `+08:00`; characterization test added |
| **Test database** | ✅ Complete | Disposable MySQL 8.4, `.env.testing` |
| **Branch topology** | 🟡 Partial | `ifgf/main` exists; `contrib/laravel-supported-platform` **not yet merged**; `deploy` not created (correct — 1E.3) |
| **Requirements reference** | ✅ Complete | `WORKBOOK_INVENTORY.md`, `GAS_INVENTORY.md`, `ROUTE_MIGRATION_INVENTORY.md`, `DEPENDENCY_INVENTORY.md` |
| **PRD amendments** | ✅ Complete | QR design FR-02.7, scanner-side context FR-04.1, six §13.1 decisions |
| **Characterization tests** | 🔴 **2 files** | `MemberImportCharacterizationTest`, `TimezoneCharacterizationTest` — target is 60–90 tests |
| **IFGF package** | 🔴 **Missing** | `custompackages/ifgf/church-operations` does not exist |

---

## Work package progress

| Package | Status | Note |
|---|---|---|
| WP 0A — baseline and safety net | 🟡 **~70%** | Items 6 (characterization), 11 (package), 14 (smoke tests) outstanding. Exit gate not reviewed |
| WP 0B — platform upgrade | ✅ **100%** | Landed 2026-08-10 |
| WP 0C — schema and migration | 🔴 0% | Blocked on 0A |
| WP 0D — privacy gates | 🔴 0% | |
| Phase 1A — member + QR | 🔴 0% | |
| Phase 1B — attendance | 🔴 0% | |
| Phase 1C — iCare | 🔴 0% | |
| Phase 1D — calendar, reports, import | 🔴 0% | |
| Phase 1E — production | 🔴 0% | |

---

## The three things blocking everything

1. **Characterization tests — 2 files.** Three Laravel majors were crossed without a behavioural
   baseline. Until suites for auth, roles, attendance, member profile, QR, groups and exports
   exist, no change can be shown to preserve behaviour. **3–4 sessions.**
2. **No IFGF package.** `custompackages/ifgf/church-operations` does not exist, so there is
   nowhere new IFGF code can legally live under the ownership rule. **1 session.**
3. **`contrib/laravel-supported-platform` is unmerged.** The platform work sits on a branch. Merge
   is deliberately gated on (1) passing. **0.5 sessions.**

---

## Remaining effort

| Stage | Sessions |
|---|---:|
| Close WP 0A (tests, package, smoke tests, merge, rehearsal, gate) | 6–7 |
| WP 0C schema + migration | 10–13 |
| WP 0D privacy | 3–4 |
| Phase 1A member + QR | 10–12 |
| Phase 1B attendance | 7–9 |
| Phase 1C iCare | 5–7 |
| Phase 1D calendar, reports, import | 8–10 |
| Phase 1E production | 8–11 |
| **Release 1 total** | **57–73** |

Release 2 (FR-06, FR-07, FR-09, FR-13) adds ~20–28 afterwards. FR-14 is Release 3.

---

## Honest framing

The project is roughly **20% through the work to Release 1**, and the completed portion is entirely
foundation: a supported framework, a repaired dependency tree, CI, a corrected timezone, and a
verified requirements reference.

That foundation is worth what it cost. The upgrade alone removed an eighteen-month-old
unsupported-framework exposure, and four upstream defects meant the repository could not install
from a clean checkout at all. But **no church-facing function has been built yet**, and the next
milestone that a member or usher would notice is Phase 1A.
