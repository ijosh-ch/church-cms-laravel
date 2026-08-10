# Legacy Google Apps Script — behaviour inventory

**Source:** `church-member-management` (clasp/GAS, V8 runtime, `Asia/Taipei`) · analysed 2026-08-09
**Authority:** rank 1 — with the workbook, defines required data and main functions
**Companion:** `WORKBOOK_INVENTORY.md` (data shape) · this file covers behaviour

> **No secrets in this file.** `config.js` holds the live registration form ID, attendance form ID
> and entry IDs, birthday Calendar ID, spreadsheet ID, and admin email. Those are **access
> identifiers and must never be committed** to this repository (`build.md` SECURITY 2). They are
> referenced here by name only.

---

## 1. What the running system actually does

Single trigger, `onFormSubmit`, on the registration form:

```
onFormSubmit(e)
├── open spreadsheet → sheet "Daftar Jemaat"
├── checkIfResponseIsEdited(response, sheet)
├── getMemberDetailsFromResponse(response)   → {englishName, chineseName, birthday,
│                                               iCare, phone, email, timestamp}
├── if EDITED   → checkAndUpdateBirthdayIfChanged(member, sheet)
├── if NEW      → addEditUrlSpreadsheet(response, sheet)
│                 addBirthdayToCalendar(member, response, sheet)
├── generatePrefilledUrl(member)             ← both paths
├── updateSheetWithQrCodeUrl(...)
├── generateQRCodeBlob(...)
└── sendQRCodeByEmail(member, blob)
```

On any error it emails the script owner and rethrows.

Separate entry points: `doGet` serves an Indonesian member portal (`index.html`); `editMember`
emails a member their form edit link; `addWeeklyAttendanceColumns` runs weekly;
`openRegistrationForm` / `closeRegistrationForm` run on a Saturday/Monday schedule;
`syncAllBirthdays` is a manual reconciliation.

---

## 2. Requirements this establishes

| # | Behaviour | Maps to |
|---|---|---|
| R1 | Registration is a Google Form; submission creates the member | PRD FR-01, Phase 1A.3 |
| R2 | Members self-edit via a stored per-member edit URL | Phase 1A.3 activation |
| R3 | Birthday auto-creates a recurring Calendar event; the series ID is stored per member | PRD FR-08, Phase 1D.1 |
| R4 | Birthday edits update the series in place, falling back to delete-and-recreate | Phase 1D.1 |
| R5 | Every member gets a QR encoding an attendance-form URL | PRD FR-13, Phase 1A.4 |
| R6 | QR is emailed with an HTML welcome message | Phase 1A.3 |
| R7 | Attendance is a second Google Form; the QR pre-fills it | PRD FR-04, Phase 1B.3 |
| R8 | Two branches, TPE and ZL, each with its own attendance sheet | `ifgf_branches` |
| R9 | Weekly attendance columns are inserted by a scheduled job | Phase 1B.1 occurrences |
| R10 | Duplicate detection matches on **email or phone** | WP 0C item 13 |
| R11 | Registration form opens Saturday, closes Sunday night/Monday | Phase 1A.3 |
| R12 | Member portal is Indonesian, mobile, with dark mode | Phase 1A.4 |

---

## 3. Conflicts with the PRD — owner decision required

The owner's rule is that legacy and PRD disagreements escalate rather than auto-resolve. Three do.

### C1 — **The QR payload leaks member contact data.** Highest severity.

`qr-code.js:42 generatePrefilledUrl()` builds the QR content as:

```
{attendance form URL}?usp=pp_url
  &entry.{EMAIL_ID}=<member email>
  &entry.{PHONE_ID}=<member WhatsApp number>
  &entry.{NAME_ID}=<member full name>
  &entry.{ICARE_ID}=<member iCare group>
```

That URL **is** the QR code. Anyone who photographs a member's QR — printed, on a phone screen,
over a shoulder, in a group photo — reads their **email address, WhatsApp number, full name and
iCare group** in plaintext. No token, no signature, no expiry.

This contradicts three separate requirements:

| Requirement | Source |
|---|---|
| "Do not reveal … contact, iCare, pastoral, or private data through Calendar titles, **QR payloads**, public routes, or logs" | `build.md` SECURITY 8 |
| "Opaque, rotatable member QR" | PRD FR-02, Phase 1A.4 |
| "Do not expose sequential IDs in public URLs or QR payloads" | `build.md` TECHNICAL BASELINE 7 |

**It is also not rotatable.** The URL is a pure function of the member's own data, so regenerating
it produces an identical code. There is no revocation path — if a QR leaks, the only remedy is
changing the member's email or phone.

**Recommendation: the PRD wins here.** This is not a legacy behaviour worth preserving; it is a
data-exposure defect that the PRD already independently identified and specified a fix for. The
replacement is an opaque rotatable token resolved server-side.

**Migration consequence:** every existing member QR must be reissued at cutover. Old printed or
saved QRs stop working by design. That needs a member communication plan — it is the most visible
user-facing change in the whole migration.

