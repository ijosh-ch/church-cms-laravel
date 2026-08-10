# Legacy Workbook Inventory — structure only

**Source:** `Jemaat & Absensi (2).xlsx` · 19 sheets · analysed 2026-08-09
**Authority:** rank 1 — with the Google Apps Script, this defines required data and main functions
(`PRD_OPEN_QUESTIONS.md`, authority hierarchy)

> **This file contains no member data.** Column names, fill rates, distinct counts and categorical
> distributions only. No names, emails, phone numbers, LINE IDs, birthdays or addresses. The
> workbook itself is never committed and never modified (`build.md` SOURCE EVIDENCE).

---

## 1. Sheet map

| Sheet | Rows | Cols | Role |
|---|---:|---:|---|
| `Daftar Jemaat` | 317 | 33 | **Member roster** — 217 non-empty rows |
| `Daftar Absensi` | 3,234 | 9 | **Raw attendance scan log** — 3,227 timestamped |
| `Absen-TPE` | 1,005 | 116 | Weekly attendance grid, Taipei |
| `Absen-ZL` | 1,005 | 117 | Weekly attendance grid, Zhongli |
| `Absen-TPE_ZL` | 1,005 | 117 | Combined grid |
| `Summary Absen` | 55 | 80 | Monthly averages, TPE / ZL |
| `URLs Absensi` | 217 | 25 | Per-member prefilled form URLs |
| `Absensi iCare` | 1 | 6 | **Designed, never used** — header row only |
| `(Old) Daftar Jemaat` | 7 | 33 | Superseded roster |
| `Absen-{2022..2026}-{TPE,ZL}` | 27–71 | 20 | Ten historical year summaries |

---

## 2. `Daftar Jemaat` — 217 members, 33 columns, ~14 of them alive

| # | Column | Filled | Distinct | Note |
|---:|---|---:|---:|---|
| 1 | Edit URL | 99.5% | 216 | GAS-managed |
| 2 | Birthday ID | 100% | 217 | GAS-managed — Calendar event series ID |
| 3 | Full Name | 99.5% | 214 | **3 duplicate names** |
| 4 | Email Address | 99.5% | 216 | |
| 5 | iCare | 99.1% | 22 | includes a "not yet joined" value |
| 6 | Kategori | 99.5% | 4 | |
| 7 | LINE ID | **0%** | 0 | **duplicate of col 14 — dead** |
| 8 | QR Code URL | 99.5% | 216 | GAS-managed |
| 9 | Timestamp | 99.5% | 216 | form submission |
| 10 | Chinese Name | 92.2% | 198 | 17 missing |
| 11 | Tanggal Lahir | 99.5% | 209 | 215 dates + **1 stored as text** |
| 12 | Domisili Indonesia | 0.9% | 2 | dead |
| 13 | Domisili Taiwan | 99.5% | 162 | |
| 14 | Line ID | 80.6% | 172 | 172 text + **3 numeric** |
| 15 | WhatsApp Number | 99.5% | 214 | |
| 16 | Profesi saat ini | 95.9% | 7 | free text, not a controlled list |
| 17 | Tingkat Pendidikan | 91.7% | 7 | |
| 18–19 | Tahun Masuk Ajaran, Jurusan | 0.9% | 2 | dead |
| 20 | Sekolah / Kampus / Perusahaan | 95.9% | 165 | |
| 21 | **Domisili Gereja IFGF** | 99.5% | **2** | **home branch** |
| 22 | Berencana menghadiri kembali | 99.5% | 2 | |
| 23–31 | Baptis, Gereja di Indonesia, Hobi, Pernah Komsel, Pernah Melayani, Discipleship Class, Ayo Baca Alkitab, First Impression, Saran | 0.5–0.9% | 1–2 | **dead — older form version** |
| 32 | Ingin dihubungi untuk mengenal g… | **0%** | 0 | **follow-up preference — never captured** |
| 33 | Column 27 | **0%** | 0 | junk |

### Distributions

```
Kategori            College 115 · Adult 67 · Teens and Youth 33 · Kids 1
Domisili Gereja     IFGF Taipei / 台北 112 · IFGF Zhongli / 中壢 104
iCare                22 distinct groups
Profesi             Siswa/Mahasiswa 156 · Pekerja 45 · Ibu Rumah Tangga 3 · 4 one-offs
Tingkat Pendidikan  S1 135 · SMA/SMK 38 · S2 12 · Diploma 7 · S3 4 · SMP 2 · SD 1
```

### Findings

1. **Twelve columns are ≤0.9% filled and three are 0%.** Only ~14 of 33 carry real data. The dead
   ones are residue from an earlier form version — 2 straggler rows each. Do **not** model them
   as fields; treat them as an import-exception category.
2. **Duplicate LINE ID columns** — col 7 `LINE ID` (0%) and col 14 `Line ID` (80.6%). Case-differing
   names, one dead. Exactly the class of defect a case-insensitive matcher would silently merge.
3. **`Profesi` and `Tingkat Pendidikan` are free text, not controlled lists** — 7 distinct values
   each, including `bisnis`, `Toko`, `倉管` alongside proper categories. Normalise on import; do
   **not** create a MySQL enum (`build.md` TECHNICAL BASELINE 6).
4. **One malformed row.** The same ~12 columns each report exactly 1 missing value, and the Absen
   sheets carry 216 members against this sheet's 217. One row is structurally incomplete, not one
   member missing a branch.
