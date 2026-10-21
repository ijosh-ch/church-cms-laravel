# Prompt — move the app to `ifgf-tpe/members`

> Paste everything below the line into a new Claude Code session started in
> `D:\Users\Ian Joseph\Documents\GitHub\church-cms-laravel`. Written 2026-10-21. It is a one-off
> session: no feature work in it. Features built on `dev` before it runs come along
> automatically, because the move replays every commit from the rebuild up to the current tip.

---

Continue the IFGF member and attendance app. This is my church project, not lab work: plain commit
messages, no `work duration:` block, no daily log.

TASK FOR THIS SESSION, AND ONLY THIS ONE — move the app out of the ChurchCMS fork into its own
repository, `https://github.com/ifgf-tpe/members`.

## Why

`ijosh-ch/church-cms-laravel` is a public GitHub fork of `church-cms/church-cms-laravel`
(GegoSoft). Since the rebuild of 2026-10-12 (`fd25235`), the app on `dev` contains **no GegoSoft
code**. The review of 2026-10-21 found:
- 0 shared source files;
- only boilerplate lines in common;
- 12 identical files, all empty placeholders, Laravel's own `favicon.ico` and `robots.txt`, or
  `LICENSE.upstream-MIT`.

Yet `dev` still sits on GegoSoft's 323 commits, and the repository's default branch shows their
app. The two projects stay separate. This repository remains the archive of the fork-era work,
and keeps `contrib/laravel-supported-platform` (ChurchCMS on Laravel 13) in case it becomes a pull
request to GegoSoft.

## Owner — done before this session, on GitHub

`ifgf-tpe` is a GitHub **user account**, not an organisation, so Claude cannot create the
repository.
1. Signed in as `ifgf-tpe`, create an **empty** repository named `members`: no README, no
   `.gitignore`, no licence. Make it **Private** unless decision D2 says otherwise.
2. Give `ijosh-ch` push access. Either:
   - Settings → Collaborators → invite `ijosh-ch`, then accept the invite; or
   - Settings → Organizations → turn `ifgf-tpe` into an organisation with `ijosh-ch` as owner.
     This is better if more volunteers will join, but it cannot be undone.
3. Set the default branch to `dev` once it exists (step 3 below), unless `ijosh-ch` is an admin
   and can do it with `gh`.

## Decisions — the owner answers these in the first message; the defaults are in bold

- **D1. History:**
  - **only the app's own commits, from the rebuild on (recommended).** Same messages, authors and
    dates; new hashes. No GegoSoft history travels.
  - Or the full history, with GegoSoft's 323 commits underneath.
- **D2. Visibility:** **Private** or Public. The repository holds no member data either way.
- **D3. `LICENSE.upstream-MIT`:**
  - **Keep it as an acknowledgement.** It costs nothing, and the app's early design came out of
    the fork.
  - Or remove it and keep a one-line credit in `NOTICE.md`.

  With D1 = full history, it must stay. Removing it needs the owner's explicit yes, because
  `CLAUDE.md` forbids it today.
- **D4. Branches in the new repository:** **`dev` only, as the default branch.** `main` is created
  later, on the owner's first release.
- **D5. This repository afterwards:**
  - **Keep it as is, plus one commit on its `dev` saying where development moved.**
  - Never delete its branches. Archiving it on GitHub is a separate decision for later.

## Rules

1. Start as `CLAUDE.md` says, but read only:
   - `CLAUDE.md`, `CONTEXT.md`, `TODO.md`, and the newest `MEMORY.md` entry;
   - this file;
   - `STANDARDS.md` §1 and §3.

   Then state the plan in ten lines or fewer and wait for "go".
2. `git branch --show-current` must print `dev`, and `git status --short` must show nothing but
   `wp1-salvage/`. If another session has uncommitted work, stop and say so: the move would leave
   it behind.
3. **Do not create, switch, rebase or delete a branch in this repository.** The history is
   rebuilt in a throwaway clone in the scratchpad, never here.
4. **Never force-push. Never push GegoSoft history to `ifgf-tpe/members`.** Never push to
   `ijosh-ch/church-cms-laravel`, except the one D5 commit after the owner's yes.
5. Never commit or copy into the new repository:
   - `.env` or anything else from `~/.ifgf/`;
   - `wp1-salvage/`, `graphify-out/`, `vendor/`, `node_modules/`, `public/build/`;
   - member data.

   `.env` is copied as a **local file** into the new folder; never print it.
6. Both folders use the same databases: `ifgf_app` and `ifgf_app_test`.
   - Do not run `migrate`, `migrate:fresh` or `db:wipe`; `migrate:status` only.
   - Never touch `ifgf_cms`.
   - Do not run a queue worker in both folders at once.
7. Never stage or commit without the owner's yes. Stage files one by one, never `git add -A`.

## Steps

**0. Check the target.** `gh api repos/ifgf-tpe/members --jq '{private, size, permissions}'` must
show `size: 0` and `push: true`. If not, stop: the owner's GitHub steps are not done.

**1. Rebuild the history in a throwaway clone** (D1 = from the rebuild). Save this script in the
scratchpad as `replay.sh`. It was tested on 2026-10-21 with these results:
- 9 commits, all by the owner;
- the final tree is identical to `dev`;
- no GegoSoft commit is reachable;
- running it twice gives the same hashes.