### C2 — Attendance location is optional, and members skip it

`config.js` defines a `LOCATION` entry ID, but `generatePrefilledUrl` deliberately omits it so the
member picks their branch at scan time. `WORKBOOK_INVENTORY.md` shows the result: **`Lokasi` is
populated on 40 of 3,227 scans (1.2%)**.

The PRD requires attendance to belong to a specific occurrence at a specific branch. Options: bind
the branch to the QR-issuing context, infer it from the leader's assigned scope at scan time
(PRD's model — leader-scanned, assignment-scoped), or keep it member-selected and accept the gap.

**Recommendation: the PRD's leader-scoped model resolves this structurally** — attendance is
recorded against an occurrence the leader is assigned to, so the branch is known without asking
the member.

### C3 — Phone normalisation is incomplete, and duplicate detection depends on it

`main.js:590 cleanPhoneNumber()` strips spaces, hyphens, brackets and dots, and converts a leading
`00` to `+`. The leading-zero branch is a **no-op**:

```js
} else if (cleaned.startsWith('0') && !cleaned.startsWith('+')) {
  cleaned = cleaned; // Keep as is for now
}
```

So `0912345678`, `+886912345678` and `886912345678` are three distinct keys for one person.
`checkIfMemberExists` matches on **email or phone**, so phone-based duplicate detection silently
fails across formats — and the workbook has Taiwanese and Indonesian numbers mixed.

**Consequence for WP 0C:** do not trust legacy phone matching. Normalise to E.164 with an explicit
default region during import, and expect duplicates the legacy system never detected. Email
matching is sound — `trim()` + `toLowerCase()`.

---

## 4. Mechanics worth preserving exactly

### Weekly attendance column insert — `spreadsheet.js:817`

```
for each sheet in ['Absen-TPE', 'Absen-ZL']:
    insertColumnsAfter(5, 2)      # two new columns at F, G
    copy H:I → F:G                # carry previous week's format/formulas forward
    re-flatten H1:I5 to values
    merge F6:G6                   # the date header spans the Onsite/Online pair
    autoResizeColumns(6, 7)
```

Confirms: **newest week is leftmost**, columns A–E are the identity block
(No., Full name, Email, iCare, Kategori), and each week is an Onsite/Online pair under a merged
date header in row 6.

**This explains the `Absen-TPE_ZL` anomaly.** The job iterates `SPREADSHEET.sheets.ABSEN`, which
lists only `Absen-TPE` and `Absen-ZL`. **`Absen-TPE_ZL` is not maintained by the script** — which
is exactly why `WORKBOOK_INVENTORY.md` found it stalled at 2026-04-26 while the per-branch sheets
run to 2026-08-09. Treat it as abandoned; do not import from it.

### Birthday Calendar strategy — `calendar.js`

Stores the recurring event series ID per member ("Birthday ID" column, 100% populated — the only
fully-populated column in the roster). On edit: attempts `setRecurrence()` in place, falls back to
delete-and-recreate; patches details only when the date is unchanged; cleans up by name when no ID
exists. `syncAllBirthdays()` reconciles everything.

This is a **more careful design than the PRD assumes** and should be carried forward — PRD FR-08's
"same-calendar adoption, wrong-calendar recreation, tombstones" maps onto it directly.

### Identity resolution — `spreadsheet.js`

`addEditUrlSpreadsheet` searches **bottom-up** for a matching email and takes the most recent row
lacking an edit URL, falling back to the last row. Combined with duplicate names in the roster,
this is the legacy identity rule WP 0C must reproduce or deliberately supersede.

---

## 5. Not carried forward

| Legacy | Why |
|---|---|
| QR payload with contact data | **C1** — replaced by opaque rotatable token |
| Google Forms as the registration UI | PRD specifies an in-app registration flow |
| `Absen-TPE_ZL` | Unmaintained by the script; stale |
| Stale `test.js` symbols | `CONFIG.FIELD_TITLES`, `getEntryId`, `generateQRCodeBlobs`, `sendQRCodesByEmail` reference an older config shape and error at runtime |
| Garbled header in `calendar.js` | Known save artifact, lines 8–29; functions below are intact |

---

## 6. Feeds forward

| Finding | Consumed by |
|---|---|
| **C1 QR leaks contact data; not rotatable** | **Owner decision**, then Phase 1A.4 + cutover comms |
| C2 branch missing on 98.8% of scans | WP 0C attendance attribution; Phase 1B.3 |
| C3 phone normalisation broken | WP 0C item 13 duplicate detection — normalise to E.164 |
| Duplicate rule = email OR phone | WP 0C item 13 identity map |
| Birthday series ID per member, 100% populated | Phase 1D.1 same-calendar adoption |
| Weekly insert mechanics | Phase 1D.5 historical grid pivot |
| `Absen-TPE_ZL` unmaintained | Exclude from import |
| Config holds live IDs | WP 0D secret inventory — never commit |
