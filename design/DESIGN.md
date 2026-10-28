# DESIGN — IFGF Taipei–Zhongli member and attendance app

**Status:** draft 1, 2026-10-12 — written before any Stitch screen exists. The owner confirms §11.
**Scope:** how the app looks and behaves. What it does is `PRD.md`; this file never adds a feature.

> Any person or AI restyling a view reads this file first, uses only the tokens in §3, and builds
> pages only from the components in §5. If something needed is not here, add it here first.

---

## 1. Who uses it, and where

| Person | Device | Moment | What must never go wrong |
|---|---|---|---|
| Member, often a student | own phone | registers once; shows a QR at the door | finds the QR in one tap |
| Usher | phone, at a door, people queuing | scans one QR after another | reads the result at arm's length, instantly |
| iCare leader | phone, after a meeting | ticks who came; checks birthdays | saves without wondering whether it saved |
| IT admin | laptop | reads tables and reports | sees a whole table without hunting |

Text appears in Indonesian (default), English and Traditional Chinese. Indonesian labels are long.

## 2. Principles

1. **One job per screen.** One primary action, one place to look. Anything else is secondary or gone.
2. **Quiet by default.** White space, one accent colour, no decoration. Colour is kept for meaning.
3. **Say what happened.** Every action answers with words: saved, recorded, already recorded, failed.
4. **Phone first, thumb first.** Designed at 375 px; primary actions sit in the lower half of the screen.
5. **Plain words.** The label a church volunteer would say out loud, never a system term.

## 3. Tokens

### 3.1 Colour

Roles, not hues, are used in views (`bg-surface`, `text-ink-muted`). Ratios are computed (WCAG 2.1).

| Role | Light | Dark | Used for | Contrast (light) |
|---|---|---|---|---|
| `canvas` | `#F8FAFC` | `#0F172A` | page background | — |
| `surface` | `#FFFFFF` | `#1E293B` | cards, inputs, bars | — |
| `ink` | `#0F172A` | `#F1F5F9` | headings, input text | 17.9 on surface |
| `ink-body` | `#334155` | `#E2E8F0` | body text | 10.4 |
| `ink-muted` | `#475569` | `#CBD5E1` | help text, secondary lines | 7.6 |
| `ink-subtle` | `#64748B` | `#94A3B8` | placeholders, timestamps (never essential text) | 4.8 |
| `line` | `#E2E8F0` | `#334155` | dividers, card borders (decorative) | — |
| `line-strong` | `#64748B` | `#94A3B8` | borders of inputs and controls (must reach 3:1) | 4.8 |
| `primary` | `#1D4ED8` | `#60A5FA` | primary button, links, selected state, focus ring | 6.7 (white text on it) |
| `primary-hover` | `#1E40AF` | `#93C5FD` | hover and pressed | 8.7 |
| `primary-soft` | `#EFF6FF` | `#1E3A8A` | selected row, today, info alert background | 8.0 (`#1E40AF` text) |
| `on-primary` | `#FFFFFF` | `#0F172A` | text on `primary` | 6.7 / 7.0 dark |
| `success` / `success-soft` | `#166534` / `#F0FDF4` | `#BBF7D0` / `#14532D` | recorded, saved | 6.8 |
| `warning` / `warning-soft` | `#92400E` / `#FFFBEB` | `#FDE68A` / `#78350F` | already recorded, needs attention | 6.8 |
| `danger` / `danger-soft` | `#991B1B` / `#FEF2F2` | `#FECACA` / `#7F1D1D` | errors, not found, destructive | 7.6 |
| `danger-solid` | `#B91C1C` | `#B91C1C` | destructive button background (white text) | 6.5 |
| `birthday` / `birthday-soft` | `#9D174D` / `#FDF2F8` | `#FBCFE8` / `#831843` | birthday markers only | 7.2 |

