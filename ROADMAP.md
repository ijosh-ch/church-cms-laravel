# Roadmap — an integrated set of church tools

**Date:** 2026-10-04 · **Owner:** Ian Joseph Chandra

> **This file is not scope.** `PRD.md` is. Nothing here is built until the owner moves it into a
> PRD of its own. It exists so each tool can be developed later, one at a time, without redoing
> the foundations. Hard cap: 5,500 tokens.

## The idea

One list of people and one list of teams, kept in the member app (`PRD.md` 3.0). Every other tool
is a separate, small app that refers to those two lists instead of keeping its own.

**Why.** So the church can see how each member and volunteer is growing, and decide what to do
next from data rather than impressions. Every module below either records an activity
(attendance, serving, a milestone, a registration) or reads those records back as analysis (M6).

**Every module inherits `STANDARDS.md` unchanged:** its own test, development, staging and
production databases; tests written with the code; the security and privacy checklists and a
`/security-review` at every gate; staging through Cloudflare before production. And every module
is built on `dev`, from its own PRD, with the prompt in `SESSION_PROMPT.md`.

| # | Module | Status | Depends on |
|---|---|---|---|
| M1 | Members, teams, attendance | **Now** — `PRD.md` 3.0, ten work packages | — |
| M2 | Photo-assisted iCare attendance | Future | M1 in production, consent in place |
| M3 | Camera attendance at the Sunday entrance | Future | M2 piloted and measured |
| M4 | Asset management on Snipe-IT | Future, can start any time | `teams` from M1 |
| M5 | Joining the tools together | Grows with each module | M1 |
| M6 | Growth and engagement analytics | Future | a few quarters of M1 data |
| M7 | Discipleship journey | Future | M1 |
| M8 | Event ticketing | Future | M1; M9 for paid tickets |
| M9 | Offerings: cash, transfers, bank matching | Future, last | a treasurer's process agreed first |
| M10 | Event photo finder | Future | a profile photo per member; the M2 face worker |

What M1 already provides for the others: `members.public_ref` (a stable, non-guessable person
key), `teams.id` (a stable division key), `attendances.method` (a string — new methods need no
schema change), and one attendance per member per occurrence. **M1 keeps history instead of
overwriting it** — every attendance row, `member_team.left_at`, the `change_requests` log — which
is what makes M6 possible later without new data collection.

---

## M2 — Photo-assisted iCare attendance

**Goal.** The leader uploads the weekly group selfie; the app proposes who is in it; the leader
checks the ticks and submits. The photo is a faster way to fill the FR-9 list, never a replacement
for the leader's confirmation.

**How.**
1. A member opts in from their profile and enrols with three selfies. A child is enrolled by a
   parent in the same household.
2. On upload, faces are found and compared **only against that group's enrolled members** — a
   dozen candidates, not the whole church, which keeps it accurate and cheap.
3. The tick list opens pre-filled: confident matches ticked, uncertain ones marked "check",
   unmatched faces offered as "guest?". Nothing is saved until the leader presses submit.
4. Rows are stored with `method = photo`.

**Technical shape.** A small self-hosted worker beside the app (an open-source face-embedding
model); no cloud face service. New table `face_templates (member_id, vector, consented_at)`,
encrypted. The uploaded photo is deleted as soon as matching finishes. At this size no vector
database is needed.

**Decide first.** Consent wording and how withdrawal deletes the template; whether photos may be
kept for the group's own album (default: no); the pilot group.
**Prior analysis:** `git show feat/postgresql-multisite:ICARE_PHOTO_ATTENDANCE.md`.

## M3 — Camera attendance at the Sunday entrance

**Goal.** A phone or tablet at the door recognises enrolled members as they walk in and shows the
usher who came and when. QR stays as the fallback and for everyone who has not opted in.

**How.**
- **The device is assigned the session.** The usher opens a Sunday Service occurrence on it —
  Zhongli 10:00–12:00 or Taipei 15:00–17:00 — and everything that device records belongs to that
  occurrence. Volunteers who arrive early are counted in the right session because the session
  comes from the device, not from the clock.
