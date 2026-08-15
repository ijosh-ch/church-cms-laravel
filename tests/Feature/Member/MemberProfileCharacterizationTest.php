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
 * CAPTURES WHAT IS. Some tests here document defects by asserting the wrong
 * behaviour; they are named test_documents_defect_*. A green run on those means
 * "this has not changed", never "this is correct".
 *
 * ══ READ THIS BEFORE ADDING ANY TEST THAT RENDERS AN ADMIN VIEW ══
 *
 * The admin layout needs runtime `settings.*` config, and A TEST DATABASE DOES NOT
 * HAVE IT. AppServiceProvider::boot() populates `settings.*` from the `church_details`
 * table for `Church::first()`. On a disposable test database that table is empty, so
 * `config('settings.favicon')` is NULL — and `resources/views/layouts/admin/layout
 * .blade.php` line 6 does:
 *
 *     {{ url(\Config::get('settings.favicon')) }}
 *
 * `url(null)` returns the UrlGenerator INSTANCE (documented Laravel behaviour, not a
 * version change), which Blade's e() then rejects:
 *
 *     htmlspecialchars(): Argument #1 ($string) must be of type string,
 *     Illuminate\Routing\UrlGenerator given
 *
 * EVERY admin page using this layout 500s without that config. This is a FIXTURE
 * requirement, NOT an application defect. seedRuntimeSettings() below supplies it.
 *
 * This was originally recorded as "MEM-001 — the member admin UI is broken on Laravel
 * 13", a suspected upgrade regression. IT WAS WRONG, and the finding is withdrawn.
 * With settings seeded these routes return 200. The lesson, which cost a commit:
 * A 500 IN A TEST ENVIRONMENT IS A FIXTURE QUESTION UNTIL PROVEN OTHERWISE. Read the
 * actual failing line before attributing a failure to the framework.
 *
 * The config is set directly rather than by inserting church_details rows because the
 * provider runs at BOOT, before a test's fixture data exists — inserting rows mid-test
 * would not be read.
 *
 * MEASURED 2026-08-15 with settings seeded:
 *   GET /admin/members              200
 *   GET /admin/member/add           200
 *   GET /admin/members/find         200 (JSON)
 *   GET /admin/member/edit/{name}   200
 *   GET /admin/member/show/{name}   500 — imagick, REAL and separate, owned by UP-008
 */
class MemberProfileCharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    /** Clears the churchadmin gate; not AdminOrPermission's bypass value. */
    private const ADMIN_USERGROUP_ID = 4;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRuntimeSettings();
    }

    public function test_member_list_and_add_and_edit_render(): void
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
            $this->assertSame(
                200,
                $this->actingAs($admin)->get($url)->getStatusCode(),
                "GET {$url} no longer renders. If this is 500, check whether the failure "
                .'is the layout\'s settings.* config (a FIXTURE problem — see the class '
                .'docblock) before attributing it to application code.'
            );
        }
    }

    public function test_member_search_endpoint_responds(): void
    {
        $fixture = $this->fixture();
        $admin = $this->actorWithPermissions($fixture['church_id'], ['read-members']);

        $this->assertSame(
            200,
            $this->actingAs($admin)->get('/admin/members/find')->getStatusCode()
        );
    }

    /**
     * DOCUMENTS a real defect — the member show route needs the imagick extension.
     *
     * Survives the settings fixture, so unlike the withdrawn MEM-001 this one is
     * genuine. Already owned: decision 2026-08-10 #1, UP-008 reserved for the
     * format('png') -> format('svg') change across 8 Blade call sites.
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
            'The member show route now fails for a different reason. If UP-008 has '
            .'landed this should render — replace this test with the render assertion '
            .'rather than loosening it.'
        );
    }

    /**
     * DOCUMENTS DEFECT — userprofiles allows MORE THAN ONE ROW PER USER.
     *
     * Architectural invariant 4: "UserProfile has exactly one row per user, enforced
     * by a unique constraint after duplicate cleanup." The constraint DOES NOT EXIST.
     * `userprofiles.user_id` carries a foreign key but the index is Non_unique = 1,
     * and a second row for the same user inserts cleanly.
     *
     * This is WP 0C item 3, confirmed with data rather than by reading the migration.
     * It matters more than it looks: every `$user->userprofile` accessor silently
     * picks ONE row, so a duplicated member can present different names, birthdays or
     * membership types depending on row order. WP 0C must dedupe BEFORE adding the
     * unique key, and which row survives is a pastoral data decision needing the
     * owner, not a technical one.
     */
    public function test_documents_defect_userprofiles_permits_duplicate_rows_per_user(): void
    {
        $index = DB::select("SHOW INDEX FROM userprofiles WHERE Column_name = 'user_id'");
        $this->assertNotEmpty($index, 'userprofiles.user_id has no index at all.');

        $this->assertFalse(
            collect($index)->contains(fn ($i) => (int) $i->Non_unique === 0),
            'userprofiles.user_id now has a UNIQUE index. If WP 0C item 3 has landed, '
            .'replace this with a test asserting the constraint HOLDS — and check what '
            .'the dedupe migration did with users who had several rows, because '
            .'choosing which profile survives is a pastoral data decision.'
        );

        $fixture = $this->fixture();
        DB::table('userprofiles')->insert(
            $this->profileRow($fixture['church_id'], $fixture['member_id'], 'Duplicate')
        );

        $this->assertSame(
            2,
            DB::table('userprofiles')->where('user_id', $fixture['member_id'])->count(),
            'A second userprofile row was rejected, so the invariant is enforced '
            .'somewhere other than the schema. Find out where before relying on it.'
        );
    }

    /**
     * The authorization decision, asserted with a control on both sides.
     */
    public function test_member_routes_require_the_read_members_permission(): void
    {
        $fixture = $this->fixture();

        $withPermission = $this->actorWithPermissions($fixture['church_id'], ['read-members']);
        $this->assertSame(
            200,
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
     * routes/web.php uses /member/show/{name} and /member/edit/{firstname}, and
     * `users.name` has no uniqueness constraint — two members in one church can share
     * one, making those URLs ambiguous: whichever row the query returns first wins.
     * FR-02.7 prohibits sequential IDs in URLs, and the obvious misreading of that
     * ("use the name instead") is what produced this. The correct answer is the opaque
     * token FR-02.7 actually specifies.
     */
    public function test_documents_member_names_are_not_unique_within_a_church(): void
    {
        $fixture = $this->fixture();

        $duplicate = $this->makeUser($fixture['church_id'], 1, $fixture['member_name']);

        $this->assertNotSame($fixture['member_id'], $duplicate);
        $this->assertSame(
            2,
            DB::table('users')
                ->where('church_id', $fixture['church_id'])
                ->where('name', $fixture['member_name'])
                ->count(),
            'users.name is now unique within a church. If that was added deliberately '
            .'the name-keyed routes become safe — confirm and re-baseline.'
        );
    }

    public function test_both_user_and_userprofile_are_soft_deleted(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('userprofiles', 'deleted_at'));
    }

    // ---- fixtures -------------------------------------------------------------

    /**
     * Supply the runtime settings the admin layout needs. See the class docblock —
     * without this every admin view 500s for a reason that has nothing to do with the
     * code under test.
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

        // Resolve the model AFTER the grants. Laratrust caches a user's permissions on
        // first check, so a model loaded before the grant answers from a stale cache.
        return \App\Models\User::findOrFail($userId);
    }

    /** A church with one member holding exactly one profile row. */
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