Category badges (text / background, light): Kids `#92400E` / `#FEF3C7` · Teens and Youth `#115E59` /
`#CCFBF1` · College `#5B21B6` / `#EDE9FE` · Adult `#1E40AF` / `#DBEAFE`. All above 6:1.
In views they are the roles `cat-kids`, `cat-teens`, `cat-college`, `cat-adult`, each with a `-soft`.

The Google mark keeps Google's own four colours — `google-blue` `#4285F4`, `google-green` `#34A853`,
`google-yellow` `#FBBC05`, `google-red` `#EA4335` — used inside `<x-icon name="google" />` and nowhere else.

Rules: one accent (`primary`). Status colour is always paired with an icon and a word. Never put
`ink-subtle` on `canvas` for text that must be read. The accent is a placeholder until §11.1.

### 3.2 Type

Font: **Instrument Sans** (already bundled; weights 400, 500, 600), then `"Noto Sans TC",
"PingFang TC", "Microsoft JhengHei", system-ui, sans-serif` so Chinese names render well.
Codes and counts use `ui-monospace` with tabular figures.

| Style | Size / line | Weight | Used for |
|---|---|---|---|
| `display` | 28 / 36 | 600 | one per screen at most: a name on the QR page, a big count |
| `title` | 22 / 28 | 600 | page title (`h1`) |
| `heading` | 18 / 26 | 600 | section title, card title |
| `body` | 16 / 24 | 400 | everything by default — never smaller in a form |
| `label` | 14 / 20 | 500 | field labels, table headers, buttons on desktop |
| `caption` | 13 / 18 | 400 | help text, timestamps, badges |

No text below 13 px. No all-caps. Sentence case everywhere. Numbers in tables are right-aligned.

### 3.3 Space, shape, depth, motion

- **Spacing scale:** 4, 8, 12, 16, 24, 32, 48. Page gutter 16 on phones, 24 from 768 px.
  Between fields 20; between sections 32; inside a card 16 (phone) or 24.
- **Radius:** controls 10 · cards 14 · bottom sheets 20 (top corners) · pills and badges full.
- **Depth:** a 1 px `line` border, not a shadow. One shadow only, for things that float over the
  page (sheet, menu, toast): `0 8px 24px rgb(15 23 42 / 0.12)`.
- **Motion:** 150 ms ease-out for colour and opacity; 220 ms for a sheet or toast sliding in.
  Nothing loops, except the spinner of a button that is submitting. All motion is removed under
  `prefers-reduced-motion`; the spinner then stands still.
- **Breakpoints:** phone below 768 · tablet 768 · desktop 1024 and up.
- **Sizes:** tap target at least 44 × 44; control height 48 on phones, 44 on desktop;
  form column at most 560; reading column 720; tables up to 1200.
- **Focus:** a 2 px `primary` ring with a 2 px offset, on every interactive element, always visible.
- **Icons:** Heroicons outline, 1.5 px stroke, 20 px in controls and 24 px in navigation, inlined
  as SVG. An icon never stands alone without a label or an `aria-label`.

## 4. Layout

- **Phone:** a top bar (56 px: page title, at most one action) and, when signed in, a bottom
  navigation bar (64 px, at most four items, icon over label, safe-area padding). Content
  scrolls between them. A screen's primary button may stick above the bottom bar.
- **Desktop:** a left sidebar (240 px) with the same destinations, the page title in the content
  area, content centred at its maximum width. No top bar.
- **Signed out** (registration, sign-in, confirm links): a single centred column, the church name
  at the top, a language switch (ID / EN / 中文), no navigation.
- **Navigation by role** — never show a destination the person cannot open:
  member: Home · Profile | usher adds: Scan · Attendance | iCare leader adds: My group ·
  Birthdays | IT admin: all, plus Admin. Above four items the phone shows three and "More".
- **Page anatomy:** title → one line of context if needed → content → primary action.
  A list page is: filters → count → list → pagination or "load more".

## 5. Components

Each is one Blade component. Variants are props, never copied markup.

