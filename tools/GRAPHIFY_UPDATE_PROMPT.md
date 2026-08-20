# Graphify update — Claude Code prompt

Short session. Paste the fenced block into Claude Code.

**Why now:** the graph was built at `a262771`. HEAD is `7d7654b`, many commits later — it predates
the IFGF package scaffold, all 8 characterization test files, and the UP-011 case rename. Any
architecture query against it describes code that no longer exists.

---

```
Read CLAUDE.md and CONTEXT.md. This is a short maintenance task, not a work package.

GOAL: rebuild the Graphify index so it reflects HEAD, and make it return source code
rather than prose.

STEP 1 — extend .graphifyignore before rebuilding.
The current graph wastes capacity on documentation. Measured at the last rebuild,
top files by node count were: package.json 116, _design_reference/*.md 95,
composer.json 82, PRD.md 80, _ai/*.md 63 — while app/Models/User.php, the first
real source file, had only 48.

Indexing PRD.md is actively counterproductive: a query for an implementation returns
the requirement text describing it.

Add to .graphifyignore:
  _ai/
  _design_reference/
  _code_reference/
  *.md at the repository root (PRD.md, build.md, hosting.md, EXECUTION_PLAN.md,
    TESTING_PLAN.md, PROJECT_STATUS.md, PRD_REVIEW.md, PRD_OPEN_QUESTIONS.md,
    WORKBOOK_INVENTORY.md, GAS_INVENTORY.md, ROUTE_MIGRATION_INVENTORY.md,
    DEPENDENCY_INVENTORY.md, PRODUCTION_PATH.md, ICARE_PHOTO_ATTENDANCE.md,
    ATTENDANCE_QR_DESIGN.md, UPSTREAM.md, CONTEXT.md, MEMORY.md, TODO.md,
    AGENTS.md, CLAUDE.md, README.md, CONTRIBUTING.md, INSTALL_GUIDE.md,
    CLI_INSTALL_GUIDE.md)
  tools/
  database/schema/

Do NOT ignore: app/, custompackages/, database/migrations/, routes/, config/, tests/.
Those are what queries should return.

STEP 2 — rebuild.
  graphify update .
If that errors or the CLI is missing, report exactly what happened and stop. Do not
substitute a different tool or hand-edit graph.json.

STEP 3 — verify the rebuild actually improved things. Report ALL of these numbers,
they are how the next Cowork session checks your work:

  a. graphify-out/graph.json  ->  built_at_commit   (must equal current HEAD)
  b. node count and edge count
  c. graph.json file size in MB
  d. distinct source_file count
  e. TOP 15 source_file values by node count
     (expect app/ and custompackages/ to dominate; if package.json or any .md is
      still in the top 5, the ignore rules did not take effect — say so plainly
      rather than reporting success)

STEP 4 — one functional check.
Run: graphify query "attendance session scan member occurrence" --budget 2500
Report whether the results point at app/Http/Controllers/Api/AttendanceController.php,
app/Models/EventAttendanceSession.php and app/Models/EventAttendee.php — the files
that actually implement it. If it returns markdown or manifests instead, the index is
still not fit for purpose and I need to know.

STEP 5 — commit .graphifyignore only.
graphify-out/ stays untracked (build.md OPERATING CONTRACT 9). Show the staged file
and message before committing.

Do not start any work package. Report and stop.
```

---

## How Cowork verifies this

I read `graphify-out/graph.json` directly — no CLI needed. The checks:

| Check | Pass condition |
|---|---|
| `built_at_commit` | equals HEAD at the time of the rebuild |
| Top 15 files by node count | dominated by `app/` and `custompackages/`; no `.md`, no `package.json`, no `composer.json` |
| `custompackages/ifgf/church-operations` present | the package scaffold is indexed |
| `tests/` present | the 8 characterization files are indexed |
| Size | should fall from 7.3 MB once docs are excluded |

If the top files are still prose, the ignore rules did not apply and the rebuild is not usable —
better to know immediately than to trust a query later.

## What happens after

1. You paste this into Claude Code; it rebuilds and reports.
2. You tell me it is done. I verify from `graph.json` and analyse current architecture against the
   PRD — what exists, what is missing, what needs refactoring before WP 0C.
3. I produce the **generation manifest**: the exact `php artisan make:*` commands for WP 0C and
   Phase 1A, plus anything that should be removed.
4. Claude Code runs the manifest — cheap, correct namespaces, verified as it goes.
5. I fill in the file bodies.
6. One Claude Code wrap-up prompt: verify, test, fix, commit.
