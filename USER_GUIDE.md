# IFGF prototype — how to test it yourself

**For this machine, 2026-09-08.** Everything below has been run end to end and works.

This is the walkthrough for the two things you asked to try: **registering a member**, and
**an usher recording attendance**. It also covers resetting the demo data, which you can do
as often as you like.

---

## 0. Start it

```
php artisan serve --host=127.0.0.1 --port=8080
```

Leave that running and open **http://localhost:8080**.

PostgreSQL runs as a Windows service and starts on boot, so there is nothing else to start.
(MySQL is no longer used by this app — the database is now `ifgf_cms` on PostgreSQL 17.)

### The two sites

| Site | Locally | Who it is for |
|---|---|---|
| Member | `http://localhost:8080/app` | members: register, see their own QR and attendance |
| Usher | `http://localhost:8080/usher` | ushers: scan, tick a roster, read the report |

In production these become `member.ifgf.site` and `attend.ifgf.site`. Locally they are URL
prefixes so you do not have to edit a hosts file.

### Test accounts

Created by `php artisan db:seed --class=IfgfDevArenaSeeder`. Re-run it any time; it resets
the passwords rather than duplicating the accounts.

| Role | Email | Password | Where to sign in |
|---|---|---|---|
| Usher | `usher@ifgf.test` | `usher-password` | `/usher/login` |
| Member | `member@ifgf.test` | `member-password` | *not yet — see below* |

> ⚠ **Members have no login yet.** Google and Apple sign-in are P2. Until then the member
> pages identify you by a link, not a password — see scenario 1. The `member@ifgf.test`
> account exists so you can confirm that a *non-usher* is correctly refused at `/usher`.

> 🔴 These passwords are public knowledge in this file. They exist only on this machine and
> must never be seeded anywhere reachable from the internet. The seeder refuses to run
> outside `APP_ENV=local` and outside the known local database names.

### Language

Every page has a language picker in the top right: **Bahasa Indonesia**, **English**,
**繁體中文**. It changes labels only — `iCare Linkou`, `Belum mengikuti`, `Adult`, `College`
and so on are stored data and never translate, because the spreadsheet import matches on
them exactly.

---

## 1. Register a new member

**Go to http://localhost:8080/app/register**

This replaces the Google registration Form.

1. **Name** and **Branch** are required. Everything else is optional.
2. **Email or WhatsApp — at least one.** The form refuses both blank, because those two are
   what duplicate detection matches on; a member with neither could never be found again.
3. **Phone format does not matter.** `0912345678`, `+886912345678` and `0912 345 678` are
   all stored as you typed them and matched as `886912345678`.
4. **iCare is optional.** "Belum mengikuti iCare" is a real answer — 62 of the 217 real
   members are in that state. Choosing it stores *no* group row rather than putting them in
   a group called "not in a group".
5. Press **Register**.

**What you get:** a welcome page with the member's **QR code** and an 8-character **short
code**. Screenshot it — you will use it in scenario 2.

### Things worth trying

| Try this | What should happen |
|---|---|
| Register the same email twice | Refused: *"Someone is already registered with this email or number."* |
| Register a **different** email but the same phone written as `+886…` instead of `09…` | **Also refused.** This is the duplicate the old Apps Script could never catch — its phone cleaner had a no-op bug, so one number written three ways was three people. |
| Leave email *and* WhatsApp blank | Refused, with both fields flagged. |
| Set a birthday in the future | Refused. |
| Register with a birthday | The birthday is added to the church calendar automatically. In local development that calendar is an in-memory fake — nothing is written to the real Google Calendar. See §5. |

### After registering

The welcome page links to **My page**, which shows that member's attendance history, their
iCare, and their QR again. The URL carries an opaque UUID (`/app?member=<uuid>`), never a
sequential id — so you cannot browse to member 1, 2, 3.

---

## 2. Usher: record attendance

**Go to http://localhost:8080/usher/login** and sign in as `usher@ifgf.test` /
`usher-password`.

You land on the **Scan** page.

### Step 1 — choose the service

At the top, pick which service you are recording for, e.g. *Taipei — Sun, 06 Sep 2026*.

