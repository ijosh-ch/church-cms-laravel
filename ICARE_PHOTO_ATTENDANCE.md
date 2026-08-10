# iCare photo attendance — design note

**Raised by the owner, 2026-08-09.** Proposed `PRD.md` addition; not yet approved.
**Status:** design only. **No biometric processing may begin before WP 0D privacy approval.**

---

## 1. Correction to the record

`WORKBOOK_INVENTORY.md` reported that the `Absensi iCare` sheet contains a header row and no data,
and `PRODUCTION_PATH.md` used that to defer FR-05 to Release 2. **That inference was wrong.**

iCare small groups meet **weekly on weekdays**, each has a leader, and attendance is taken per
member. What the empty sheet actually shows is that **the spreadsheet was never the tool for it** —
the practice exists, the tooling does not. Data absence is not capability absence.

**FR-05 returns to Release 1.** Session estimates updated in `PRODUCTION_PATH.md`.

---

## 2. What the owner asked for

Upload one photo of an iCare meeting; the system detects who attended.

For hybrid meetings the photo is a **video-call gallery screenshot**, and the owner proposes a
neat heuristic:

- the tile containing **many faces** is the physical room → those people are **onsite**
- tiles containing **one face** are remote participants → those people are **online**

That is a genuinely good idea and it is not in the PRD. It deserves to be, because it solves
something FR-04.3 otherwise leaves to manual entry: distinguishing participation mode without
asking anyone.

---

## 3. Where this already fits the PRD

FR-14.3 already specifies the shape:

> Group-photo processing returns consented candidate member IDs and scores only. It remains
> assisted and requires leader confirmation before attendance.

The owner's request is that capability, plus the hybrid tile heuristic. Two things follow.

**It is biometric processing.** Face detection *and matching against enrolled members* is
biometric data under Indonesia Law No. 27/2022 and Taiwan's PDPA. It requires purpose-specific
consent, distinct from profile-photo consent (FR-11.18, invariant 33), and it cannot start before
the WP 0D privacy review. This is not a formality that can be compressed — it is the gate the PRD
was built around.

**It stays assisted, permanently.** The leader confirms before any attendance is written. Nothing
in this path becomes automatic; automatic mode is the separately gated entrance-device workflow in
FR-14.15, which this is not.

---

## 4. Proposed PRD addition — FR-05.9a and FR-14.3a

```
FR-05.9a  A leader may upload one or more photos of an iCare occurrence. When group-photo
          assistance is enabled and the occurrence's members have granted group-photo-matching
          consent, the system returns ranked candidate matches for leader confirmation. No
          attendance is written without that confirmation. Unmatched and non-consenting faces
          produce only an aggregate count and are never stored as crops or embeddings.

FR-14.3a  For a hybrid occurrence photographed as a video-call gallery view, the processor may
          propose a participation mode per detected face:
            - faces within the single largest multi-face region are proposed as onsite
            - faces within single-face regions are proposed as online
          The proposal is advisory. The leader confirms or overrides mode per member before
          AttendanceRecorder writes the record. If region segmentation is ambiguous or the
          heuristic's confidence is below the configured threshold, mode is left unset and the
          leader supplies it.
```

---

## 5. Honest engineering assessment

I want to be straightforward about accuracy, because this design will disappoint if it is expected
to be hands-free.

**Recognition from a gallery screenshot is much harder than from a photo.** A Zoom tile is
typically 160–320 px wide after compression; a usable face crop from it is often under 100 px.
Most face-recognition models want ≥112 px of actual face and degrade sharply below that. Expect
meaningfully worse accuracy than the entrance-camera scenario FR-14 was written for.

**Faces in the room tile are the hardest case of all.** A wide shot of a small-group room puts
each face at perhaps 20–40 px, at an angle, often partly occluded. Realistically the room tile
will detect *that there are N people* far more reliably than *who they are*.

**Which is why the heuristic is better than the recognition.** Counting faces per region to infer
onsite versus online is a geometry problem, not an identity problem, and it should work well. The
useful framing is: **the photo tells you the shape of the meeting; the leader tells you the names.**

A realistic first version:

1. Detect faces and regions; propose the onsite/online split from tile geometry.
2. Pre-tick the roster from the *previous* occurrence's attendance — small groups are highly
   regular, so last week is a strong prior and needs no biometrics at all.
3. Offer recognition candidates only for consented members, ranked, never auto-applied.
4. Leader confirms in one screen.

**Step 2 alone may deliver most of the time saving**, at zero privacy cost and zero legal gate. I
would build that first and treat recognition as an enhancement measured against it — if the
regular-attendance prior gets a leader to a correct roster in two taps, expensive biometrics that
get them to the same place are not obviously worth the consent burden.

---

## 6. Sequencing

| Step | Package | Gate |
|---|---|---|
| iCare groups, roster, effective-dated membership, leader scope | Phase 1C.1 → **Release 1** | — |
| Manual tick-list attendance, mobile, with previous-occurrence pre-tick | Phase 1C.1 → **Release 1** | — |
| Private photo upload, documentation only, no processing | Phase 1C.2 → **Release 1** | — |
| Face detection + region heuristic for onsite/online proposal | Phase 2B | WP 0D privacy review |
| Recognition candidates against consented enrolled members | Phase 2B | WP 0D + FR-14 consent + enrollment |

Release 1 gets iCare working end to end without any biometric processing. The photo feature lands
in Phase 2B behind its existing gates, and the tile heuristic can ship with it.

---

## 7. Open questions for the owner

1. **Is the hybrid photo always a gallery screenshot**, or sometimes a room photo plus a separate
   screen capture? The heuristic depends on tile geometry and fails on a plain room photo.
2. **Roughly how many members per iCare?** 22 groups across 217 members averages ~10, which makes
   the previous-occurrence prior very strong and recognition largely unnecessary.
3. **Are iCare guests and visitors common?** They will never match an enrolled template and must
   fall to manual add regardless.