```bash
#!/usr/bin/env bash
# Rebuilds the app's history without the fork underneath it: the rebuild commit's tree becomes a
# root commit, and every later commit is re-created on top with the same tree, message, author,
# committer and dates. Only the hashes change. Prints "map <old> <new>" per commit, then the tip.
# Usage (inside a clone): replay.sh <root-commit> <tip>
set -euo pipefail
ROOT="$1"
TIP="$2"

if [ "$(git rev-list --merges --count "$ROOT..$TIP")" != "0" ]; then
  echo "merge commits found between $ROOT and $TIP; this script handles a linear history only" >&2
  exit 1
fi

parent=""
for c in $(git rev-list --reverse "$ROOT^..$TIP"); do
  export GIT_AUTHOR_NAME="$(git log -1 --format=%an "$c")"
  export GIT_AUTHOR_EMAIL="$(git log -1 --format=%ae "$c")"
  export GIT_AUTHOR_DATE="$(git log -1 --format=%aI "$c")"
  export GIT_COMMITTER_NAME="$(git log -1 --format=%cn "$c")"
  export GIT_COMMITTER_EMAIL="$(git log -1 --format=%ce "$c")"
  export GIT_COMMITTER_DATE="$(git log -1 --format=%cI "$c")"
  if [ -z "$parent" ]; then
    parent="$(git log -1 --format=%B "$c" | git commit-tree "$c^{tree}")"
  else
    parent="$(git log -1 --format=%B "$c" | git commit-tree "$c^{tree}" -p "$parent")"
  fi
  echo "map $(git rev-parse --short "$c") $(git rev-parse --short "$parent")"
done

echo "tip $parent"
```

Run it like this:

```bash
git clone -q --no-local --single-branch --branch dev "<this folder>" "<scratchpad>/members-move"
cd "<scratchpad>/members-move"
bash "<scratchpad>/replay.sh" fd25235 dev > "<scratchpad>/hash-map.txt"
```

Before pushing, all three checks must pass:
1. `git rev-parse <tip>^{tree}` equals `git rev-parse dev^{tree}`.
2. `git rev-list --count <tip>` equals the number of commits from `fd25235` to `dev`.
3. `git log --format=%an <tip>` names no GegoSoft author (gurunathan, Karthick, Sundari,
   GegoSoft).

With D1 = full history, skip the script and push `dev` as it is.

**2. Push.** From the throwaway clone:
`git push https://github.com/ifgf-tpe/members.git <tip>:refs/heads/dev`.
- No `--force`, and nothing else.
- Then set the default branch (D4):
  `gh api -X PATCH repos/ifgf-tpe/members -f default_branch=dev`. If that is refused, the owner
  sets it.

**3. Clone the new repository beside this one,** at `D:\Users\Ian Joseph\Documents\GitHub\members`.
Then, in that folder:
1. Copy `.env` from this folder; do not print it.
2. Run `composer install`, `npm ci` and `npm run build`.
3. Run `php artisan migrate:status`. Every migration that has run here must show as run there
   too, because it is the same `ifgf_app`.
4. Run `php artisan test 2>&1 | tail -40` against `ifgf_app_test`. It must pass with the same
   count as here.

**4. Carry the local tooling that is not in git.**
- Copy Claude's project memory from
  `C:\Users\ianjo\.claude\projects\D--Users-Ian-Joseph-Documents-GitHub-church-cms-laravel\memory\`
  to the folder Claude Code uses for the new path,
  `C:\Users\ianjo\.claude\projects\D--Users-Ian-Joseph-Documents-GitHub-members\memory\`.
- Copy `.claude/settings.local.json` if it exists.
- `~/.ifgf/` is machine-wide and needs nothing.
- Google sign-in redirects to `localhost:8080`, which does not change.

**5. One commit in the new repository: "Make the members repository stand on its own".** Change
only what names the fork:
- **`CLAUDE.md` and `AGENTS.md`** (they mirror each other):
  - Drop the "Until WP1 lands" paragraph.
  - Old code is read with
    `git -C "../church-cms-laravel" show feat/postgresql-multisite:<path> | sed -n 'A,Bp'`.
  - The licence section and the `LICENSE.upstream-MIT` rule follow D3.
- **`CONTEXT.md`:** the repository, the folder, and the fact that this is no longer a fork.
- **`EXECUTION_PLAN.md`** lines 4 and 19: the same `git -C` form.
- **`NOTICE.md`:** rewrite section 2. The app is not a fork and contains no ChurchCMS code; its
  history begins at the 2026-10-12 rebuild; one line acknowledges ChurchCMS. Remove the claim
  about a GegoSoft footer and `config/ifgf-sites.php`; neither exists.
- **`README.md`:** replace Laravel's stock text with a short project introduction, and keep the
  "Data model" section as it is.
- **`.claude/settings.json`:** drop the two permissions that point at `c:/laragon/www/church-cms-new`.
- **`MEMORY.md`:** a new top entry with the old → new hash map from `hash-map.txt`, because older
  entries cite old hashes.
- **`TODO.md`:** item 1 is the next feature again.

Rebuild the graph afterwards if the `graphify` CLI can. Show the commit message and wait for the
owner's yes. Push `dev` without `--force`.

**6. This repository (D5), after the owner's yes.**
- One commit on `dev`: a line at the top of `CLAUDE.md`, `AGENTS.md` and `CONTEXT.md` reading
  "Development moved to `ifgf-tpe/members` on <date>. This repository is the archive of the
  ChurchCMS fork; do not build features here."
- Push it.
- Delete no branch, archive nothing.

## Exit gate

- `ifgf-tpe/members` `dev` has the same tree as this repository's `dev`, plus the step 5 commit.
- The test suite passes in the new folder.
- No GegoSoft commit is reachable in the new repository.
- `.env` and `~/.ifgf/` contents are nowhere in it.
- The owner opens `D:\Users\Ian Joseph\Documents\GitHub\members` in Claude Desktop for every
  session from now on.
