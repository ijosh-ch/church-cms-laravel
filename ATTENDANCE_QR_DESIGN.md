# Attendance via QR — design recommendation

**Owner question, 2026-08-09:** "the QR should also carry the event information — Super Sunday,
iCare, Christmas."
**Status:** proposed design. Feeds PRD FR-02.7 and FR-04, Release 1.

---

## 1. Why event data cannot live in the member QR

| | Member identity | Event identity |
|---|---|---|
| Lifetime | Permanent — printed once | Per occurrence — changes weekly |
| Cardinality | One per member | One per event *instance* |

A printed card that says "Super Sunday" would need a sibling card for iCare, another for
Christmas, and it still could not distinguish **this** Sunday from last Sunday. Attendance is
recorded against an *occurrence*, not an event type, so an event-type QR would not be enough even
if members carried several cards.

**The information is not missing — it is just on the other side of the scan.**

---

> **Simplified by the owner, 2026-08-09 — and the simplification is right.**
> QR-B below is **not required**. The usher's phone web app carries the event and location tagger,
> so occurrence context is selected on the scanner rather than scanned from a code. That is fewer
> moving parts, fewer things to print, and it is already how upstream's `scan()` works.
> **QR-B is now an optional convenience** for opening the right occurrence quickly at a large
> event; it is not part of the MVP. Ratified in `PRD.md` §13.1.22 and FR-04.1.

## 2. Recommended design — two QRs with different jobs

### QR-A · Member card *(the one members carry)*

```
payload:  <qr_token>                  32 random chars, nothing else
lifetime: permanent, until rotated
answers:  "who"
```

### QR-B · Occurrence code *(the one that carries event information)*

```
payload:  <occurrence_token>          per occurrence, generated with the occurrence
lifetime: that occurrence only
answers:  "which event, which branch, which date"
```

**QR-B is exactly what you asked for** — a QR that means "Super Sunday, Taipei, 16 Aug 2026" or
"iCare Bethesda, 13 Aug 2026". It just belongs to the event, not to the member.

### How they combine

```
1. Leader opens the attendance app
2. Leader scans QR-B  (or picks the occurrence from a list)
       → session opens, screen shows "Super Sunday · Taipei · 16 Aug"
3. Leader scans QR-A, QR-A, QR-A …   one per arriving member
       → each scan writes attendance against the open occurrence
4. Leader finalizes
```

The leader scans the event **once**, then members **many times**. Event context is established
once per service instead of being duplicated into two hundred member cards.

This is also how the upstream code already works — `Api/AttendanceController::scan()` takes
`session_id` plus a member identifier. **QR-B is a shortcut for choosing `session_id`, not a new
mechanism.** It replaces a dropdown with a scan.

---

## 3. Why not let members scan the event QR themselves?

Worth considering, because it is the natural reading of your idea, and it is what the legacy
Google Form effectively did.

| | Leader scans members (recommended) | Members scan event poster |
|---|---|---|
| Speed for 200 arrivals | Fast — one operator, one device, no logins | Slow — 200 phones, 200 logins |
| Requires member smartphone | No | **Yes, every member** |
| Requires staff at the door | **Yes, one leader** | No |
| Remote-check-in fraud | Not possible | **Yes — photograph the poster, mark yourself present from home** |
| Branch attribution | Automatic | Automatic |
| Already built upstream | **~60%** | No |

The fraud row is the decisive one. A static poster QR can be photographed and shared; anyone could
mark themselves present without attending. Defending it means rotating the code every few seconds
on a screen — real complexity for a problem you do not have.

And your own history argues against self-service: the legacy flow asked members to pick their
location and **98.8% of scans never did** (`WORKBOOK_INVENTORY.md`). Asking members to do work at
check-in has a measured track record here, and it is not good.

**Recommendation: leader-scanned for Sunday and Christmas. iCare does not need QR at all** — with
~10 members and a leader present, a tick list with last week's attendance pre-ticked is faster
than scanning.

---

## 4. Data model

```php
// ifgf_member_profiles                      QR-A
$table->char('qr_token', 32)->unique();
$table->unsignedInteger('qr_version')->default(1);
$table->timestamp('qr_rotated_at')->nullable();

// ifgf_event_occurrences                    QR-B
$table->char('occurrence_token', 16)->unique();   // generated with the occurrence
```

`occurrence_token` can be shorter — it is short-lived, scanned by a trusted leader, and never
printed on anything a member keeps.

---

## 5. Scan flow, with the checks upstream already performs

`Api/AttendanceController::scan()` already implements items 2, 4 and 5 correctly. The work is
replacing the identifier and adding the missing columns — not rebuilding the endpoint.

```
POST /api/v1/attendance/scan   { occurrence_token, qr_token }

1. resolve occurrence_token  → occurrence        404 if unknown
2. occurrence not finalized/locked               403   ← exists today
3. leader assigned to this occurrence            403   ← EventManager exists
4. resolve qr_token → member                     404 if unknown or rotated
5. existing attendance for member+occurrence     409 + existing record  ← exists today
6. AttendanceRecorder::record(
       occurrence, member,
       status: present,
       participation_mode: onsite,
       capture_method: member_qr,
       actor: leader
   )
7. return { member_name, thumbnail, recorded_at }
```

Four changes from today:

1. `member_username` → `qr_token`. Removes the enumerable identifier.
2. `session_id` → `occurrence_token`. Adds the event context you asked for.
3. Writes go through `AttendanceRecorder`, not directly to `EventAttendee` (FR-04.1).
4. `avatar_url` moves off `Storage::disk('public')` to a short-lived signed URL (invariant 30).

---

## 6. Practical notes

**Manual fallback is not optional.** Cameras fail, cards get left at home, phones die.
`searchMember` + `markAttendee` already exist upstream and must stay — a QR system without a
manual path fails on the day it matters most (`build.md` FR-04.9).

**Rate-limit the scan endpoint.** Laravel's `throttle` middleware. A leader scanning a queue is
maybe 30/minute; anything far above that is enumeration.

**Consider offline tolerance later, not now.** If the venue's connectivity is unreliable, the
leader's device can queue scans and replay them — but that needs an idempotency key per scan and
is genuinely more complex. Ship online-only first and measure whether it is a problem.

**Printed cards should carry a human-readable fallback** — the member's name and a short code — so
a leader can find them manually when the camera will not focus.

---

## 7. Answering the original question directly

> Can the QR carry the event information?

**Yes — on the event's QR, not the member's.** One code per occurrence saying "Super Sunday,
Taipei, this date," scanned once by the leader; then member cards scanned many times against it.

That gives you event information in a QR, keeps member cards permanent and reusable, avoids
reprinting 217 cards whenever an event is added, and reuses roughly 60% of the attendance code the
fork already has.