🔴 **This is the important bit.** The branch comes from the service you picked, never from
the member. In the old system the member chose their own location when they scanned, and
almost never did — `Lokasi` is filled on **40 of 3,516** rows, so per-branch history is
simply not recoverable from that log. Asking the usher instead removes the question from
the member entirely.

### Step 2 — record, three ways

**a) QR code (the normal way).** Point the camera at a member's QR. It records on sight.
The camera needs `https` or `localhost` — `localhost:8080` counts, so it works.

**b) Short code (when a camera will not read).** Type the 8-character code from the
member's page and press **Look up**.

To find a real member's code for testing, open their page (`/app?member=<their public_ref>`)
or read one from the database:

```sql
SELECT m.full_name, c.short_code
FROM ifgf_member_credentials c
JOIN ifgf_members m ON m.id = c.member_id
WHERE c.revoked_at IS NULL
LIMIT 5;
```

Codes are deliberately not printed in this file - each one is a working attendance
credential for a real member.

**c) NFC card.** Tap **Tap NFC card**, then hold an NFC tag to the phone.
Only works in **Chrome on Android** — Web NFC does not exist on iPhone.

> ⚠ **NFC reads cards, not phones.** An Android phone in card-emulation mode randomises its
> NFC ID on every tap, and iPhone does not allow third-party card emulation in Taiwan at
> all. So this reads NTAG213/215/216 sticker cards (about US$0.30 each), which is what you
> would hand out. iPhone members use the QR, which every phone can display.

### What you should see

- First scan: **"Attendance recorded · Taipei 2026-09-06"**
- Scan the same code again: **"Already marked present"** — and still only one row. A camera
  re-reads a code many times a second; that must never double-count.
- An unknown, revoked or garbled code: **"Code not recognised."** — always exactly that
  message. It deliberately never says *which*, because a more helpful message would let
  someone test guesses against the short-code space.

### Step 3 — the manual roster

Open **Roster** (bottom nav on a phone, top nav on a laptop).

- Filter by **branch**, by **iCare**, or search by name.
- **Tap a row** to mark present; tap again to undo. The whole row is the target, not the
  small tick box.
- The counter at the top updates as you go.
- Members with no iCare are still listed — they are not hidden just because they have no
  group.

### Step 4 — the quarterly report

Open **Reports**. The imported history spans **2025 Q4 through 2026 Q3**:

| Quarter | Total | Taipei | Zhongli |
|---|---:|---:|---:|
| 2025-Q4 | 199 | 79 | 120 |
| 2026-Q1 | 1,117 | 514 | 603 |
| 2026-Q2 | 1,284 | 697 | 587 |
| 2026-Q3 | 718 | 255 | 463 |

They sum to 3,318 - every imported attendance row.

You get the same shape as the old `Summary Absen` sheet: attendance by category and branch,
an average per service, an online/onsite split, and a week-by-week bar chart. Anything you
just recorded appears here immediately.

---

## 3. Reset the demo data

For an EMPTY database only - the real data is now loaded, so this will refuse unless
you purge first. It **purges first, every time**, then rebuilds the same
8 members, 4 iCare groups, 12 services and 29 attendance rows.

```
php artisan ifgf:demo:seed
```

Purge without reseeding:

```
php artisan ifgf:demo:purge
```

🔴 **The purge is precise, not a wipe.** It deletes only rows flagged `is_demo`, so members
you registered yourself in scenario 1 **survive** — they are real registrations, not demo
data. If you want a completely clean slate, delete them from the member list or reset the
database.

To also clear demo events from Google Calendar, add `--calendar`. That deletes only events
tagged `ifgf_demo`; a real member's birthday is invisible to it.

---

## 3b. The real data is loaded

The legacy workbook has been imported. `ifgf_cms` now holds:

| | |
|---|---|
| Members | **217** |
| iCare groups | **21** ("Belum mengikuti" is correctly not one of them — 63 members have no group) |
| Sunday services | **102** (51 weeks × 2 branches, 2025-09-28 → 2026-09-13) |
| Attendance records | **3,318** |
| Google Calendar links | **217** — every existing birthday event series id, carried verbatim |
| Raw scan log | **3,516** rows, kept as JSONB |

