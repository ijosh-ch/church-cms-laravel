# Session prompts — admin, usher and leader areas

Written 2026-10-14. One prompt per session, in this order: **A admin → B usher → C leader**.
Use Opus 5.5 at effort `high`. Paste one block as the first message of a new session.

The three are areas of one app (`/admin`, `/usher`, `/icare`, PRD D4), not three repositories.
Their routes stay in separate groups because the owner wants each on its own hostname later
(2026-10-14): `admin.ifgf.site`, `usher.ifgf.site`, `leader.ifgf.site`, all served by this one
app. These sessions build with the path prefixes; the hostnames arrive with the Cloudflare
staging work (WP2S), which also changes PRD D4.

Before any of them:

- Commit or discard the uncommitted work in the tree first. Never run two sessions in the same
  working tree at once.
- Sign in with Google at `http://localhost:8080`, then run
  `php artisan ifgf:admin:grant <your email>`. Without a verified admin, no session can walk the
  admin screens.

---

## A — Admin: members and roles

```
Continue the IFGF member and attendance app in this repository. This is my church project,
not lab work. Scope: PRD.md 3.0 and nothing else.

TASK FOR THIS SESSION, AND ONLY THIS ONE — the admin area: the member list and assigning roles.

Already built — read the code first and tell me what you found before you plan:
/admin/members (list, search and filters kept in the session), the member page (edit, status,
add to a team, change role, leave), the audit log, the last-admin guard, and the note for an
unverified email. Do not rebuild these.

Build:
1. A roles page for IT admins: every role on one page — IT admins, ushers, the leaders of each
   iCare, the leaders of each ministry team — with who holds it. From it an admin finds a member
   by name and gives or removes a role. It marks a member whose email is not verified.
2. Teams (PRD FR-14): create, rename, activate and deactivate iCare groups and ministry teams;
   branch and meeting weekday for an iCare. A team's `ability` is shown but can never be set
   from a web form. Deactivating the only admin team is refused.
3. Roles in the future: a role is a team. A role that opens a new area is a new TeamAbility
   plus a Gate, which is a code change. Do not build a free-form permission editor; say in the
   plan how a fourth role would be added.
4. Every change of team or role goes to the audit log, by id only.

Not in this session: deleting or anonymising a member, households, imports.

Rules
1. `git branch --show-current` must print `dev`, and `git status --short` must show no
   uncommitted work from another session. If either fails, stop and tell me. Never create,
   switch, rebase or delete a branch. Never push.
2. Start exactly as CLAUDE.md says, tell me the plan in ten lines or fewer, wait for my "go".
3. Where a document or this prompt disagrees with the code, the code wins.
4. Run every `php artisan make:*` command before writing a file by hand.
5. Tests are written with the code: three access tests for every route (signed out, signed in
   without the right, someone else's record). Tests use `ifgf_app_test` and the fake calendar.
6. Access needs a verified email (`Member::hasAbility`, `leads`). Never weaken that.
7. No sequential id in a URL (STANDARDS §3.5). Members travel by `public_ref`. A team has only
   a row number today: say in the plan how a team page is addressed, and wait for my answer
   before adding a column.
8. No new Composer or npm package, no new public route, no personal data in a URL, a log line
   or a command's output.
9. If the restyle has landed, build screens from `resources/views/components`; never redesign.
10. Walk every new screen on the `ifgf-dev` server (port 8080) at 375 px and at desktop width.
    Run the gate in STANDARDS.md §5 and show me the output.
11. Commit message: a title, `Summary:`, `Details:` and the Co-Authored-By line. No
    `work duration:` block. Show it and wait for my yes. Do not stage or commit before that.
12. At the gate or at 120k tokens, whichever is first: add the MEMORY.md entry at the top,
    rewrite TODO.md and CONTEXT.md, then stop.
```

---

## B — Usher: scanning attendance