| Component | Rules |
|---|---|
| `x-button` | Variants `primary`, `secondary` (surface, `line-strong` border), `quiet` (text only), `danger`. One `primary` per screen. Full width on phones, natural width on desktop. Label is a verb: "Save attendance". While submitting: disabled, spinner, the label stays. |
| `x-field` | Wraps any control: label above (never placeholder-as-label), optional mark "(optional)" instead of asterisks on required fields when most fields are required, help text under the label, error under the control with an icon. |
| `x-input`, `x-select`, `x-textarea` | 48 px high, 16 px text, `line-strong` border, `surface` background. Error state: `danger` border 2 px and `aria-invalid`. Correct `type`, `inputmode` and `autocomplete` always. |
| `x-radio-cards` | For two to five choices (branch, yes/no, occupation): each choice a full-width card of at least 52 px with the radio at the left; selected = `primary` border 2 px and `primary-soft` background. A `fieldset` with a `legend`. Six or more choices use `x-select`. |
| `x-checkbox-row` | A 52 px row, the whole row tappable, 24 px box at the right; used for attendance ticking. |
| `x-alert` | Variants `info`, `success`, `warning`, `danger`: soft background, matching text, icon, a title and one sentence. At the top of a form it lists the fields in error as links. |
| `x-toast` | Confirms a completed action ("Saved"). Bottom on phones above the nav bar, top-right on desktop. Disappears after 4 s; errors stay until dismissed. Never the only record of a failure. |
| `x-card` | `surface`, 1 px `line`, radius 14. Optional title row with one action. No card inside a card. |
| `x-badge` | Pill, `caption` size, soft background. Status badges carry an icon; category badges use §3.1. |
| `x-list` / `x-table` | A table on desktop; on phones each row becomes a card with the most important field as its title and at most three more lines. Never horizontal scrolling on a phone. Sticky header on desktop. Sortable columns show the direction. |
| `x-empty` | Icon, one sentence saying why it is empty, and the one action that fills it. Every list has one. |
| `x-steps` | "Step 1 of 2" with a bar; names the step. Used for registration only. |
| `x-qr-card` | White `surface` in both themes (a QR must stay black on white), the QR at up to 280 px and never under 220 px, the name, then the backup code in monospace, grouped 4 + 4, with a "Copy" action. |
| `x-month-nav` | Previous / month name / next, plus "Today". Used by birthdays and attendance. |
| `x-sheet` | Bottom sheet on phones, centred dialog on desktop, for one short task or a confirmation. Focus is trapped and returns to the trigger. |
| `x-skeleton` | Grey blocks in the shape of the content while a list loads. No spinners for whole pages. |
| `x-icon` | The one place an icon's path is written: `<x-icon name="check-circle" />`, 20 px by default, `size="24"` in navigation. Always `aria-hidden`; the words beside it, or the parent's `aria-label`, carry the meaning. |

## 6. Patterns

- **Forms.** One column. Group related fields under a heading. Long forms are split into steps,
  never into two columns. Validate on submit; keep everything the person typed; move focus to the
  error summary. Error text says how to fix it: "Enter a date in the past", not "Invalid".
- **Feedback.** Success: toast, or an inline `success` alert when the page changes. Failure: inline
  `danger` alert that stays. A destructive action asks first, in a sheet, naming what is lost.
- **Scanning result (usher).** A full-width band that takes the lower third of the screen:
  green "Recorded" + name + iCare, amber "Already recorded at 13:42", red "QR not recognised".
  Large text (22 px), icon and word, and a different vibration pattern for each.
- **Loading.** A button shows its own spinner; a list shows a skeleton; nothing blocks the page.
- **Dates and time.** `Sun 12 Oct 2026`, 24-hour time, Asia/Taipei. Birthdays show day and month only.
- **Personal data.** A list shows the least needed: name and iCare. Contact details and birth year
  appear only on a page whose viewer is allowed to see them (PRD §5.10).
- **Language.** Buttons and labels wrap; nothing is truncated. A layout must survive a label
  twice as long as its English version.

