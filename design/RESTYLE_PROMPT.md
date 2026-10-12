# Prompt — restyle the app from DESIGN.md

Paste this into a **new** Claude Code session opened in this repository. Change the two lines
marked ← before sending.

```
Improve the UI/UX of this Laravel app without changing its behaviour.
Stay on `dev`. Follow CLAUDE.md and STANDARDS.md. Do not start a work package.

Context
- Laravel 13, Blade views, Tailwind 4 through Vite. No Livewire, no Inertia.
- Users: church members, ushers and small-group leaders, mostly on phones, of mixed technical skill.
- The design system is design/DESIGN.md. Reference screens from Google Stitch, where they exist,
  are in design/stitch/ (HTML and PNG). A screen with no reference is built from DESIGN.md alone.

What I want
1. Read design/DESIGN.md and any file in design/stitch/. Open each existing page in the browser at
   375px and 1280px and audit it against DESIGN.md §10. List the problems per page and propose a
   plan. Wait for my approval before editing.
2. Foundations first, with no page changed yet: the tokens of DESIGN.md §12 in
   resources/css/app.css, and the Blade components of DESIGN.md §5 that the existing pages need.
   Generate each with `php artisan make:component <name> --view`. Show me one page of all
   components in every state before going on.
3. Then restyle ONE page at a time, starting with: registration page one (/register).      ←
   Theme: light only for now.                                                              ←

Rules
- Change only Blade views, Blade components, resources/css, resources/js and styling config.
  Do not change routes, controllers, requests, models, migrations, services or tests.
- Keep every form field name, id, value and route exactly as it is. The existing tests must pass
  unchanged; if a test needs editing, stop and tell me why.
- Use only the tokens and components of DESIGN.md. If something is missing, propose the addition
  to DESIGN.md first. No hex value in a view.
- The Stitch HTML is a picture of the target, not code to paste.
- Do not add a Composer or npm package without asking. Alpine.js is the one I expect you to ask for.
- Phone first. WCAG AA contrast, visible focus, a label on every input, tap targets of 44px.
- Every list has an empty state, every form has inline errors and an error summary, every action
  has success or failure feedback, every submit button has a loading state.
- After each page: run `php artisan test --compact`, run `npm run build`, screenshot the page at
  375px and 1280px, and summarise what changed in five lines. Then wait.
- Never stage or commit without my yes. Plain commit messages (title, summary, details).
```