```
Continue the IFGF member and attendance app in this repository. This is my church project,
not lab work. Scope: PRD.md 3.0 and nothing else.

TASK FOR THIS SESSION, AND ONLY THIS ONE — finish the usher area (PRD FR-7, FR-12).

Already built — read the code first and tell me what you found before you plan:
/usher (pick a branch), /usher/scan (camera through jsqr, typed backup code), /usher/roster
(find by name and tick onsite or online), /usher/attendance (one Sunday of one branch with the
totals block), and the throttles. Only a member of a team with the `scan` ability, or an IT
admin, gets in. The camera has never been tried with a real camera.

Build:
1. The camera on a real phone. You cannot open a camera; I can. I connect an Android phone by
   USB and forward port 8080 in chrome://inspect, so the phone opens http://localhost:8080.
   Give me a short list of what to try, wait for what I report, and fix it.
2. Another event of today (PRD FR-7, last line): the usher may pick any other active event for
   today in place of the Sunday Service; scans then belong to that event.
3. Headcount (PRD FR-12): one count per occurrence, by category plus online, entered by an
   usher or an IT admin; it can be corrected; who entered it is kept.
4. Where an usher lands after signing in: propose it in the plan. Today everyone lands on /me.

Not in this session: the members × weeks grid and CSV export, the quarterly review, imports.

Rules
1. `git branch --show-current` must print `dev`, and `git status --short` must show no
   uncommitted work from another session. If either fails, stop and tell me. Never create,
   switch, rebase or delete a branch. Never push.
2. Start exactly as CLAUDE.md says, tell me the plan in ten lines or fewer, wait for my "go".
3. Where a document or this prompt disagrees with the code, the code wins.
4. Run every `php artisan make:*` command before writing a file by hand.
5. Tests are written with the code: three access tests for every route. Tests use
   `ifgf_app_test` and the fake calendar. The camera path gets no automated test; say so.
6. Access needs a verified email (`Member::hasAbility`). Never weaken that.
7. The scan stays throttled per usher and the typed code hardest. A scan answers with a name
   and an iCare only — never contact details, never a birthday.
8. No sequential id in a URL. No new Composer or npm package, no new public route, no personal
   data in a URL, a log line or a command's output.
9. If the restyle has landed, build screens from `resources/views/components`; never redesign.
10. Walk every new screen on the `ifgf-dev` server (port 8080) at 375 px and at desktop width.
    Run the gate in STANDARDS.md §5 and show me the output.
11. Commit message: a title, `Summary:`, `Details:` and the Co-Authored-By line. No
    `work duration:` block. Show it and wait for my yes. Do not stage or commit before that.
12. At the gate or at 120k tokens, whichever is first: add the MEMORY.md entry at the top,
    rewrite TODO.md and CONTEXT.md, then stop.
```

---

## C — Leader: the iCare workspace

```
Continue the IFGF member and attendance app in this repository. This is my church project,
not lab work. Scope: PRD.md 3.0, plus the one amendment below.

TASK FOR THIS SESSION, AND ONLY THIS ONE — the iCare leader's area at /icare (PRD FR-9, and
the leader's part of FR-11). Nothing of it exists yet; the `lead-team` Gate does.

Owner's amendment, 2026-10-14 — write it into PRD FR-9 and FR-14 before any code, show me the
wording, and wait for my yes:
- A leader may rename the iCare they lead.
- A leader may register a new iCare and becomes its leader. Ask me in the plan whether this
  needs an IT admin's approval before the new group shows in the registration form.

Build:
1. /icare: the groups the signed-in member leads. An IT admin sees every group.
2. Tick attendance (FR-9): the group's active members, tick who came, save. Saving the same
   date again updates it. Add a guest by name. Pick any past date to fill a forgotten week.
   The occurrence is created the first time someone records that date.
3. Set a member of the group active or inactive: applied at once, and written to the audit log.
4. See the attendance of the group: members × weeks, the last 8 weeks by default, for the
   iCare meetings; and the same members' Sunday attendance (FR-11: a leader sees their own
   group's rows). Nothing about any other group.
5. Rename and register an iCare, as amended.

Not in this session: leader-proposed changes confirmed by email (FR-10 — it needs a mail
provider), the birthday table (FR-6), CSV export, LINE import.

Rules
1. `git branch --show-current` must print `dev`, and `git status --short` must show no
   uncommitted work from another session. If either fails, stop and tell me. Never create,
   switch, rebase or delete a branch. Never push.
2. Start exactly as CLAUDE.md says, tell me the plan in ten lines or fewer, wait for my "go".
3. Where a document or this prompt disagrees with the code, the code wins.
4. Run every `php artisan make:*` command before writing a file by hand.
5. Tests are written with the code: three access tests for every route, and for every route
   that takes a group, a fourth: the leader of group A is refused on group B. Tests use
   `ifgf_app_test` and the fake calendar.
6. Access needs a verified email (`Member::leads`). Never weaken that. Being in an iCare is not
   leading it.
7. A leader sees contact details only for members of a group they lead (PRD §5.10), and no
   birth year anywhere.
8. No sequential id in a URL (STANDARDS §3.5). A team has only a row number today: say in the
   plan how a group page is addressed (session A may already have settled it), and wait for my
   answer before adding a column.
9. No new Composer or npm package, no new public route, no personal data in a URL, a log line
   or a command's output.
10. If the restyle has landed, build screens from `resources/views/components`; never redesign.
11. Walk every new screen on the `ifgf-dev` server (port 8080) at 375 px and at desktop width.
    Run the gate in STANDARDS.md §5 and show me the output.
12. Commit message: a title, `Summary:`, `Details:` and the Co-Authored-By line. No
    `work duration:` block. Show it and wait for my yes. Do not stage or commit before that.
13. At the gate or at 120k tokens, whichever is first: add the MEMORY.md entry at the top,
    rewrite TODO.md and CONTEXT.md, then stop.
```
