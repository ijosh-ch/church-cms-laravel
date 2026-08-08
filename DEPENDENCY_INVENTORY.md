# DEPENDENCY_INVENTORY — Work Package 0A, Session 2

> Deliverable for `build.md` WORK PACKAGE 0A item 3 ("Inventory all production and development
> packages, custom packages, abandoned packages, known vulnerabilities, and framework
> constraints"). Produced 2026-08-09 from `EXECUTION_PLAN.md` §1.4 and the `UPSTREAM.md` security
> table, cross-checked against real usage in `app/`, `routes/`, and `resources/`. Not read at
> session start — `CONTEXT.md`/`TODO.md` link here; this file itself has no token cap.

## Method

Severity label alone does not set priority here — the same method `UPSTREAM.md` UP-004 used for
`symfony/yaml`: for every advisory-bearing or compatibility-flagged package, `composer why` /
`grep` located the real call sites in this application, and the verdict is **is this code path
actually reachable with attacker-influenced input**, not just "is the package present." Where no
call site exists, the advisory is real but currently inert — flagged for the record, not urgency.

## Composer — 194 packages, all classified

| Disposition | Count | Meaning |
|---|---:|---|
| **keep** | 173 | Current release, no advisory, no Laravel-13 conflict. |
| **upgrade** | 17 | Advisory-bearing (13) or Laravel-13-incompatible (4: sanctum, collision, laratrust, +1 counted once — medialibrary appears in both sets) needing a version bump, in place. |
| **replace** | 0 | None at the Composer layer this session — see npm below for the frontend-toolchain replacements. |
| **remove** | 4 | `botman/botman`, `botman/driver-web`, `laravel/legacy-factories`, `doctrine/annotations`. |

### Two findings beyond what `EXECUTION_PLAN.md` §1.4 predicted

1. **`botman/botman` + `botman/driver-web` are dead code, not just "high risk."** §1.4 called them
   an "isolation candidate." `grep -rl "BotMan" app/ config/ routes/` returns **zero** matches —
   nothing in this application ever constructs a BotMan bot. Reclassified from "isolate" to
   **remove outright**; there is nothing to isolate.
2. **`custompackages/brozot/laravel-fcm` is an orphaned path repository, not an active custom
   package.** `composer.json` still declares the path repository
   (`./custompackages/brozot/laravel-fcm`) and the directory still exists on disk with its own
   `composer.json`, `src/`, and `tests/` — but **nothing in the root `composer.json` requires
   it** (`composer show brozot/laravel-fcm` returns "not found"). The project has already moved to
   `laravel-notification-channels/fcm` 4.5.0 (installed, part of the UP-001 fix set). This package
   is **not** one of the 194 resolved packages, so it has no row in the table below — flagged here
   instead. **Recommendation:** remove the `repositories` entry and the `custompackages/brozot/`
   directory in a WP 0A/0B cleanup pass; it is dead weight the baseline is currently carrying
   silently.

### Advisory triage summary — 49 advisories / 13 packages, by real exposure

| Priority | Packages | Why |
|---|---|---|
| **Prioritize now** | `phpoffice/phpspreadsheet` (9 adv., 2 critical) | Confirmed live sink: `ImportMemberController.php:56` calls `Excel::import()` on an admin-uploaded file; 4 more `Imports` classes exist. Includes RCE/SSRF-class CVEs on a file-upload path. |
| **High, real path** | `spatie/laravel-medialibrary` (2 adv.) | File-upload-bypass and SSRF CVEs sit on real `addMedia()`/`HasMedia` usage in `PageAttachmentsController`, `PageAttachment`, `Post`. Already needed for Laravel 13 (§1.4) — one upgrade pass covers both. |
| **Medium, plausible path** | `laravel/framework` (3 adv.), `symfony/mailer` (1), `symfony/mime` (2) | Email-CRLF-injection family. Member email is a self-editable profile field feeding 26 `Mail::to()`/`->notify()` call sites — plausible chain, not proven. Also: 1 `signedRoute` call site exists; confirm during WP 0A item 4 (route inventory) whether QR/attendance signed URLs are exposed to the signed-URL-confusion CVE. |
| **Low-medium, narrow path** | `guzzlehttp/guzzle` (9 adv.) | Only 1 direct call site (`ValidRecaptcha.php`, fixed Google endpoint). Transitive users all hit fixed vendor endpoints. `spatie/crawler`'s link-following stays inside the app's own domain. |
| **Low, no live path found** | `dompdf/dompdf` (6), `spatie/browsershot` (6), `symfony/dom-crawler` (1), `symfony/http-foundation` (1), `symfony/polyfill-intl-idn` (1), `symfony/routing` (2), `league/commonmark` (6) | Zero or narrow call sites: dompdf/browsershot are present but never invoked; dom-crawler only parses the app's own generated HTML; http-foundation's guard class is never wired; polyfill-intl-idn's homograph bug has no allow/deny-list logic to bypass; routing's URL-generation edge case has no matching pattern in-app; commonmark only renders developer-authored Markdown mail templates (verify in WP 0A item 6). |

Full per-package reasoning, including exact call-site paths, is in the **Reason** column of the
table below — this summary just orders it by what to act on first.

### Full Composer classification (194 packages)

<details>
<summary>Expand — 194 rows</summary>

| Package | Version | Scope | Disposition | Reason |
|---|---|---|---|---|
| `aws/aws-crt-php` | 1.2.7 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `aws/aws-sdk-php` | 3.381.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `bacon/bacon-qr-code` | 2.0.8 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `barryvdh/laravel-debugbar` | 3.16.5 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `barryvdh/laravel-dompdf` | 2.2.0 | direct | **keep** | Direct dependency, no advisory on this package itself, but confirmed dead code: zero Pdf::/Dompdf call sites anywhere in app/ or resources/views/. Keep pinned and patch its dompdf/dompdf dependency regardless; revisit removal if PDF export (membership cards, certificates) is confirmed out of scope. |
| `beste/clock` | 3.0.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `beste/in-memory-cache` | 1.5.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `beste/json` | 1.7.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `botman/botman` | 2.8.11 | direct | **remove** | EXECUTION_PLAN 1.4 flagged as high-risk/unmaintained for Laravel 11+. Zero usage found in app/, config/, or routes/ (grep confirmed) — dead dependency, safe to drop outright rather than isolate. |
| `botman/driver-web` | 1.5.3 | direct | **remove** | Companion driver to botman/botman; same zero-usage finding, remove together. |
| `brick/math` | 0.12.3 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `carbonphp/carbon-doctrine-types` | 2.1.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `composer/pcre` | 3.3.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `composer/semver` | 3.4.4 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `cuyz/valinor` | 2.5.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `darkaonline/l5-swagger` | 8.6.5 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. Sole consumer of the abandoned doctrine/annotations below. |
| `dasprid/enum` | 1.0.7 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `dflydev/dot-access-data` | 3.0.3 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `doctrine/annotations` | 2.0.2 | transitive | **remove** | Marked abandoned upstream with no replacement offered. Sole consumer is darkaonline/l5-swagger (^1.0\|\|^2.0), an admin-only OpenAPI doc generator. Drop when l5-swagger is upgraded to an attribute-based OpenAPI release in WP 0B; do not patch or pin an abandoned package in place. |
| `doctrine/inflector` | 2.1.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `doctrine/lexer` | 3.0.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `dompdf/dompdf` | 2.0.8 | transitive | **upgrade** | REAL EXPOSURE: NONE FOUND. 6 medium/low advisories (SVG/BMP file-existence and DoS issues, <3.1.6). Zero call sites in app/ or resources/views/ — barryvdh/laravel-dompdf is required but never invoked. Upgrade opportunistically (cheap), do not treat as urgent. |
| `dragonmantank/cron-expression` | 3.6.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `egulias/email-validator` | 4.0.4 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `evenement/evenement` | 3.0.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `ezyang/htmlpurifier` | 4.19.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `fakerphp/faker` | 1.24.1 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `fig/http-message-util` | 1.1.5 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `filp/whoops` | 2.18.4 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `firebase/php-jwt` | 7.1.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `fruitcake/php-cors` | 1.4.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `google/auth` | 1.53.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `google/cloud-core` | 1.72.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `google/cloud-language` | 0.31.3 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `google/cloud-storage` | 1.51.0 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `google/common-protos` | 4.14.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `google/gax` | 1.42.4 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `google/grpc-gcp` | 0.4.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `google/longrunning` | 0.7.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `google/protobuf` | 5.34.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `graham-campbell/result-type` | 1.1.4 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `grpc/grpc` | 1.80.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `guzzlehttp/guzzle` | 7.10.0 | direct | **upgrade** | REAL EXPOSURE: LOW-MEDIUM. 9 advisories (1 high) about cookie-jar/host/proxy/redirect handling. Only 1 direct app call site (app/Rules/ValidRecaptcha.php, fixed Google endpoint, no cookie jar). Transitive users (AWS SDK, Firebase, Google Cloud, Twilio, FCM) all hit fixed first-party vendor endpoints. spatie/crawler + spatie/laravel-sitemap (app/Console/Commands/GenerateSitemap.php) follow links, but only within the app's own domain, not attacker-supplied URLs. Patch on the next composer update pass; not an incident. |
| `guzzlehttp/promises` | 2.5.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `guzzlehttp/psr7` | 2.13.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `guzzlehttp/uri-template` | 1.0.5 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `hamcrest/hamcrest-php` | 2.1.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `intervention/image` | 2.7.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `kkszymanowski/traitor` | 1.1.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `kreait/firebase-php` | 7.24.1 | transitive | **keep** | Transitive via kreait/laravel-firebase. |
| `kreait/firebase-tokens` | 5.5.0 | transitive | **keep** | Transitive via kreait/laravel-firebase. |
| `kreait/laravel-firebase` | 5.10.0 | direct | **keep** | UP-001: lock resynced; Firebase Admin SDK Laravel wrapper. |
| `laracasts/presenter` | 0.2.8 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `laravel-notification-channels/fcm` | 4.5.0 | direct | **keep** | UP-001: lock resynced; active FCM push channel, supersedes the orphaned custompackages/brozot/laravel-fcm fork (see note above — that fork is not one of these 194 packages). |
| `laravel/dusk` | 7.13.0 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `laravel/framework` | 10.50.2 | direct | **upgrade** | REAL EXPOSURE: MEDIUM-HIGH. Framework itself, cannot be isolated. CVE-2026-48019 (CRLF injection in the default `email` validation rule) is reachable everywhere the app validates member/user emails (8 form requests use the email rule) and any of the 26 Mail::to()/->notify() call sites that reuse that value in a header. Signed-URL path confusion is relevant if QR/attendance check-in uses temporarySignedRoute (1 signedRoute call site found; confirm in WP 0A item 4 route inventory). Prioritize this upgrade path in WP 0B; the current 10.50.2 install cannot itself be patched without a framework major/minor bump. |
| `laravel/legacy-factories` | 1.4.2 | direct | **remove** | Hard Laravel-13 blocker (EXECUTION_PLAN 1.4). Laravel 10 max. All factories must move to the native Laravel 8+ factory pattern before WP 0B; UP-003 already fixed the one PSR-4 casualty this package's presence was masking. |
| `laravel/prompts` | 0.1.25 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `laravel/sanctum` | 3.3.3 | direct | **upgrade** | EXECUTION_PLAN 1.4: needs ^4 for Laravel 13. No advisory currently, purely a compatibility upgrade, WP 0B. |
| `laravel/serializable-closure` | 1.3.7 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `laravel/tinker` | 2.11.1 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `laravel/ui` | 4.6.3 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `lcobucci/jwt` | 5.6.0 | direct | **keep** | UP-001 upgraded 4.3.0->5.6.0 to satisfy composer.json ^5.2; verified in UPSTREAM.md. |
| `league/commonmark` | 2.8.2 | transitive | **upgrade** | REAL EXPOSURE: LOW. 6 DoS-via-crafted-Markdown advisories. Pulled in by laravel/framework core for Markdown mail notifications (17 ->markdown() call sites), but those render developer-authored templates, not raw member-supplied Markdown. Verify no template interpolates unescaped user text directly into Markdown syntax during WP 0A characterization; otherwise defer. |
| `league/config` | 1.2.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `league/csv` | 9.28.0 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `league/flysystem` | 3.34.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `league/flysystem-aws-s3-v3` | 3.34.0 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `league/flysystem-local` | 3.31.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `league/glide` | 2.3.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `league/mime-type-detection` | 1.16.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `maatwebsite/excel` | 3.1.68 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. Its own dependency, phpoffice/phpspreadsheet, is the real risk — see below. |
| `maennchen/zipstream-php` | 3.2.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `markbaker/complex` | 3.0.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `markbaker/matrix` | 3.0.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `masterminds/html5` | 2.10.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `mockery/mockery` | 1.6.12 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `monolog/monolog` | 3.10.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `mpociot/pipeline` | 1.0.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `mtdowling/jmespath.php` | 2.9.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `myclabs/deep-copy` | 1.13.4 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `nckg/laravel-impersonate` | 4.0.1 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `nesbot/carbon` | 2.73.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `nette/schema` | 1.3.5 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `nette/utils` | 4.1.4 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `nicmart/tree` | 0.3.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `nikic/php-parser` | 5.7.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `nunomaduro/collision` | 6.4.0 | direct | **upgrade** | EXECUTION_PLAN 1.4: needs ^8 for Laravel 13. Dev-only CLI error formatter, low-risk upgrade, WP 0B. |
| `nunomaduro/termwind` | 1.17.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `phar-io/manifest` | 2.0.4 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `phar-io/version` | 3.2.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `phenx/php-font-lib` | 0.5.6 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `phenx/php-svg-lib` | 0.5.4 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `php-debugbar/php-debugbar` | 2.2.6 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `php-webdriver/webdriver` | 1.16.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `phpoffice/phpspreadsheet` | 1.30.0 | transitive | **upgrade** | REAL EXPOSURE: HIGH — PRIORITIZE. 9 advisories including 2 CRITICAL (CVE-2026-34084 SSRF/RCE via IOFactory::load, CVE-2026-45034 patch bypass) plus CPU/memory-exhaustion DoS and an SSRF via WEBSERVICE() formula evaluation. Confirmed live sink: app/Http/Controllers/Admin/ImportMemberController.php:56 calls Excel::import() directly on an admin-uploaded file (import_file), and 4 more Imports classes exist (Attendance, Subscribers, Summary, Users). Any authenticated user who can reach an import screen can hand PhpSpreadsheet a crafted XLS/XLSX/Gnumeric file. Do not defer this one to WP 0B. |
| `phpoption/phpoption` | 1.9.5 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `phpunit/php-code-coverage` | 10.1.16 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `phpunit/php-file-iterator` | 4.1.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `phpunit/php-invoker` | 4.0.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `phpunit/php-text-template` | 3.0.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `phpunit/php-timer` | 6.0.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `phpunit/phpunit` | 10.5.63 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `psr/cache` | 3.0.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `psr/clock` | 1.0.0 | transitive | **keep** | PSR-20 interface pulled in by lcobucci/jwt 5.x, replacing the removed lcobucci/clock concrete dependency (UP-002). |
| `psr/container` | 2.0.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `psr/event-dispatcher` | 1.0.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `psr/http-client` | 1.0.3 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `psr/http-factory` | 1.1.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `psr/http-message` | 2.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `psr/log` | 3.0.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `psr/simple-cache` | 3.0.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `psy/psysh` | 0.12.22 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `ralouphie/getallheaders` | 3.0.3 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `ramsey/collection` | 2.1.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `ramsey/uuid` | 4.9.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `react/cache` | 1.2.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `react/dns` | 1.14.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `react/event-loop` | 1.6.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `react/promise` | 3.3.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `react/socket` | 1.17.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `react/stream` | 1.4.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `rize/uri-template` | 0.4.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `sabberworm/php-css-parser` | 8.6.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `santigarcor/laratrust` | 7.2.1 | direct | **upgrade** | EXECUTION_PLAN 1.4: needs v8+ for Laravel 13. Confirmed 4 real usage sites and is the physical model for roles (invariant 9) — cannot be dropped, must be upgraded in place during WP 0B/0C role work. |
| `sebastian/cli-parser` | 2.0.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `sebastian/code-unit` | 2.0.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `sebastian/code-unit-reverse-lookup` | 3.0.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `sebastian/comparator` | 5.0.5 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `sebastian/complexity` | 3.2.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `sebastian/diff` | 5.1.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `sebastian/environment` | 6.1.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `sebastian/exporter` | 5.1.4 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `sebastian/global-state` | 6.0.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `sebastian/lines-of-code` | 2.0.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `sebastian/object-enumerator` | 5.0.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `sebastian/object-reflector` | 3.0.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `sebastian/recursion-context` | 5.0.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `sebastian/type` | 4.0.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `sebastian/version` | 4.0.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `simplesoftwareio/simple-qrcode` | 4.2.0 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `spatie/browsershot` | 3.61.0 | transitive | **upgrade** | REAL EXPOSURE: LOW. 6 advisories (SSRF, path traversal) in headless-Chrome rendering. Zero call sites in app/ — present only as a transitive dependency of spatie/crawler (via GenerateSitemap) and never invoked for URL/HTML rendering. Upgrade opportunistically. |
| `spatie/crawler` | 7.1.3 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `spatie/image` | 2.2.7 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `spatie/image-optimizer` | 1.8.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `spatie/laravel-activitylog` | 4.12.3 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `spatie/laravel-medialibrary` | 10.15.0 | direct | **upgrade** | REAL EXPOSURE: HIGH. CVE-2026-48557 (file-upload restriction bypass) and CVE-2026-48555 (SSRF) sit directly on a live upload path: app/Http/Controllers/Admin/PageAttachmentsController.php, app/Models/PageAttachment.php and app/Models/Post.php all use addMedia()/HasMedia for real member-facing file uploads. Also flagged in EXECUTION_PLAN 1.4 as needing v11/v12 for Laravel 13 — combine the security and compatibility upgrades into one WP 0B pass. |
| `spatie/laravel-package-tools` | 1.93.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `spatie/laravel-sitemap` | 6.4.0 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `spatie/macroable` | 2.1.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `spatie/robots-txt` | 2.5.4 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `spatie/temporary-directory` | 2.3.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `stripe/stripe-php` | 20.3.1 | direct | **keep** | UP-001: lock resynced to composer.json requirement; app/Services/Payment/StripeService.php consumes it. |
| `swagger-api/swagger-ui` | 5.32.6 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/cache` | 7.4.16 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/cache-contracts` | 3.7.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/console` | 6.4.39 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/css-selector` | 7.4.9 | transitive | **keep** | UP-002 resolved off the stranded PHP>=8.4-only v8.0.9 onto 7.4.9. |
| `symfony/deprecation-contracts` | 3.7.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/dom-crawler` | 6.4.34 | transitive | **upgrade** | REAL EXPOSURE: LOW. XXE advisory requires validateOnParse=true on attacker-controlled XML; only consumer is spatie/crawler parsing the app's own generated HTML during sitemap generation. |
| `symfony/error-handler` | 6.4.36 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/event-dispatcher` | 7.4.9 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/event-dispatcher-contracts` | 3.7.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/filesystem` | 7.4.15 | transitive | **keep** | UP-002 resolved off the stranded PHP>=8.4-only v8.0.11 onto 7.4.15. |
| `symfony/finder` | 6.4.34 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/http-foundation` | 6.4.35 | transitive | **upgrade** | REAL EXPOSURE: LOW. SSRF-guard bypass only matters if the app uses Symfony's NoPrivateNetworkHttpClient; Laravel does not wire this by default and no app code references it. |
| `symfony/http-kernel` | 6.4.39 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/mailer` | 6.4.34 | transitive | **upgrade** | REAL EXPOSURE: MEDIUM. CRLF/argument-injection in SendmailTransport via crafted recipient address. 26 Mail::to()/->notify() call sites exist; member email is a self-editable profile field, so an unsanitized email reaching a raw mail header is plausible. Pairs with the laravel/framework email-validation CVE above — same root cause, same fix window. |
| `symfony/mime` | 6.4.37 | transitive | **upgrade** | REAL EXPOSURE: MEDIUM. Header-injection CVEs in Address/Mime parameter parsing, same reachability path as symfony/mailer above (member-editable email feeding Mail::to()). |
| `symfony/polyfill-ctype` | 1.37.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/polyfill-intl-grapheme` | 1.37.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/polyfill-intl-idn` | 1.37.0 | transitive | **upgrade** | REAL EXPOSURE: LOW. Narrow IDN/Punycode homograph-equivalence bug; the app has no domain allow/deny-list logic keyed on IDN comparison. Patch opportunistically. |
| `symfony/polyfill-intl-normalizer` | 1.37.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/polyfill-mbstring` | 1.38.2 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/polyfill-php80` | 1.37.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/polyfill-php83` | 1.37.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/polyfill-uuid` | 1.37.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/process` | 6.4.41 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/routing` | 6.4.37 | transitive | **upgrade** | REAL EXPOSURE: LOW. URL-generation edge cases (dot-segment, regex-alternation bypass) matter for apps that build further trust decisions on generated route URLs; no such pattern found in this app. |
| `symfony/service-contracts` | 3.7.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/string` | 7.4.11 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/translation` | 6.4.38 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/translation-contracts` | 3.7.0 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/uid` | 6.4.32 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/var-dumper` | 6.4.36 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/var-exporter` | 7.4.16 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `symfony/yaml` | 7.4.15 | transitive | **upgrade** | UP-004 applied (7.4.11->7.4.15) but the 3 CVEs were hygiene, not live exposure (see UPSTREAM.md) — no consumer parses attacker-controlled YAML. Re-run exposure check if a future feature accepts uploaded YAML/OpenAPI documents. |
| `theseer/tokenizer` | 1.3.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `tijsverkoyen/css-to-inline-styles` | 2.4.0 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `titasgailius/search-relations` | 1.0.6 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `twilio/sdk` | 6.44.4 | direct | **keep** | Direct dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `vlucas/phpdotenv` | 5.6.3 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `voku/portable-ascii` | 2.1.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |
| `zircote/swagger-php` | 4.11.1 | transitive | **keep** | Transitive dependency, current release, no advisory, no Laravel-13 constraint conflict found. |

</details>

---

## npm — 51 direct packages (`package.json` `dependencies` + `devDependencies`), all classified

`npm audit` on the full 1,286-package tree: **166 vulnerabilities (13 critical, 58 high, 78
moderate, 17 low)**. The overwhelming majority sit inside the `laravel-mix`/`webpack@4` build
toolchain's own transitive dependencies (`loader-utils`, `postcss`-family, `braces`,
`micromatch`, `chokidar`, `cross-spawn`, `node-forge`, …) — those are **not** listed
individually below; they are inherited wholesale from the toolchain and go away with the WP 0B
Vite migration (`build.md` item 8), not by patching each one.