5. **`Tanggal Lahir` has one date stored as text** and `Line ID` has three numerics among strings.
   Type coercion needed on import.
6. **Follow-up preference was never captured** (col 32, 0%). `PRD.md` L973 lists it in the required
   minimum. The requirement cannot be satisfied from this source — it is new data collection.

---

## 3. Attendance

### `Daftar Absensi` — raw scan log, 3,227 timestamped rows, 2025-10-04 → 2026-08-02

Columns: `Email Address`, `Timestamp`, `Email`, `Lokasi`, `Full name`, `iCare`, `Online`,
`WhatsApp Number`, `Column 8`.

| Field | Populated | Consequence |
|---|---|---|
| **`Lokasi`** | **40 of 3,227 (1.2%)** — Taipei 20, Zhongli 20 | **Branch attribution is absent for 98.8% of scans** |
| **`Online`** | **3 of 3,227** marked `Ya` | Online attendance effectively unused in practice |
| `Timestamp` | 3,227 | reliable |

The Google Apps Script deliberately leaves location blank so the member picks it at scan time
(`church-member-management` — QR pre-fills email, phone, name and iCare, but not location).
**Members almost never pick it.** Any migration that needs per-branch attendance history cannot
take it from this log.

### `Absen-TPE` / `Absen-ZL` — weekly grids

| Sheet | Weeks | Members | Date range |
|---|---:|---:|---|
| `Absen-TPE` | 46 | 216 | 2025-09-28 → 2026-08-09 |
| `Absen-ZL` | 46 | 216 | 2025-09-28 → 2026-08-09 |
| `Absen-TPE_ZL` | 93 | 216 | 2025-09-28 → 2026-04-26 |

Layout:

```
r1–r5   category totals block  (Adult, College, Teens and Youth, Kids, Sub-Total)
r6      No. | Full name | Email | iCare | Kategori | <DATE> | | <DATE> | | ...
r7                                                  Onsite | Online | Onsite | Online
r8+     one row per member, counts per week
```

Each week is a **merged Onsite/Online column pair**. The GAS inserts two columns after column E
weekly and merges `F6:G6`, so **the newest week is leftmost** and history extends rightward.

### Findings

7. **Attendance is a wide grid, not rows.** 46 weeks × 216 members × 2 modes ≈ 19,872 cells per
   branch to pivot into `AttendanceRecord` rows. The pivot is the migration, and the column
   headers are the only occurrence identifiers.
8. **The grid is not derived from the scan log.** 3,227 raw scans cannot populate ~40,000 grid
   cells across two branches. The grid is substantially manual. Reconciling the two sources
   against each other is a WP 0C task, and they will disagree — which the escalate-every-conflict
   rule now covers.
9. **`Absen-TPE_ZL` reports 93 weeks but ends 2026-04-26**, four months before the per-branch
   sheets. Either a stale combined view or a different week granularity. **Resolve before trusting
   it**; prefer the per-branch sheets.
10. **`Absensi iCare` has a header row and no data.** iCare attendance was designed and never
    collected. PRD FR-05 iCare attendance is therefore **new capability with no history to
    migrate** — not a port.
11. **History reaches back to 2022** via ten year-summary sheets (20 columns each), but the
    detailed weekly grids only start 2025-09-28. Pre-2025 exists only as summaries.

---

## 4. Direct answers to open questions

### Q3 — home branch mandatory? → **effectively yes in the data**

`Domisili Gereja IFGF` is 99.5% filled with exactly 2 values (Taipei 112, Zhongli 104). The single
missing value belongs to the one structurally malformed row, not to a genuine branch-neutral
member.

This **strengthens the case for nullable-in-schema**, not weakens it: the constraint would fail on
exactly one row, and that row is junk. A nullable column with a validation rule at activation
captures the real invariant — every actual member has a branch — without forcing the import to
either fabricate a value or reject a row that should become an import exception with a reason.

Recommendation stands: **nullable column, required at activation, malformed row → import
exception.**

### Q8 — which pages at launch?

Nothing in the workbook informs this. Still an owner decision.

### Follow-up preference

`PRD.md` L973 requires it; the source has none. Either drop it from the required minimum or accept
that every imported member starts with it unset.

---

## 5. Feeds forward

| Finding | Consumed by |
|---|---|
| 217 rows, ~14 live columns, 12 dead | WP 0C item 13 extraction + `ifgf_member_profiles` shape |
| 1 malformed row; 3 duplicate names | WP 0C item 14 all-row reconciliation |
| Duplicate `LINE ID` / `Line ID` | WP 0C normalisation |
| Free-text Profesi / Pendidikan | WP 0C normalisation; no enums |
| Branch 99.5% filled, 2 values | **Q3**, `ifgf_branches` seed |
| Kategori 4 values | `ifgf_member_profiles.member_category` |
| 22 iCare groups | Phase 1C.1 `ifgf_group_memberships` seed |
| **`Lokasi` missing on 98.8% of scans** | WP 0C — branch attribution for historical attendance |
| Wide grid → row pivot, ~40k cells | Phase 1D.5 historical attendance import |
| Scan log ≠ grid | WP 0C conflict volume; escalation workflow |
| `Absen-TPE_ZL` date mismatch | Resolve before import |
| `Absensi iCare` empty | Phase 1C.1 is new build, not a port |
| Follow-up preference never captured | `PRD.md` L973 correction |