## 7. Screens

| Screen | Layout |
|---|---|
| Registration | `x-steps`; step 1 "About you", step 2 "Study and work". Branch and "come again?" are `x-radio-cards`. The "other occupation" input appears only when "Other" is chosen. Privacy note in an `info` alert above the first field. One primary button per step. |
| Registered / My QR | `x-qr-card` is the first thing on the page. Under it: branch and iCare. A `success` alert once, after registering. |
| Member home | QR card → "My attendance" (last eight Sundays, missing weeks muted) → "My household". |
| Sign-in | One email field, one primary button, then "Continue with Google" as `secondary`. The state after sending replaces the form: "Check your email — the link works for 15 minutes." |
| Usher scan | Branch picker as two very large `x-radio-cards` → camera filling the screen, result band (§6), a running count, "Type a code" and "Find by name" as `secondary`. |
| iCare attendance | Owner's choice, 2026-10-28 ("design B"). In order: **1 Foto** — a dashed drop area (take or upload) and a checkbox "Tanpa foto"; **2 Tanggal** — a date field that opens the calendar (future dates refused, "sudah disimpan" under a saved date); **3 Anggota** — a count tile "9 dari 14 hadir — 7 onsite, 2 online", then one `x-checkbox-row` per member, ticked by default, with an Onsite/Online pill on ticked rows and a reason input under online and absent rows; **4 Tamu** — guest rows with the same pill and a remove button, then a name input and "Tambah"; **5** "Simpan absensi" at the very bottom, **not** sticky. Errors gather above the button. |
| Attendance table | Filters → five summary tiles (Adult, College, Teens and Youth, Kids, Sub-total) → `x-table`. |
| Birthdays | See §8. |

## 8. Birthdays — list and calendar

For iCare leaders (their own group) and IT admins (the whole church). Nobody else sees it.

- **Header:** `x-month-nav`, and a two-way switch **List | Calendar** (the choice is remembered).
- **This week strip:** above both views, the birthdays of the next seven days as small cards —
  day and month, name, iCare — or one line "No birthdays this week".
- **List view:** grouped by day; the day number large at the left (`display`), then name and iCare.
  Today's group has a `birthday-soft` background and the word "Today".
- **Calendar view:** a month grid, Monday first, seven equal columns.
  - Desktop: each day cell shows up to three names as `birthday` pills, then "+2 more".
  - Phone: each day cell shows the day number and a `birthday` dot with a count; tapping a day
    lists that day's birthdays directly under the grid. No horizontal scrolling.
  - Today has a `primary` ring; days outside the month are `ink-subtle`.
  - Each day is a button whose label reads "14 October, 2 birthdays".
- **Never shown:** the birth year, age, phone or email.
- **Empty month:** `x-empty` — "No birthdays in November" and a link to the next month that has one.
- **Where the data comes from:** the app's own database, the same records it writes to the church's
  Google birthday calendar. The page does not embed Google Calendar: an embed shows whatever the
  calendar holds to anyone who can open it, cannot apply the "own group only" rule, and cannot
  hide the year. A small "Synced with Google Calendar · 2 minutes ago" line links the two.

## 9. Words

- Voice: warm, short, direct. "Welcome home." "You're registered." Never "Success!" or "Error 422".
- Buttons are verbs. Links say where they go. No "Click here", no "Submit".
- An error names the field and the fix. An empty state names the next step.
- The same thing has the same name everywhere: "iCare", "branch", "QR", "backup code".

## 10. Accessibility — every page, before it is called done

1. Contrast: text 4.5:1, large text and control borders 3:1 — in both themes.
2. Every input has a visible label; every group of radios is a `fieldset` with a `legend`.
3. Keyboard: everything reachable, focus ring visible, order follows the page.
4. Tap targets 44 × 44; 8 px between neighbouring targets.
5. Errors are text plus icon, linked with `aria-describedby`; the summary receives focus.
6. `lang` on the page matches the language shown; Chinese names are not broken across lines oddly.
7. Works at 200 % zoom and at 320 px wide without horizontal scrolling.
8. Motion respects `prefers-reduced-motion`; nothing flashes.