| Disposition | Count | Meaning |
|---|---:|---|
| **keep** | 34 | No advisory, not EOL-tied, or independently upgradable without the Vite decision. |
| **upgrade** | 2 | `axios`, `lodash` — direct high-severity findings, cheap to patch now, independent of the Vite timeline. |
| **replace** | 14 | Vue-2-locked or unmaintained-with-a-live-CVE packages; replaced as part of the WP 0B Vue2→Vite/Vue3 migration, not patched individually. |
| **remove** | 1 | `@dymantic/vue-trix-editor` — superseded in-place by `@tiptap/*`, which is already installed and in use. |

| Package | Constraint | Scope | Disposition | Reason |
|---|---|---|---|---|
| `@dymantic/vue-trix-editor` | ^0.5.0 | dep | **remove** | HIGH in npm audit; unmaintained Trix wrapper, Vue-2-only. Confirmed still referenced in one Vue component, but `@tiptap/*` (Vue-3-ready, modern) is already installed and referenced in two components — this looks like a migration in progress. Finish it and drop this package rather than patch it. |
| `@fullcalendar/daygrid` | ^5.5.0 | dep | **replace** | FullCalendar v5 is Vue-2-only; v6 supports Vue 3. Bundle with the Vite/Vue migration. |
| `@fullcalendar/interaction` | ^5.5.0 | dep | **replace** | Same FullCalendar v5→v6 migration as above. |
| `@fullcalendar/timegrid` | ^5.5.1 | dep | **replace** | Same FullCalendar v5→v6 migration as above. |
| `@fullcalendar/vue` | ^5.5.0 | dep | **replace** | Same FullCalendar v5→v6 migration as above; also flagged low in npm audit. |
| `@tiptap/extension-image` | ^3.22.2 | dep | **keep** | Current major, Vue-3-ready, actively used — the target rich-text editor. |
| `@tiptap/extension-link` | ^3.22.2 | dep | **keep** | Same as above. |
| `@tiptap/extension-underline` | ^3.22.2 | dep | **keep** | Same as above. |
| `@tiptap/pm` | ^3.22.2 | dep | **keep** | Same as above. |
| `@tiptap/starter-kit` | ^3.22.2 | dep | **keep** | Same as above. |
| `@tiptap/vue-2` | ^3.22.2 | dep | **keep** | Low advisory in npm audit; Vue-2 build of TipTap — swap for `@tiptap/vue-3` at the same time as the framework migration, not before (no standalone fix exists). |
| `bootstrap` | ^4.6.0 | dep | **replace** | Bootstrap 4 is EOL (Bootstrap 5 current). No npm-audit CVE by name today, but EOL means no future patches either. Bundle with WP 0B frontend modernization. |
| `emoji-mart-vue` | ^2.6.6 | dep | **replace** | Moderate in npm audit; unmaintained Vue-2 wrapper around emoji-mart. |
| `jquery` | ^3.6.0 | dep | **upgrade** | No advisory, but 3.7.x is current and framework-agnostic — safe to bump independent of Vite/Vue timing. |
| `jquery-datetimepicker` | ^2.5.21 | dep | **keep** | jQuery plugin, framework-agnostic, no advisory found; survives the Vue migration either way. |
| `popper.js` | ^1.16.1 | dep | **replace** | Popper v1 predecessor to `@popperjs/core` v2; tied to `vue-popperjs` below, migrate together. |
| `portal-vue` | ^1.5.1 | dep | **replace** | v1 is Vue-2-only; v2 supports Vue 3. Bundle with the framework migration. |
| `quill` | ^1.3.7 | dep | **replace** | Old major (2.x current); tied to `vue-quill-editor` below which is Vue-2-locked anyway. |
| `vue-audio-recorder` | ^3.0.1 | dep | **keep** | No advisory found; small, framework-version-agnostic wrapper. Re-verify Vue-3 compatibility at migration time. |
| `vue-carousel` | ^0.18.0 | dep | **replace** | Low in npm audit; unmaintained, Vue-2-only. |
| `vue-clipboard2` | ^0.3.1 | dep | **replace** | Name says it — Vue-2-only. `vue-clipboard3` is the Vue-3 successor. |
| `vue-csv-import` | 2.3.4 | dep | **replace** | Vue-2-only CSV import widget; no maintained Vue-3 release found. |
| `vue-faq-accordion` | ^1.6.2 | dep | **replace** | Vue-2-only, low-traffic UI widget; trivial to reimplement post-migration. |
| `vue-flash-message` | ^0.7.2 | dep | **replace** | Vue-2-only flash-message plugin. |
| `vue-image-lightbox-carousel` | ^1.0.7 | dep | **replace** | **CRITICAL in npm audit** (pulls in vulnerable `swiper`/`vue-awesome-swiper`). Confirmed actively used (1 component). Not dead code — replace with a maintained lightbox library, do not just delete the feature. |
| `vue-loading-overlay` | ^3.4.2 | dep | **replace** | Low in npm audit; Vue-2-only. |
| `vue-multiselect` | ^2.1.6 | dep | **replace** | v2 is Vue-2-only; v3 supports Vue 3. |
| `vue-popperjs` | ^2.3.0 | dep | **replace** | Tied to `popper.js` v1 above; migrate together. |
| `vue-quill-editor` | ^3.0.6 | dep | **replace** | Vue-2-only Quill wrapper; superseded functionally by the TipTap migration already underway. |
| `vue-simple-uploader` | ^0.7.6 | dep | **keep** | No advisory found; re-verify Vue-3 compatibility at migration time rather than pre-emptively replacing. |
| `vue-simplemde` | ^2.0.0 | dep | **keep** | No advisory found; small Markdown-editor wrapper, re-verify at migration time. |
| `vue-swal` | ^1.0.0 | dep | **replace** | Low in npm audit; thin unmaintained SweetAlert2 wrapper, trivial to call SweetAlert2 directly post-migration. |
| `vue-tree-chart` | ^1.2.9 | dep | **replace** | Low in npm audit; unmaintained, Vue-2-only. |
| `vue-upload-multiple-image` | ^1.1.6 | dep | **keep** | No advisory found; re-verify Vue-3 compatibility at migration time. |
| `vue2-collapse` | ^1.0.15 | dep | **replace** | Name says it — Vue-2-only. |
| `vue2-dropzone` | ^3.6.0 | dep | **replace** | Name says it — Vue-2-only; Dropzone core itself is fine, only the wrapper is locked. |
| `vuejs-datepicker` | ^1.6.2 | dep | **replace** | Low in npm audit; unmaintained since ~2021, Vue-2-only. |
| `vuejs-datetimepicker` | ^1.1.13 | dep | **replace** | Low in npm audit; Vue-2-only. |
| `vuejs-paginate` | ^2.1.0 | dep | **keep** | No advisory found; re-verify Vue-3 compatibility at migration time rather than pre-emptively replacing. |
| `axios` | ^0.18 | devDep | **upgrade** | HIGH in npm audit. 0.18.x predates multiple SSRF/ReDoS fixes. Cheap, largely drop-in bump to 1.x independent of the Vite/webpack decision — do this now, don't wait for WP 0B. |
| `cross-env` | ^5.2.1 | devDep | **keep** | No advisory found; trivial cross-platform env-var setter, survives a Vite migration unchanged. |
| `laravel-mix` | ^4.1.4 | devDep | **replace** | HIGH in npm audit (transitively, via its webpack4 toolchain). This *is* the WP 0B item 8 Vite decision — do not patch in place. |
| `laravel-mix-purgecss` | ^4.2.0 | devDep | **replace** | HIGH in npm audit; tied 1:1 to laravel-mix's webpack4 major, migrate together. |
| `laravel-mix-tailwind` | ^0.1.2 | devDep | **replace** | Tied to laravel-mix; Vite has native Tailwind integration, no equivalent plugin needed post-migration. |
| `lodash` | ^4.17.21 | devDep | **upgrade** | HIGH in npm audit despite already being the latest 4.x release — confirm the exact advisory ID and whether it requires the 5.x line; cheap to patch either way, independent of Vite timing. |
| `resolve-url-loader` | 3.1.0 | devDep | **replace** | webpack4-era loader; Vite resolves url() natively, no replacement package needed post-migration. |
| `sass` | ^1.32.8 | devDep | **upgrade** | Dart Sass compiler itself is framework/bundler-agnostic — bump to current 1.7x now; Vite will keep using it post-migration. |
| `sass-loader` | ^7.3.1 | devDep | **replace** | webpack-specific loader; Vite's built-in Sass integration replaces this package outright. |
| `tailwindcss` | ^1.9.6 | devDep | **upgrade** | Tailwind 1.x is several majors behind (4.x current). Independent of the Vite decision but a real migration in its own right (utility-class renames) — schedule deliberately in WP 0B, don't bundle silently with the bundler swap. |
| `vue` | ^2.6.12 | devDep | **replace** | Vue 2 reached EOL 2023-12-31. This is the root of the whole "replace" cluster above — every Vue-2-only plugin listed follows from this one decision. |
| `vue-template-compiler` | ^2.6.12 | devDep | **replace** | Pinned 1:1 to the `vue` version; replaced by `@vue/compiler-sfc` when Vue 3 lands. |

---

## Cross-references

- `EXECUTION_PLAN.md` §1.4 — original Laravel-13 blocker list this session validated and extended.
- `UPSTREAM.md` — UP-001 through UP-004 (composer.lock fixes already applied) and the "Security
  findings — deferred" section this file supersedes with real-exposure triage.
- `TODO.md` — Session 3 picks up build.md item 4a (route + migration inventory), which will
  confirm or rule out the signed-URL exposure flagged above for `laravel/framework`.