Re-import at any time — it updates rather than duplicating:

```
php artisan ifgf:import:workbook "<path to the .xlsx>"
```

That is a **dry run**: it reports exactly what it would do and rolls everything back. Add
`--commit` to write.

### The four things it could not do cleanly

Nothing was auto-corrected. Each is a row in `ifgf_import_conflicts` for you to judge:

| Where | What |
|---|---|
| `Daftar Jemaat!53` | A birthday reading `5/11/0005`. Left empty rather than guessed. |
| `Daftar Jemaat!83` | Phone normalises to the same number as another member's. |
| `Daftar Jemaat!166` | Same again, a different pair. |
| `Absen-TPE!213` | Marked **both** Onsite and Online for 2026-08-02. Onsite kept. |

The two phone duplicates are the kind the old system **could never detect** — it compared
raw strings, so one number written `0912…` and `+886912…` was two different people.

To see them:

```sql
SELECT stage, kind, row_ref, message FROM ifgf_import_conflicts ORDER BY id;
```

🔴 **Demo data and real data do not mix.** `ifgf:demo:seed` now refuses to run while real
members exist, because the fixture uses the same branches and Sunday dates — seeding would
put invented attendance into your real quarterly report. It did exactly that once, before
the guard was added. Use `--force` only if you accept the mixing.

---

## 4. What is NOT built yet

Being straight about this so you do not go looking for it.

| Missing | Notes |
|---|---|
| **Google / Apple sign-in** | P2. Members are identified by an opaque link for now. Apple also needs a US$99/yr developer account. |
| **Editing a member after registration** | Read-only for now. |
| **Real Google Calendar writes** | Needs a service account; see §5. |
| **iCare weekly attendance** | The schema supports it (`ifgf_service_occurrences.group_id`), but only Sunday services are generated so far. |
| **Rotating or reissuing a lost QR from the UI** | The service does it (`MemberCredentialService::rotate`), but there is no button yet. |
| **Telling members their old QR stopped working** | All 217 have a NEW code. Their old Google Form QR is dead by design - that needs a communication plan before go-live. |

---

## 5. Google Calendar in development

**Nothing you do locally touches the real church calendar.** The app binds an in-memory
fake unless `IFGF_CALENDAR_DRIVER=google` is set deliberately, and the test suite forces
the fake regardless of the environment. Birthdays "sync" successfully in the UI and are
simply held in memory for the life of the process.

To point it at the real calendar later:

```
composer require google/apiclient
```

then set `IFGF_GOOGLE_SERVICE_ACCOUNT`, `IFGF_BIRTHDAY_CALENDAR_ID` and
`IFGF_CALENDAR_DRIVER=google`. The service-account JSON goes in `~/.ifgf/`, never in the
repository.

---

## 6. Where things live

| What | Where |
|---|---|
| Database credentials | `C:\Users\ianjo\.ifgf\postgres.env` — machine-wide, outside every repo, entered once |
| Live database | `ifgf_cms` on PostgreSQL 17 (104 tables: 93 upstream + 11 `ifgf_`) |
| Test database | `ifgf_cms_test` — the suite drops and rebuilds tables here |
| Site config | `config/ifgf-sites.php` |
| Design and rationale | `PG_MIGRATION_PLAN.md` |

---

## 7. If something goes wrong

**"Refusing to run tests against database […]"** — the harness only touches a database whose
name contains `test`. Check `IFGF_PG_TEST_DATABASE` in `~/.ifgf/postgres.env`.

**Usher pages redirect you to the login** — the account is missing the `usher` role. Re-run
`php artisan db:seed --class=IfgfDevArenaSeeder`.

**"Camera unavailable"** — expected in an embedded preview pane; use a real browser tab at
`http://localhost:8080/usher`, or use the short code.

**A page looks stale after an edit** — `php artisan view:clear`.

**Run the whole test suite** — 196 tests, about 5 minutes:

```
php artisan test
```