## 11. Open — the owner decides

1. **Brand colour and logo.** `primary` is a neutral blue placeholder. If IFGF has a brand colour
   or logo, give the hex and the file; the soft and hover shades will be derived and re-checked.
2. **Dark theme now or later.** Tokens are defined; building both themes roughly doubles the
   checking for each page.
3. **Birthday calendar grid.** PRD FR-6 lists a birthday *table* with a month picker. The calendar
   view in §8 is a second way to show the same data and needs one line added to FR-6.
4. **Required marks.** §5 marks the few optional fields instead of starring the many required ones.
   *Decided 2026-10-13: "(optional)" on the optional fields, no asterisks.*

## 12. Tailwind 4 mapping

Tokens live in `resources/css/app.css`; views use the generated utilities (`bg-surface`,
`text-ink-muted`, `border-line-strong`, `rounded-control`). No hex value appears in a view.

```css
@theme {
    --color-canvas: #F8FAFC;      --color-surface: #FFFFFF;
    --color-ink: #0F172A;         --color-ink-body: #334155;
    --color-ink-muted: #475569;   --color-ink-subtle: #64748B;
    --color-line: #E2E8F0;        --color-line-strong: #64748B;
    --color-primary: #1D4ED8;     --color-primary-hover: #1E40AF;
    --color-primary-soft: #EFF6FF; --color-on-primary: #FFFFFF;
    --color-success: #166534;     --color-success-soft: #F0FDF4;
    --color-warning: #92400E;     --color-warning-soft: #FFFBEB;
    --color-danger: #991B1B;      --color-danger-soft: #FEF2F2;
    --color-danger-solid: #B91C1C;
    --color-birthday: #9D174D;    --color-birthday-soft: #FDF2F8;
    --color-cat-kids: #92400E;    --color-cat-kids-soft: #FEF3C7;
    --color-cat-teens: #115E59;   --color-cat-teens-soft: #CCFBF1;
    --color-cat-college: #5B21B6; --color-cat-college-soft: #EDE9FE;
    --color-cat-adult: #1E40AF;   --color-cat-adult-soft: #DBEAFE;
    --color-google-blue: #4285F4; --color-google-green: #34A853;
    --color-google-yellow: #FBBC05; --color-google-red: #EA4335;
    --radius-control: 10px;       --radius-card: 14px;      --radius-sheet: 20px;
    --shadow-float: 0 8px 24px rgb(15 23 42 / 0.12);
}
```

The type styles of §3.2 are utilities too — `text-display`, `text-title`, `text-heading`,
`text-body`, `text-label`, `text-caption` — each carrying its size, line height and weight.
`font-sans` is the stack of §3.2. `focus-ring` is the focus style of §3.3.

The dark values of §3.1 override the same variables under `prefers-color-scheme: dark`.

## 13. Do not

- Do not add a colour, size or radius that is not in §3.
- Do not use a placeholder as a label, an icon without a name, or colour as the only signal.
- Do not put two primary buttons on a screen, a card inside a card, or a table on a phone.
- Do not truncate a name, a label or an error message.
- Do not show a birth year, a phone number or an email in a list.
- Do not copy Stitch's HTML into a view: it is a picture of the target, rebuilt from §5.

## 14. Where the ideas come from

Patterns borrowed, by name, so they can be looked up — none is copied:
GOV.UK Design System (one thing per page, error summary, plain-language errors) · Material Design 3
(bottom navigation bar, touch targets, tonal surfaces) · Apple Human Interface Guidelines (44 pt
targets, safe areas) · church apps such as Planning Center's Church Center and Breeze ChMS (a
member's QR as the home screen, check-in result bands) · calendar apps' month grid with an agenda
list under it on phones.