- **No double attendance.** M1's rule already holds: one row per member per occurrence. A second
  sighting only updates "last seen".
- Start with **usher confirms each match with one tap**. Move to automatic acceptance only above a
  threshold proven in the pilot.
- Small addition to M1 when this starts: `events.starts_at` / `ends_at`, so a device can suggest
  the right session by itself.

**Gates before any build.** M2 has run for a quarter with measured wrong-match and missed-match
rates; a clear majority of regular attenders have opted in; a notice at the entrance; a written
answer to "what happens to a visitor's face" (default: never stored, never matched).

**Privacy rule for M2 and M3.** Face data is the most sensitive data the church would hold.
Opt-in only, never a condition of attending, templates not photos, deleted on request, and never
used for anything except attendance.

---

## M4 — Asset management (Snipe-IT)

**Goal.** Every item is labelled, belongs to a division, has a home position, and can be checked
out and back in by scanning — so nothing goes missing after an outdoor event.

**Map the church onto Snipe-IT's own concepts** (no custom code needed for these):

| Church need | Snipe-IT feature |
|---|---|
| Division that owns the item | **Company** — one per ministry team, same names as M1 `teams` |
| What kind of thing it is | **Category** — Audio, Video, Lighting, Instrument, Computer, Cable, Furniture, Decoration, Container |
| "All electronics", across divisions | Custom field **Class** — Electronics / Non-electronics / Furniture / Consumable |
| Brand and model | **Manufacturer** + **Asset model** |
| Where it lives | **Location**, nested: Branch → Room → Shelf or Rack |
| A box | An **asset** of category Container, with its own tag and home location |
| What is inside a box | Custom field **Home box** (the box's asset tag) on each item |
| Taking gear to an event | A **Location** per event; bulk check-out to it, optionally from a predefined **Kit** |
| Coming back | Bulk check-in by scanning; whatever is still checked out to the event is missing |
| Is everything in its place? | **Audit**: scan the shelf, then each item on it; unaudited items are listed |
| Cables, batteries, tape | Tagged individually only when worth tracking; otherwise **Accessories** or **Consumables** counted by quantity |

**Information on every asset**

- Identity: asset tag, name, category, class, manufacturer, model, serial number, photo
- Ownership and place: division, home location, home box, person responsible
- Condition: status (ready, in use, in repair, lost, retired), condition note, last audit date
- Purchase: date, cost, supplier, warranty end — also covers donated items (cost 0, donor noted)
- Electrical: voltage (110 V / 220 V / both) and plug type — gear brought from Indonesia matters
- Cables only: type, connector A, connector B, length
- Lending: may it leave the building, and who approves

**Asset tag.** `DIV-NNNN` — a three-letter division code and a running number (`SND-0042`,
`MUL-0117`). Short enough for a cable, readable without a scanner, and it says whose it is.

**Labels.**
- Equipment and boxes: QR code + tag + division + name, about 50 × 25 mm.
- Cables: a wrap-around or flag label near each end with a small 2D code (Data Matrix reads
  better than QR at this size), the tag and the length, e.g. `SND-0042 · XLR · 5 m`.
- Shelves and box positions: a location label, so "is this box in the right place" is two scans —
  the position, then the box.

**Decide first.** One Snipe-IT for both branches or one each (default: one, branches as top-level
locations); which divisions start; who may check out; the label printer.

## M5 — Joining the tools together

- **One sign-in.** Snipe-IT can use Google sign-in; M1 already does. Same Google account, same
  person. Cloudflare Access in front of every admin area.
- **Teams flow one way.** M1 owns the list of teams and leaders. A scheduled job pushes them into
  Snipe-IT through its REST API as Companies and users. Nothing is typed twice.
- **An API only when a second app needs it.** M1 ships without one. The first real consumer gets a
  small, read-only, token-protected API for exactly what it reads — teams, leaders, a member's
  name by `public_ref` — and nothing else.
- **Each tool keeps its own database.** They share keys, never tables.

---

## M6 — Growth and engagement analytics

**Goal.** See how the church, its members and its volunteers are growing, from what people
actually do rather than from a headcount alone.

**Questions it answers — all from M1 data, no new forms:**
- Church: attendance per branch and category by week, month, quarter and year; newcomers per
  month; how many visitors come back; how many regulars have gone quiet.
- Member: which events each person joined this quarter (Sunday, iCare, classes, special events)
  and an activity level — active, occasional, drifting, new — defined by the church, not by code.
- iCare: size and weekly attendance rate per group; groups growing or shrinking.
- Volunteers: people serving per team over time; how many members serve at all; who leads.

**From numbers to decisions.** A quarterly review page that puts the figures next to what they
suggest: members who have gone quiet → a follow-up list for their iCare leader; visitors who did
not return → a welcome follow-up; a group that keeps growing → a candidate to split; a team with
one leader and no deputy → a succession gap. The page proposes; pastors and leaders decide.

**Shape.** Read-only report pages on top of M1's tables; no second database until the queries are
slow, which at this size is years away. **Decide first:** the activity definitions (for example
"active = 3 of the last 4 Sundays"), and who may see per-person activity — it is pastoral data,
so default to pastors and IT admins, with an iCare leader seeing only their own group.

## M7 — Discipleship journey

**Goal.** Know where each member is on the journey: baptised or not, classes completed, serving.

**Shape.** One new table, `milestones (member_id, type, achieved_on, place?, note?)`, for things
that happen once — baptism, membership, completing a course. Classes themselves are already M1
events of type `class`, so attendance at them is recorded the normal way and completion can be
derived from it. Serving is already `member_team`. **Decide first:** the list of milestones and
classes (the earlier COME–GROW–SERVE–LEAD analysis is in
`git show feat/postgresql-multisite:PRD.md`, FR-06), and who records a baptism.

## M8 — Event ticketing

**Goal.** Registration for special events — a retreat, Christmas — with limited places, and
check-in at the door.

**Shape.** `event_registrations (occurrence_id, member_id | guest details, status, ticket_ref)`.
A member's ticket is their existing QR; a guest gets a ticket QR by email. Check-in is the M1
usher scan page pointed at that occurrence, so attendance and the M6 reports work unchanged.
Free events need nothing more. Paid events wait for M9. **Decide first:** capacity and waiting-list
rules; whether guests may register without becoming members.

## M9 — Offering and payments

**Goal.** One place where every offering is recorded and reported — cash today, bank transfers
next, the bank itself later — with a clear trail. Today offerings are cash, counted and reported
by hand.

**Build it in four steps, and last.**
1. **Cash recording.** After each service the counters enter the cash by date, branch and
   purpose; two people sign off the count. Weekly, monthly and quarterly reports per purpose and
   branch. This alone replaces the current manual report.
2. **Member-submitted transfers.** A signed-in member records an offering they sent by bank
   transfer: the amount, the purpose (picked from a suggested list, or typed), the date, how
   they want to appear, and a photo or PDF of the transfer as proof. It stays **pending** until
   the treasurer checks it against the bank statement and marks it verified or rejected.
3. **Bank statement matching.** The treasurer uploads the bank's statement export; the app
   matches lines to pending submissions by amount, date and the sender's last account digits, and
   lists what it could not match on either side.
4. **Direct bank link**, only if the church's bank offers one — for example a collection service
   that gives each member a personal virtual account number, so a transfer identifies its giver
   by itself. Paid event tickets (M8) would use a hosted checkout from a licensed provider; the
   app never sees or stores card numbers.

**How a giver appears in the offering table** — their choice, per offering, changeable later:
- **Name**
- **Code** — a short fixed code only they recognise (initials plus two digits, e.g. `IJ-47`),
  unique in the church, so they can find their own row without being identified
- **Hidden** — counted in the totals, not listed

The treasurer always sees the real giver. Everyone else sees the table as the giver chose.

**Shape.** `offerings (member_id?, branch_id, purpose_id, amount, currency, method cash|transfer,
given_on, display name|code|hidden, proof_file?, status pending|verified|rejected, verified_by,
verified_at, bank_ref?)` and `offering_purposes (name, is_active, sort)` — a real table this time,
because the treasurer edits the list. Starting suggestions: Tithe, Sunday offering, Thanksgiving,
Missions, Building fund, Special event, Other.

**Rules.**
- A separate app with its own database and its own, much smaller, list of people allowed in.
- Proof files are private: stored outside the public folder, images and PDF only, size-limited,
  viewable by the uploader and the treasurer, and deleted a fixed time after verification.
- Every change is logged and nothing is ever deleted. A correction is a new entry that reverses
  the old one. Two people approve corrections and refunds.
- A submission is a claim, not money. Only verified rows count in reports.

**Decide first, with the treasurer and church leadership:** the purposes; who besides the
treasurer may see the offering table at all; how long proof files are kept; receipts; what the
church's bank can provide (statement format, virtual accounts, any API); and what local rules
apply to a religious organisation's donation records.

---

## M10 — Event photo finder

**Goal.** After an event the documentation team uploads the photos, the app works out who is in
each one, and once the team publishes them every member can see and download the photos they
appear in — and only those. The point is to save the documentation team the sorting.

**Needs first — a profile photo.** Registration and the profile page gain a required photo:
JPEG, PNG or WebP, re-encoded by the server to a small WebP (which drops location metadata) and
kept privately as `members/{public_ref}.webp` — no name in the path, no path column. Beside it a
separate, optional tick, "use my photo to find me in event photos" (`face_consent_at`), because
matching faces is a different use from showing a profile picture. Existing members are asked on
their home page. This step is small and can be pulled into M1 whenever the owner says so.

**How.**
1. The documentation team uploads an event's photos against its occurrence. The worker finds the
   faces and compares each with the profile photos of consenting members. Confident matches are
   saved; uncertain ones wait in a review queue ("Is this Maria?"), and each confirmation
   improves that member's reference.
2. Nothing is visible outside the team until they press **publish**.
3. A member's "My photos" page lists, per event, the photos they are in; download one, or all of
   an event as a zip. A parent also gets their children's.
4. The team can export a CSV (photo file → people in it). It is an export of the table, not the
   store.

**Keeping it small.**
- **One copy of each photo, never a copy per person.** "Who is in it" is rows in a link table.
- The server keeps a web-size version (about 2,000 px, ~0.5 MB) and a thumbnail (~30 KB). Camera
  originals (5–10 MB each) stay in the documentation team's own archive; the row records where.
- Rough size: an event of 1,000 photos ≈ 0.5 GB plus 30 MB of thumbnails; its database rows are a
  few hundred KB.
- Paths come from keys, never from names:
  `photos/{year}/{occurrence_id}/{photo_id}.webp` and `…/thumb/{photo_id}.webp`.
- The file's hash is stored, so the same photo uploaded twice is kept once.
- Tables: `photos (id, occurrence_id, file_hash, width, height, taken_at, original_ref?,
  status draft|published, uploaded_by)` and `photo_faces (photo_id, member_id?, x, y, w, h, score,
  confirmed_by?)`. It shares `face_templates` and the worker with M2.
- Files are private, served to signed-in members through expiring links. The server's disk to
  start; object storage (for example Cloudflare R2) when the disk is outgrown.

**Decide first.** A group photo shows other people too — is "you may download any published
photo you are in" acceptable (default: yes, members only, no public links)? People without
consent are never matched but still appear in others' photos. How a member asks to be removed
from a photo. How long event photos are kept.

---

## Suggested order

1. **M1**, through to cutover from the Google Form and the Apps Script.
2. **M4**, labelling one division first — it waits on nothing but `teams`.
3. **M6**, once two or three quarters of M1 data exist — it is the cheapest module and shows
   what the others are worth.
4. **M7**, then **M8** for free events.
5. **M2** with one willing iCare group, then **M10** on the same face worker; **M3** only after
   M2's numbers justify it.
6. **M9** last — it carries the most risk and needs decisions from outside IT.
