<?php

namespace Tests\Feature\Media;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Characterization suite 11 — private media and storage.
 * WP 0A item 6 / gate 5. TESTING_PLAN.md Part 1, suite 11.
 *
 * CAPTURES WHAT IS. Several tests here document defects by asserting the wrong
 * behaviour; they are named test_documents_defect_*. A green run on those means
 * "this has not changed", never "this is correct".
 *
 * ══ THE HEADLINE: THERE IS NO PRIVATE MEDIA ══
 *
 * The suite is named "private media" after TESTING_PLAN.md. Measured 2026-08-21, no
 * such thing exists in this application. Every disk that receives user content is
 * declared `'visibility' => 'public'`; the `uploads` disk is rooted at public_path()
 * itself, so anything written through it lands directly in the webroot; and
 * `public/storage` is a live symlink to `storage/app/public`. Member photographs are
 * therefore served by the webserver with NO PHP in the request path — which means no
 * authentication, no authorization and no audit are even reachable, let alone applied.
 *
 * The only thing standing between an unauthenticated stranger and a member's
 * photograph is the 40-character random filename Laravel's hashName() generates. That
 * is OBSCURITY, NOT AUTHORIZATION, and it is defeated by any of: a leaked URL, a
 * referrer header, a directory listing, a browser extension, or a backup of the
 * webroot. The URL is also emitted in plain JSON by two endpoints (see below).
 *
 * `Storage::disk('public')` is used at 16 call sites and `disk('s3')` at 1. No call
 * site anywhere in app/ uses a private disk, temporaryUrl(), a signed route, or
 * hasValidSignature(). Verified by test_documents_public_urls_carry_no_signature.
 *
 * ══ WHY THIS SUITE WAS TAKEN EARLY ══
 *
 * It and suite 9 (exports) were the two wholly uncharacterized surfaces that touch
 * member PII. A church member database holding photographs of minors on a public URL
 * is a materially different risk from a stale dependency, and it had never been
 * measured.
 *
 * ══ SEC-003 — FOUND, MIS-SIZED, RE-MEASURED, THEN FIXED (all on 2026-08-21) ══
 *
 * The avatar endpoint accepted ANY file type with no validation whatsoever. Found this
 * session, and **fixed the same day by owner direction** rather than left characterized
 * — so the tests below assert the FIXED behaviour, not a defect.
 *
 * ⚠ READ THIS BEFORE TRUSTING ANY UPLOAD ASSERTION — IT COST A WRONG SEVERITY RATING.
 *
 * The finding was first reported as a deployment-dependent **RCE**, on the strength of a
 * test using `UploadedFile::fake()->createWithContent('shell.php', ...)`, which stored
 * the file as `.php`. THAT WAS THE HARNESS, NOT THE APPLICATION:
 *
 *   UploadedFile::fake()  -> mime application/x-php -> guessExtension 'php'  -> stored .php
 *   a REAL upload         -> mime text/x-php        -> guessExtension ''     -> stored with
 *                                                                              NO EXTENSION
 *
 * `Storage::putFile()` names files with `hashName()`, which derives the extension from the
 * **detected MIME type**, never from the client filename. Real PHP content has no
 * extension mapping in Symfony's MimeTypes, so it landed extensionless and could never
 * have matched a `\.php$` handler. A PHP file *named* `.jpg` landed extensionless too.
 *
 * THE REAL VECTOR WAS STORED XSS, and it was genuine: `.svg` stored as `.svg` and
 * `.html`/`.xhtml` stored as `.html`, both served from `/storage/…` on the application's
 * OWN ORIGIN. An SVG carrying `<script>` executes when navigated to.
 *
 * This is the fourth time this project has asserted against the harness instead of the
 * application, and the first time the error inflated a severity rating rather than
 * merely wasting a commit. **Fakes are for convenience, not for security conclusions —
 * construct a real UploadedFile from real bytes when the answer depends on file content.**
 *
 * ⚠ AND: Laravel's `image` validation rule ALLOWS SVG (ValidatesAttributes line ~1535
 * excludes it only without `allow_svg`). The fix therefore uses an explicit
 * `mimes:jpg,jpeg,png,bmp,webp` list. **Do not "simplify" it to `image`** — that would
 * silently reopen the one vector that actually existed.
 */
class PrivateMediaCharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    /** Clears the churchadmin gate; not AdminOrPermission's bypass value. */
    private const ADMIN_USERGROUP_ID = 4;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRuntimeSettings();
    }

    // ---- how storage is configured --------------------------------------------

    /**
     * DOCUMENTS that no disk in this application is private.
     *
     * `local` carries no explicit visibility and is rooted OUTSIDE the webroot, so it
     * would be the private disk if anything were written to it — nothing is. The
     * three disks that do receive user content are all explicitly public.
     */
    public function test_documents_defect_no_disk_is_configured_private(): void
    {
        $disks = config('filesystems.disks');

        $this->assertSame(
            ['local', 'public', 's3', 'uploads'],
            array_keys($disks),
            'The disk list has changed. If a PRIVATE disk was added, that is the fix for '
            .'this whole suite — re-baseline it and check which call sites moved.'
        );

        foreach (['public', 's3', 'uploads'] as $name) {
            $this->assertSame(
                'public',
                $disks[$name]['visibility'] ?? null,
                "Disk '{$name}' is no longer declared public. Check whether the call "
                .'sites that write member photographs moved with it.'
            );
        }

        $this->assertSame('local', config('filesystems.default'));
    }

    /**
     * DOCUMENTS DEFECT — the `uploads` disk is rooted at the webroot itself.
     *
     * 'root' => public_path(). Any write through this disk places a file inside the
     * directory the webserver serves, at a path the caller controls. There is no
     * containment boundary of any kind: not a subdirectory, not a deny rule, not an
     * .htaccess. Combined with SEC-003 below, this is the ingredient that turns an
     * unvalidated upload into a webroot write.
     */
    public function test_documents_defect_uploads_disk_is_rooted_at_the_webroot(): void
    {
        $this->assertSame(
            public_path(),
            config('filesystems.disks.uploads.root'),
            'The uploads disk is no longer rooted at the webroot. That is an improvement '
            .'— confirm where it points now and re-baseline.'
        );
    }

    /**
     * DOCUMENTS that public media URLs carry no signature and no expiry.
     *
     * Asserted behaviourally rather than by reading the config, because the question
     * that matters is what the application HANDS OUT, not what it could hand out.
     * A signed URL would carry a query string; this one never does.
     */
    public function test_documents_public_urls_carry_no_signature(): void
    {
        $url = Storage::disk('public')->url('uploads/avatars/whatever.jpg');

        $this->assertNull(
            parse_url($url, PHP_URL_QUERY),
            'Public disk URLs now carry a query string. If temporaryUrl() or signed URLs '
            .'were introduced, the obscurity-only model this suite documents has been '
            .'replaced — re-baseline the whole suite.'
        );
        $this->assertStringContainsString('/storage/', $url);
    }

    // ---- the member photograph path -------------------------------------------

    /**
     * The end-to-end avatar path, pinned: upload lands on the PUBLIC disk and the
     * application immediately hands back a plain, unauthenticated URL.
     *
     * Both endpoints that expose it are asserted, because they are separate
     * disclosures: the upload response and the getavatar endpoint.
     */
    public function test_avatar_upload_stores_on_the_public_disk_and_returns_a_plain_url(): void
    {
        Storage::fake('public');
        $actor = $this->memberActor();

        $response = $this->actingAs($actor)->post('/admin/changeavatar', [
            'avatar' => UploadedFile::fake()->image('face.jpg', 64, 64),
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $stored = DB::table('userprofiles')->where('user_id', $actor->id)->value('avatar');
        $this->assertStringStartsWith('uploads/avatars/', (string) $stored);
        Storage::disk('public')->assertExists($stored);

        $payload = $response->json();
        $this->assertStringStartsWith(
            '/storage/',
            $payload['avatar'],
            'The avatar URL is no longer a plain /storage/ path. If it became a signed or '
            .'routed URL, member photographs are no longer served directly by the '
            .'webserver — that is the fix, re-baseline.'
        );

        $fetched = $this->actingAs($actor)->get('/admin/getavatar');
        $this->assertSame(200, $fetched->getStatusCode());
        $this->assertSame(
            $payload['avatar'],
            $fetched->json('avatar'),
            'getavatar and changeavatar now disagree about the avatar URL.'
        );
    }

    /**
     * DOCUMENTS that the ONLY protection on a member photograph is filename entropy.
     *
     * putFile() names the file with Laravel's hashName() — 40 random characters plus
     * the original extension. Nothing else guards it. This test exists so that the
     * control is named honestly in the baseline: if someone later "improves" the
     * naming to something readable (member id, name, upload date) they will remove
     * the only barrier there is, and this test will go red and say so.
     */
    public function test_documents_member_photo_privacy_rests_only_on_filename_entropy(): void
    {
        Storage::fake('public');
        $actor = $this->memberActor();

        $this->actingAs($actor)->post('/admin/changeavatar', [
            'avatar' => UploadedFile::fake()->image('face.jpg', 64, 64),
        ]);

        $stored = (string) DB::table('userprofiles')->where('user_id', $actor->id)->value('avatar');
        $basename = pathinfo($stored, PATHINFO_FILENAME);

        $this->assertSame(
            40,
            strlen($basename),
            'Uploaded media is no longer given a 40-character random name. IF THE NAME '
            .'IS NOW DERIVED FROM MEMBER DATA (id, name, date) the only privacy control '
            .'on member photographs has been REMOVED, because the files are public and '
            .'unauthenticated — treat as urgent.'
        );
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $basename);

        $this->assertStringNotContainsString((string) $actor->id, $basename);
        $this->assertStringNotContainsString($actor->name, $basename);
    }

    /**
     * SEC-003 FIXED — the avatar endpoint now rejects every non-image type.
     *
     * `UserProfileController::updatechangeavatar()` took a plain `Request`, called no
     * `validate()` and used no FormRequest. It now type-hints
     * `App\Http\Requests\EditUserProfileImgRequest`, which ALREADY EXISTED with exactly
     * the right rule (`mimes:jpg,jpeg,png,bmp,webp`) and was already wired to the API
     * twin `Api\UserprofileController::updateprofileImg()` — it had simply never been
     * attached to the two web endpoints. Recorded as **UP-012**.
     *
     * Rejection is **302 back with an error bag**, not 422, because these are web form
     * posts rather than JSON requests.
     *
     * Every case below is built from REAL BYTES via a real `UploadedFile`, never
     * `UploadedFile::fake()` — see the class docblock for why that distinction cost a
     * severity rating.
     *
     * `shell.jpg` carrying PHP source is the case that matters most: it proves the rule
     * checks the DETECTED MIME TYPE and not the client-supplied filename.
     *
     * NOTHING IS WRITTEN TO THE REAL WEBROOT — Storage::fake('public') redirects the
     * disk into a temporary directory first.
     */
    public function test_avatar_upload_rejects_every_non_image_type(): void
    {
        $actor = $this->memberActor();

        $rejected = [
            'svg carrying a script' => ['x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'html carrying a script' => ['x.html', '<html><script>alert(1)</script></html>'],
            'php source' => ['shell.php', "<?php echo 'characterization only';"],
            'php source NAMED .jpg' => ['shell.jpg', "<?php echo 'characterization only';"],
            'plain text' => ['note.txt', 'plain text'],
        ];

        foreach ($rejected as $label => [$filename, $bytes]) {
            Storage::fake('public');
            DB::table('userprofiles')->where('user_id', $actor->id)->update(['avatar' => null]);

            $response = $this->actingAs($actor)->post('/admin/changeavatar', [
                'avatar' => $this->realUpload($filename, $bytes),
            ]);

            $this->assertSame(
                302,
                $response->getStatusCode(),
                "SEC-003 HAS REGRESSED: {$label} was accepted. Check that "
                .'updatechangeavatar() still type-hints EditUserProfileImgRequest and that '
                ."the rule is still an explicit mimes: list — Laravel's `image` rule "
                .'permits SVG, which is the one vector that actually existed.'
            );

            $this->assertNull(
                DB::table('userprofiles')->where('user_id', $actor->id)->value('avatar'),
                "SEC-003 HAS REGRESSED: {$label} was written to the profile."
            );
            $this->assertEmpty(
                Storage::disk('public')->allFiles(),
                "SEC-003 HAS REGRESSED: {$label} reached the disk even though the request "
                .'was rejected.'
            );
        }
    }

    /**
     * The control on the other side of the SEC-003 fix: a genuine image still uploads.
     *
     * Without this, the rejection test above would pass just as happily on an endpoint
     * that had been broken outright.
     */
    public function test_avatar_upload_still_accepts_a_real_image(): void
    {
        Storage::fake('public');
        $actor = $this->memberActor();

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        $response = $this->actingAs($actor)->post('/admin/changeavatar', [
            'avatar' => $this->realUpload('face.png', $png),
        ]);

        $this->assertSame(
            200,
            $response->getStatusCode(),
            'A real PNG is now rejected. The SEC-003 fix has been tightened too far — '
            .'members cannot change their avatar at all.'
        );

        $stored = (string) DB::table('userprofiles')->where('user_id', $actor->id)->value('avatar');
        $this->assertStringStartsWith('uploads/avatars/', $stored);
        $this->assertSame('png', pathinfo($stored, PATHINFO_EXTENSION));
        Storage::disk('public')->assertExists($stored);
    }

    /**
     * DOCUMENTS that the stored extension is derived from the DETECTED MIME TYPE, never
     * from the client filename — the fact that made SEC-003 stored-XSS rather than RCE.
     *
     * `Storage::putFile()` names via `hashName()`, which appends `guessExtension()`.
     * Real PHP source is `text/x-php`, which has no extension mapping, so it would have
     * been stored with NO extension and could not have matched a `\.php$` handler. This
     * is asserted at the storage layer rather than through the endpoint because the
     * endpoint now rejects the file before it gets there — the property is what bounded
     * the severity, so it is worth pinning independently of the fix that supersedes it.
     */
    public function test_documents_stored_extension_comes_from_detected_mime_not_filename(): void
    {
        Storage::fake('public');

        $phpAsJpg = Storage::disk('public')->putFile(
            'probe', $this->realUpload('shell.jpg', "<?php echo 'x';")
        );
        $this->assertSame(
            '',
            pathinfo($phpAsJpg, PATHINFO_EXTENSION),
            'PHP source uploaded as shell.jpg now stores WITH an extension. If that '
            .'extension is ever "php", SEC-003 becomes an RCE rather than stored XSS — '
            .'re-rate it immediately.'
        );

        $svg = Storage::disk('public')->putFile(
            'probe', $this->realUpload('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')
        );
        $this->assertSame(
            'svg',
            pathinfo($svg, PATHINFO_EXTENSION),
            'SVG no longer stores as .svg. This was the real SEC-003 vector — served from '
            .'/storage/ on the application origin — so confirm what changed.'
        );
    }

    /**
     * DOCUMENTS that no media endpoint serves file BYTES, so there is no place an
     * authorization check on media could even be applied today.
     *
     * The endpoints named "getPhoto" sound like file servers and are not:
     * EventGalleryController::getPhoto() and PhotosController::getPhoto() both return
     * Eloquent collections — database rows carrying PATHS. The browser then fetches
     * the image from /storage/... directly, entirely outside PHP.
     *
     * This is the structural reason the suite has no "unauthenticated user cannot
     * fetch another member's photo" test: there is no application code in that
     * request path to test. FR-11's private-media work must CREATE the checkpoint,
     * not tighten one.
     */
    public function test_documents_photo_endpoints_return_database_rows_not_files(): void
    {
        $fixture = $this->galleryFixture();
        $admin = $this->actorWithPermissions($fixture['church_id'], ['read-events']);

        $response = $this->actingAs($admin)->get('/admin/getphoto/'.$fixture['event_id']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotInstanceOf(
            \Symfony\Component\HttpFoundation\BinaryFileResponse::class,
            $response->baseResponse,
            'A photo endpoint now streams a FILE rather than returning rows. That is '
            .'where an authorization check becomes possible — check whether one was '
            .'added, and re-baseline this suite if so.'
        );
        $this->assertIsArray(
            $response->json(),
            'The gallery photo endpoint no longer returns JSON rows.'
        );
    }

    // ---- helpers --------------------------------------------------------------

    /**
     * Supply the runtime settings the admin layout needs. Copied from
     * MemberProfileCharacterizationTest — see that file's docblock for why this is
     * config() and not fixture rows.
     */
    private function seedRuntimeSettings(): void
    {
        config([
            'settings.favicon' => 'favicon.ico',
            'settings.logo' => 'logo.png',
            'settings.site_title' => 'Characterization Church',
            'settings.sitetitle' => 'Characterization Church',
            'settings.sitename' => 'Characterization Church',
        ]);
    }

    /**
     * A REAL UploadedFile built from real bytes, so MIME detection sees actual content.
     *
     * `UploadedFile::fake()` derives its MIME from the FILENAME, which makes it useless —
     * and actively misleading — for any assertion about file-type handling. See the class
     * docblock: that shortcut produced a wrong RCE rating on 2026-08-21.
     */
    private function realUpload(string $filename, string $bytes): UploadedFile
    {
        $dir = sys_get_temp_dir().'/ifgf-upload-'.uniqid();
        mkdir($dir);
        $path = $dir.'/'.$filename;
        file_put_contents($path, $bytes);

        // test: true — behave as a real upload without requiring an actual POST.
        return new UploadedFile($path, $filename, null, null, true);
    }

    private function makeChurch(string $name): int
    {
        foreach ([1, 3, 4, 5] as $group) {
            if (! DB::table('user_group')->where('id', $group)->exists()) {
                DB::table('user_group')->insert(['id' => $group, 'name' => 'Characterization group '.$group]);
            }
        }

        return (int) DB::table('church')->insertGetId([
            'name' => $name,
            'address' => 'Taipei',
            'pincode' => '106',
            'slug' => 'media-'.uniqid(),
        ]);
    }

    private function makeUser(int $churchId, int $usergroupId): int
    {
        return (int) DB::table('users')->insertGetId([
            'church_id' => $churchId,
            'usergroup_id' => $usergroupId,
            'name' => 'media-'.uniqid(),
            'email' => 'media-'.uniqid().'@example.test',
            'mobile_no' => '0900000000',
            'password' => bcrypt('media-characterization'),
        ]);
    }

    /** An admin-group account that owns a profile row, so the avatar path resolves. */
    private function memberActor(): \App\Models\User
    {
        $churchId = $this->makeChurch('Media Church');
        $userId = $this->makeUser($churchId, self::ADMIN_USERGROUP_ID);

        DB::table('userprofiles')->insert([
            'church_id' => $churchId,
            'user_id' => $userId,
            'firstname' => 'Media',
            'lastname' => 'Member',
            'gender' => 'male',
        ]);

        return \App\Models\User::findOrFail($userId);
    }

    private function actorWithPermissions(int $churchId, array $permissions): \App\Models\User
    {
        $userId = $this->makeUser($churchId, self::ADMIN_USERGROUP_ID);

        foreach ($permissions as $permission) {
            $permissionId = DB::table('permissions')->where('name', $permission)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'name' => $permission,
                    'display_name' => $permission,
                ]);

            DB::table('permission_user')->insert([
                'permission_id' => $permissionId,
                'user_id' => $userId,
                'user_type' => \App\Models\User::class,
            ]);
        }

        // Resolve AFTER the grants — Laratrust caches permissions on first check.
        return \App\Models\User::findOrFail($userId);
    }

    private function galleryFixture(): array
    {
        $churchId = $this->makeChurch('Gallery Church');

        $eventId = (int) DB::table('events')->insertGetId([
            'church_id' => $churchId,
            'title' => 'Gallery Service',
            'enable_attendance' => 0,
        ]);

        return ['church_id' => $churchId, 'event_id' => $eventId];
    }
}
