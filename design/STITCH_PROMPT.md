# UI design with Google Stitch

**Tool:** Google Stitch — <https://stitch.withgoogle.com>. It turns a text prompt into screen
designs and exports them as HTML with Tailwind classes, which is what this app's Blade views use.

**How to use it**

1. Start a new project in *Web* mode. Paste **Part A** as the first prompt.
2. Paste the **Part B** prompts one at a time — one prompt, one screen (or one short flow). Ask for
   the phone layout first, then "now the desktop layout of the same screen".
3. When a screen looks right, export it as **HTML + Tailwind** and save it as
   `design/stitch/<screen-name>.html`, using the names in Part B. Export the design rules Stitch
   keeps (`DESIGN.md`) to `design/DESIGN.md`.
4. Commit the `design/` folder on `dev`. The development sessions convert these files to Blade
   and keep the classes; they do not redesign.

Use placeholder people only ("Maria Tan", "iCare U"). Never paste real member data into Stitch.

---

## Part A — project brief (paste first)

```
Design a responsive web app for a church community: IFGF Taipei–Zhongli, an Indonesian
congregation in Taiwan with two branches (Taipei and Zhongli) and about 250 members.

Who uses it and where:
- Members, mostly students and young workers, on their own phones. They register once, then open
  the app to show a personal QR code at the door.
- Ushers at the entrance on a phone, scanning one QR after another while people queue. Speed and
  an unmistakable result matter more than anything else.
- Small-group ("iCare") leaders on a phone after a weekly meeting, ticking who came.
- One or two administrators on a laptop, reading tables and reports.

Design rules:
- Phone first at 375px wide; every screen must also have a desktop layout at 1280px.
  On phones: a top bar and a bottom navigation bar with at most four items.
  On desktop: a left sidebar, content at a comfortable reading width, tables instead of cards.
- Any table must turn into a list of cards on a phone, with the most important field as the
  card title. No horizontal scrolling on a phone.
- Calm and warm, not corporate: generous white space, one accent colour, rounded corners,
  soft shadows. Provide a light and a dark theme.
- Tap targets at least 44px high. Body text at least 16px. Contrast that passes WCAG AA.
- Text will appear in Indonesian (the default), English and Traditional Chinese. Indonesian
  labels are long: let buttons and labels wrap instead of truncating.
- Use only HTML and Tailwind utility classes. No JavaScript framework. Reuse the same
  components everywhere: page header, card, form field with label, help text and error text,
  primary and secondary button, table, status badge, empty state, toast message.
- Status colours carry meaning and are always paired with an icon and a word:
  green "recorded", amber "already recorded", red "not found".
- No stock photos. No marketing sections. This is a tool.
```

---

## Part B — one prompt per screen

**`sign-in`**
```
Sign-in screen. Title "Welcome Home". One email field and a primary button "Send me a sign-in
link". Below a divider, a "Continue with Google" button. A text link "New here? Register".
A language switch (ID / EN / 中文) in the top corner. Also design the state after sending:
"Check your email — the link works for 15 minutes."
```

**`register`**
```
Registration, a short flow with a progress indicator (3 steps).
Step 1 "About you": email, full name (help text: as on passport or ARC), Chinese name
(optional), date of birth, where you live in Taiwan, Line ID (optional), WhatsApp number,
home branch (two large radio cards: Taipei, Zhongli), and "Do you plan to come again?"
(two radio cards: "Yes, I will come back" / "No, I am only visiting this time").
Step 2 "Study and work" (shown only when the answer was yes): current occupation (radio:
Worker, Student, Homemaker, Other with a text field), education level (optional radio: SD, SMP,
SMA/SMK, Diploma, S1, S2, S3), school / campus / company as a search-as-you-type field showing
suggestions with an English and a Chinese name, and iCare group as a dropdown whose first option
is "Not joined yet".
Step 3 "Family" (optional): a repeatable card "Add a family member" with full name, date of
birth, relationship (spouse, child, other), and optional email. A "Skip" link.
Show a field with a validation error. End with a one-line privacy note above the submit button.
```

**`registered`**
```
Success screen after registering: a large QR code in a card, the member's name and branch under
it, the text "Show this at the door. We also emailed it to you.", and a primary button
"Go to my page". If family members were added, show their QR codes as a swipeable row of cards.
```

