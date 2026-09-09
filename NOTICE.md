# Licensing and attribution

**Copyright © 2026 IFGF Taipei Zhongli** — for the IFGF-authored parts of this project.

This project is **free software for churches and ministries**, and contributions are
welcome. It is also built on someone else's work, and this file explains exactly which
licence covers which part — because getting that wrong would be unfair to them and legally
messy for you.

> ⚠ This is a plain-English summary written by the project, not legal advice. If your
> church needs certainty before deploying or redistributing, ask a lawyer. The licence
> files themselves are what actually binds.

---

## The short version

| You want to… | Allowed? |
|---|---|
| Use this at your church, free, forever | **Yes** |
| Change it to fit your church | **Yes** |
| Run it for several churches you serve | **Yes** |
| Pay a developer to install, host or customise it for you | **Yes** |
| Share your improvements back | **Yes, please** |
| Fork it, keep your changes secret, and sell it as a hosted product | **No** |

That last row is the whole point of the licence chosen below.

---

## Two licences, two different parts

### 1. IFGF-authored code — GNU AGPL-3.0-or-later

**File: [`LICENSE`](LICENSE)**

Everything written for IFGF is licensed under the **GNU Affero General Public License,
version 3 or later**. That includes:

- `custompackages/ifgf/church-operations/` — the whole package
- `database/migrations/ifgf/` — the `ifgf_` schema
- `app/Http/Controllers/{Usher,MemberApp,Ifgf}/`
- `app/Http/Middleware/{EnsureUsher,SetIfgfLocale}.php`
- `app/Http/Requests/Ifgf/`
- `resources/views/ifgf/`, `resources/lang/*/ifgf.php`
- `config/ifgf-sites.php`, `bootstrap/global-env.php`
- `tests/Feature/Ifgf/`, `tests/Feature/Credential/`, `tests/Unit/Ifgf/`, `tests/IfgfTestCase.php`
- `tools/` and the project documentation

**Why the AGPL and not something simpler.**

The goal was: any church or ministry may use and improve this freely, but nobody should be
able to take it, close it, and sell it back to churches. The AGPL is the strongest
protection against that which is still genuinely open source.

Its distinguishing clause is **section 13, "Remote Network Interaction"**. A normal open
licence only requires you to share your changes if you *distribute* the software — and a
hosted web app is never distributed, so that obligation never triggers. Section 13 closes
that gap: if you run a **modified** version and let people use it over a network, you must
offer them its source.

So a company is not forbidden from using this. It is simply required to give back. That
turns the commercial route into a contribution rather than an extraction.

**A deliberate choice, made with the trade-off understood.** A "non-commercial only"
licence was considered and rejected. Such licences are *not* open source by definition —
the Open Source Definition forbids discriminating against any field of endeavour — which
means no GitHub licence badge, a smaller pool of willing contributors, and real ambiguity
at the edges ("is a church paying a freelancer to host it commercial?"). The AGPL avoids
all of that while still preventing the outcome that actually mattered.

### 2. The underlying church management system — MIT

**File: [`LICENSE.upstream-MIT`](LICENSE.upstream-MIT)**

This project is a fork of **ChurchCMS**, `churchcms/church-cms`, which is open source
under the **MIT License**, copyright **GegoSoft Technologies (OPC) Private Limited**.

🔴 **That notice is retained verbatim and must never be removed.** MIT grants very broad
rights — including the right to sublicense, which is what makes the AGPL choice above
lawful for the combined work — but it is conditional on one thing: the copyright notice and
permission notice travel with every copy. Deleting `LICENSE.upstream-MIT` would breach the
only obligation MIT imposes.

Those upstream files remain available under MIT from upstream, independently of this fork.
Choosing the AGPL for the combined work does not, and cannot, take anything away from
GegoSoft or from anyone else using ChurchCMS.

The footer of every IFGF page carries this acknowledgement, and
`config/ifgf-sites.php` explains why it is not optional.

---

## Third-party dependencies

Everything in `vendor/` and `node_modules/` belongs to its own authors under its own
licence. Nothing here changes those. Run `composer licenses` for the current list.

---

## Contributing

Contributions from other churches, ministries and developers are genuinely wanted — that
is why this is licensed the way it is rather than kept private.

By opening a pull request you agree your contribution is licensed under **AGPL-3.0-or-later**,
the same terms as the rest of the IFGF-authored code. No copyright assignment is asked for:
you keep the copyright in your own work.

Things worth knowing before you start:

- The project documentation lives in `PG_MIGRATION_PLAN.md`; the walkthrough is in
  `USER_GUIDE.md`.
- Run `php artisan test` before opening a PR.
- Nothing containing real member data — spreadsheets, exports, database dumps, screenshots
  of a live roster — may ever be committed. The test suite builds its own synthetic
  fixtures precisely so that it never needs any.

---

## Using this at your church

You do not need permission and you do not owe anyone anything. Take it, deploy it, change
it. If you improve it, sharing that back helps every other church using it — and if you run
a modified copy as a website, section 13 asks you to.

If it helps your ministry, that was the point.
