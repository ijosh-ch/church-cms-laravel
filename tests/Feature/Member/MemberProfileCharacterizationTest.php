<?php

namespace Tests\Feature\Member;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Characterization suite 3 — member profile.
 * WP 0A item 6 / gate 5. TESTING_PLAN.md Part 1, suite 3.
 *
 * CAPTURES WHAT IS. Most of this file documents defects by asserting the wrong
 * behaviour. A green run means "this has not changed", never "this is correct".
 *
 * THE HEADLINE: THE MEMBER ADMIN UI IS BROKEN ON LARAVEL 13. Every HTML route in
 * this surface returns 500. This is the first concrete evidence of an actual
 * regression from the 10 -> 13 upgrade, and it is exactly what WP 0B item 6
 * (characterization) would have caught had it not been skipped by owner directive
 * mid-session. See MEMORY.md 2026-08-10.
 *
 * MEASURED 2026-08-15, two distinct causes:
 *
 *   GET /admin/members             500  ViewException: htmlspecialchars(): Argument
 *   GET /admin/member/add          500  #1 ($string) must be of type string,
 *   GET /admin/member/edit/{name}  500  Illuminate\Routing\UrlGenerator given
 *
 *   GET /admin/member/show/{name}  500  ViewException: You need to install the
 *                                       imagick extension to use this back end
 *                                       (resources/views/member/idcard/idcard...)
 *
 *   GET /admin/members/find        200  (JSON, empty)
 *
 * The imagick failure is KNOWN and already owned: TODO.md, decision 2026-08-10 #1,
 * UP-008 reserved for the format('png') -> format('svg') change. The
 * UrlGenerator/htmlspecialchars failure is NOT previously recorded and is tracked
 * here as MEM-001.
 *
 * MEM-001 IS NOT PROVEN TO BE AN UPGRADE REGRESSION. It is strongly suggestive of
 * one -- a type error reaching Blade's e() helper is the shape of a framework
 * behaviour change -- but there is no pre-upgrade baseline to diff against, which is
 * the same gap TODO.md records for the 730-vs-812 route count. To settle it, check
 * out 086f33d (pre-upgrade), hit the same route, and compare. Do NOT record it as a
 * regression until that is done.
 *
 * CONSEQUENCE FOR THIS SUITE: render behaviour cannot be characterized while the
 * views throw. What IS characterized here is everything that survives that: the
 * authorization decisions (a 500 proves the request got PAST both gates and the
 * permission middleware), the data-layer invariants, and the failures themselves.
 * When MEM-001 and UP-008 are fixed, extend this file with the render assertions --
 * create, edit, view, search and export per TESTING_PLAN.md suite 3.
 */
class MemberProfileCharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    /** Clears the churchadmin gate; not AdminOrPermission's bypass value. */
    private const ADMIN_USERGROUP_ID = 4;

    /**
     * DOCUMENTS DEFECT — userprofiles allows MORE THAN ONE ROW PER USER.
     *
     * Architectural invariant 4: "UserProfile has exactly one row per user, enforced
     * by a unique constraint after duplicate cleanup." The constraint DOES NOT EXIST.
     * `userprofiles.user_id` carries a foreign key (`userprofiles_user_id_foreign`)
     * but the index is Non_unique = 1, and a second row for the same user inserts
     * cleanly.
     *
     * This is WP 0C item 3 and it is confirmed real, with data rather than by reading
     * the migration. It matters more than it looks: every accessor that does
     * `$user->userprofile` silently picks ONE of the rows, so a duplicated member can
     * show different names, birthdays or membership types depending on row order. The
     * WP 0C expand/backfill/verify/contract sequence must dedupe BEFORE adding the
     * unique key, and "which row wins" is a pastoral data decision, not a technical
     * one -- it needs the owner.
     */
    public function test_documents_defect_userprofiles_permits_duplicate_rows_per_user(): void
    {
        $index = DB::select("SHOW INDEX FROM userprofiles WHERE Column_name = 'user_id'");

        $this->assertNotEmpty($index, 'userprofiles.user_id has no index at all.');

        $hasUnique = collect($index)->contains(fn ($i) => (int) $i->Non_unique === 0);
        $this->assertFalse(
            $hasUnique,
            'userprofiles.user_id now has a UNIQUE index. If WP 0C item 3 has landed, '
            .'this test should be replaced by one asserting the constraint HOLDS -- and '
            .'check what the dedupe migration did with users who had several rows, '
            .'because choosing which profile survives is a pastoral data decision.'
        );

        // Prove it with data, not only with metadata.
        $fixture = $this->fixture();
        DB::table('userprofiles')->insert($this->profileRow($fixture['church_id'], $fixture['member_id'], 'Duplicate'));

        $this->assertSame(
            2,
            DB::table('userprofiles')->where('user_id', $fixture['member_id'])->count(),
            'A second userprofile row for one user was rejected, so the invariant is '
            .'being enforced somewhere other than the schema. Find out where before '
            .'relying on it.'
        );
    }

    /**
     * DOCUMENTS MEM-001 — the member admin HTML surface returns 500 on Laravel 13.
     *
     * Asserted as the current failure rather than as an aspirational 200, so the
     * breakage cannot be silently lost. When it is fixed these assertions go RED,
     * which is the intended signal to come back and write the real render coverage.
     */
    public function test_documents_defect_member_admin_html_routes_return_500(): void
    {
        $fixture = $this->fixture();
        $admin = $this->actorWithPermissions($fixture['church_id'], [
            'read-members', 'create-members', 'update-members',
        ]);

        foreach ([
            '/admin/members',
            '/admin/member/add',
            '/admin/member/edit/'.$fixture['member_name'],
        ] as $url) {
            $response = $this->actingAs($admin)->get($url);

            $this->assertSame(
                500,
                $response->getStatusCode(),
                "GET {$url} no longer returns 500. If MEM-001 is fixed, replace this "
                .'test with real render coverage for suite 3 (create, edit, view, '
                .'search, export) rather than deleting it.'
            );

            $exception = $response->baseResponse->exception;
            $this->assertNotNull($exception, "GET {$url} returned 500 with no exception.");
            $this->assertStringContainsString(
                'htmlspecialchars',
                $exception->getMessage(),
                "GET {$url} still fails, but with a DIFFERENT error than MEM-001. Read "
                .'it -- a new failure is hiding behind the known one.'
            );
        }
    }

    /**
     * DOCUMENTS the imagick failure on the member show route — a SEPARATE cause.
     *
     * Kept distinct from MEM-001 on purpose: they are two different bugs behind the
     * same status code, and fixing one will not clear the other. This one is already
     * owned (decision 2026-08-10 #1, UP-008 reserved, format('png') -> format('svg')).
     */
    public function test_documents_defect_member_show_fails_on_missing_imagick(): void
    {
        $fixture = $this->fixture();
        $admin = $this->actorWithPermissions($fixture['church_id'], ['read-members', 'update-members']);

        $response = $this->actingAs($admin)->get('/admin/member/show/'.$fixture['member_name']);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString(
            'imagick',
            $response->baseResponse->exception->getMessage(),
            'The member show route fails for a reason other than the known missing '
            .'imagick extension. If UP-008 (format svg) has landed this should now be '
            .'MEM-001 or a render -- read the actual error rather than adjusting this.'
        );
    }

    /**
     * The authorization decision, which is measurable even though the views throw.
     *
     * A 500 proves the request cleared BOTH legacy gates AND the permission
     * middleware and reached the controller. A 401 proves it did not. That contrast
     * is the whole assertion, and it survives whatever the view does.
     */
    public function test_member_routes_require_the_read_members_permission(): void
    {
        $fixture = $this->fixture();

        $withPermission = $this->actorWithPermissions($fixture['church_id'], ['read-members']);
        $this->assertNotSame(
            401,
            $this->actingAs($withPermission)->get('/admin/members')->getStatusCode(),
            'A user holding read-members was refused.'
        );

        $withoutPermission = \App\Models\User::findOrFail(
            $this->makeUser($fixture['church_id'], self::ADMIN_USERGROUP_ID)
        );
        $this->assertSame(
            401,
            $this->actingAs($withoutPermission)->get('/admin/members')->getStatusCode(),
            'A user with no permissions reached the member list. Denial is 401 here, '
            .'per config/laratrust.php.'
        );
    }

    /**
     * DOCUMENTS that member lookup routes key on NAME, not on an identifier.
     *
     * routes/web.php uses /member/show/{name} and /member/edit/{firstname}.
     * `users.name` has no uniqueness constraint, so two members in the same church can
     * share one, and these URLs are then ambiguous -- whichever row the query returns
     * first wins. Recorded because FR-02.7 prohibits sequential IDs in URLs, and the
     * obvious reading of that ("use the name instead") is what produced this. The
     * correct answer is the opaque token FR-02.7 actually specifies, not a name.
     */
    public function test_documents_member_names_are_not_unique_within_a_church(): void
    {
        $fixture = $this->fixture();

        $duplicateNameUserId = $this->makeUser(
            $fixture['church_id'],
            1,
            $fixture['member_name']
        );

        $this->assertNotSame($fixture['member_id'], $duplicateNameUserId);
        $this->assertSame(
            2,
            DB::table('users')
                ->where('church_id', $fixture['church_id'])
                ->where('name', $fixture['member_name'])
                ->count(),
            'users.name is now unique within a church. If that constraint was added '
            .'deliberately the name-keyed routes become safe -- confirm and re-baseline.'
        );
    }

    public function test_both_user_and_userprofile_are_soft_deleted(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('userprofiles', 'deleted_at'));
    }

    // ---- fixtures -------------------------------------------------------------

    private function makeUser(int $churchId, int $usergroupId, ?string $name = null): int
    {
        return (int) DB::table('users')->insertGetId([
            'church_id' => $churchId,
            'usergroup_id' => $usergroupId,
            'name' => $name ?? 'member-'.uniqid(),
            'email' => 'member-'.uniqid().'@example.test',
            'mobile_no' => '0900000000',
            'password' => bcrypt('member-characterization'),
        ]);
    }

    private function profileRow(int $churchId, int $userId, string $firstname): array
    {
        return [
            'church_id' => $churchId,
            'user_id' => $userId,
            'firstname' => $firstname,
            'lastname' => 'Characterization',
            'gender' => 'male',
        ];
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

        return \App\Models\User::findOrFail($userId);
    }

    /** A church with one member who has exactly one profile row. */
    private function fixture(): array
    {
        foreach ([1, 3, 4] as $group) {
            if (! DB::table('user_group')->where('id', $group)->exists()) {
                DB::table('user_group')->insert([
                    'id' => $group,
                    'name' => 'Characterization group '.$group,
                ]);
            }
        }

        $churchId = (int) DB::table('church')->insertGetId([
            'name' => 'Member Profile Church',
            'address' => 'Taipei',
            'pincode' => '106',
            'slug' => 'member-profile-'.uniqid(),
        ]);

        $memberName = 'member-'.uniqid();
        $memberId = $this->makeUser($churchId, 1, $memberName);

        DB::table('userprofiles')->insert($this->profileRow($churchId, $memberId, 'Original'));

        return [
            'church_id' => $churchId,
            'member_id' => $memberId,
            'member_name' => $memberName,
        ];
    }
}