**`member-home`**
```
Member home. Top: a card with the member's QR code, name, iCare group and a short 8-character
backup code. Then "My attendance": a list of the last eight Sundays with date, branch and time,
missing weeks shown muted. Then "My household": small cards for family members, each opening
that person's QR; a button "Add a family member". Bottom navigation: Home, My QR, Profile.
```

**`profile`**
```
Edit profile form with the same fields as registration except email (shown read-only). Sections:
About you, Study and work, iCare. A sticky save bar at the bottom on phones.
```

**`usher-scan`**
```
Usher scanning screen, used at a door while people queue. First a branch picker: two very large
buttons "Taipei" and "Zhongli". Then the scanner: the chosen branch and today's date in the top
bar with a "change" link, a square camera viewfinder taking most of the screen, and a result
card that slides up from the bottom in three states — green "Recorded: Maria Tan · iCare U",
amber "Already recorded at 13:42", red "QR not recognised". A running counter "47 today".
Two secondary actions under the viewfinder: "Type a code" (opens an 8-character input with
large letter boxes) and "Find by name".
```

**`usher-roster`**
```
Roster for ushers: a search field, filter chips for iCare group, and a list of members with a
large tick button each; ticked rows show the time. A segmented control Onsite / Online. A small
form at the bottom "Headcount" with number steppers for Adult, College, Teens and Youth, Kids
and Online, and a total.
```

**`icare-attendance`**
```
iCare leader weekly attendance. Header: group name and a date picker defaulting to the latest
meeting day, with previous / next week arrows and a badge on weeks that were never filled in.
A checklist of group members with large checkboxes, "select all", and a count "9 of 14".
A row "Add a guest" that adds a typed name to the list with a "guest" badge.
A sticky "Save attendance" button.
```

**`icare-members`**
```
iCare leader's member list: name, active / inactive toggle, birthday (day and month only), and
a "Suggest a change" action. The suggest-a-change form shows each editable field with the
current value beside the new one, and explains "We will email Maria to confirm before anything
changes." Below it, a list of requests with status badges: waiting, confirmed, rejected, expired.
```

**`birthdays`**
```
Birthday table: a month selector (twelve chips, current month selected), then a list sorted by
day: day number large on the left, name, iCare group. Today's birthdays highlighted. No birth
year anywhere.
```

**`attendance-table`**
```
Attendance for one Sunday. Filters: date, branch (Taipei / Zhongli). A summary block of five
tiles — Adult, College, Teens and Youth, Kids, Sub-total — each with an onsite and an online
number. Below, a table: time, name, iCare, category, onsite/online. A tab "Grid" shows members
down the side and the last eight Sundays across the top, each cell a time or empty; on a phone
the grid becomes one card per member with a row of eight dots. An "Export CSV" button.
```

**`quarterly-review`**
```
Quarterly review report. Selectors: year, branch. A table with rows Adult, College, Teens and
Youth, Kids, Sub-total and columns for the twelve months, followed by a second table with the
four quarters. A toggle "Scanned / Headcount / Both"; in "Both" each cell shows two numbers.
A note line "3 Sundays had no data and count as zero." Desktop first; on a phone show one
quarter at a time with a quarter switcher.
```

**`admin-teams`**
```
Admin: teams. A list in two groups — iCare groups and Ministry teams (Usher, IT, Praise and
Worship, Multimedia, Sound, Publication, Documentation) — each with member count and leader
names. A team page: name, type, branch, and a roster where each person is marked leader or
member, with "Add member" search and a remove action.
```

**`admin-import`**
```
Admin: import. Step 1 upload a file (workbook or LINE recap CSV). Step 2 a preview that says
"Nothing has been saved yet" with counts — new, unchanged, could not be matched — and a list of
unmatched names, each with a "choose member" search or "keep as guest". Step 3 a confirm button
and a result summary.
```

**`emails`**
```
Three simple, single-column emails that read well on a phone: (1) "Your sign-in link" with one
button; (2) "Welcome to IFGF Taipei–Zhongli" with the QR code image and how to use it;
(3) "Please confirm a change to your details" listing old value → new value with a green
Confirm button and a plain Reject link.
```
