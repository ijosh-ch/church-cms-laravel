<?php

namespace Tests\Feature\Group;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Characterization suite 6 — groups and GroupLink.
 * WP 0A item 6 / gate 5. TESTING_PLAN.md Part 1, suite 6.
 *
 * CAPTURES WHAT IS. Tests named test_documents_defect_* assert the WRONG behaviour on
 * purpose. Green there means "this has not changed", never "this is correct".
 *
 * ══ WHY THIS SUITE MATTERS MORE THAN ITS POSITION SUGGESTS ══
 *
 * `GroupLink` is the surface iCare attendance will extend. Everything pinned here is a
 * constraint on that design, not merely a bug list.
 *
 * ══ THE ROUTE FILE THAT WINS — MEASURED, AND IT INVERTS THE PERMISSION MODEL ══
 *
 * The group surface is registered TWICE:
 *
 *   routes/web.php:262   prefix admin, ['permission:read-groups'], per-route
 *                        create-groups / update-groups / delete-groups. NO auth gate.
 *   routes/admin.php:685 ['web','auth','churchadmin'] + permission:read-groups on
 *                        EVERY route, with no finer grant anywhere.
 *
 * RouteServiceProvider::map() calls mapWebRoutes() and THEN mapAdminRoutes(), so
 * admin.php registers last and Laravel keeps it. Confirmed with route:list: every
 * /admin/group* route resolves to the admin.php registration.
 *
 * CONSEQUENCE, MEASURED: `read-groups` alone creates, updates, messages and DELETES.
 * The granular create-/update-/delete-groups permissions in web.php are DEAD CODE —
 * they are on routes that never resolve. An owner reading web.php would conclude the
 * system has a four-level group permission model. It has one level.
 *
 * The one URI that does NOT collide is `GET /admin/group/showMember` (no {id}), so the
 * web.php registration survives there — on a surface with no auth gate at all. See
 * test_documents_defect_bare_show_member_route_survives_without_an_auth_gate.
 *
 * ══ Gate::before BYPASSES CHURCH ISOLATION FOR usergroup_id == 3 ══
 *
 * AuthServiceProvider::boot() registers `Gate::before(fn ($user) => $user->usergroup_id
 * == 3 ? true : null)`. `Gate::allows('group', $group)` is the ONLY church scope on
 * show/edit/destroy, so usergroup 3 reads, edits and deletes ANOTHER CHURCH's groups.
 * This is SEC-001 reaching the Gate layer, not just AdminOrPermission — the recorded
 * SEC-001 note describes the middleware alias only. MEASURED, both branches.
 *
 * ══ MEASURED AUTHORIZATION MATRIX, 2026-08-21 ══
 *
 *   route                        guest  ug1        ug4/none  ug4/read-groups  ug3/none
 *   GET /admin/groups             401   302 portal   401         200            200
 *   GET /admin/group/create       401   302 portal   401         200            200
 *   GET /admin/group/show/{own}   401   302 portal   401         200            200
 *   GET /admin/group/show/{other} 401   302 portal   401         403            200  <-- bypass
 *   GET /admin/group/showMember   401   401          401         401            500
 *
 * usergroup 5 is a THIRD shape on this surface: it clears auth and is then refused by
 * MustBeChurchAdmin with abort(403), so 403 here means "authenticated but not an
 * admin", never "guest".
 *
 * ⚠ A HARNESS TRAP THAT PRODUCED A WRONG MATRIX ONCE — READ BEFORE WRITING A DIAGNOSTIC
 *
 * `$this->actingAs($user)` PERSISTS FOR THE REST OF THE TEST METHOD. A diagnostic that
 * loops over actors and routes, using a bare `$this->get()` for the guest row, measures
 * a GUEST only until the first actingAs() call; every later "guest" cell is really the
 * previous actor. That produced a matrix showing guest = 403 on every route except the
 * first, and an invented finding about a non-uniform guest column on byte-identical
 * middleware. The application was uniform all along; the harness was not.
 *
 * This is the fifth time this project has asserted against the harness rather than the
 * application (after UploadedFile::fake() inflating SEC-003 to a false RCE rating).
 * RULE: in a multi-actor diagnostic, either drive the guest row in its own test method
 * or reset the guard explicitly between probes.
 */
class GroupCharacterizationTest extends TestCase
{
    use DatabaseTransactions;

    /** Clears the churchadmin gate; not AdminOrPermission's bypass value. */
    private const ADMIN_USERGROUP_ID = 4;

    /** AdminOrPermission's AND Gate::before's bypass value. SEC-001. */
    private const BYPASS_USERGROUP_ID = 3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRuntimeSettings();
    }

    // ───────────────────────── authorization surface ─────────────────────────

    /**
     * The denial shapes on the group surface, pinned side by side because they are NOT
     * uniform and a test written from the recorded /admin/* model would fail here.
     */
    public function test_group_surface_denial_shapes_by_actor(): void
    {
        $f = $this->fixture();
        $url = '/admin/group/show/'.$f['groupA'];

        // Guest is 401 UNIFORMLY across this surface. Assert it on two routes so a
        // future harness bug of the kind described in the docblock cannot hide behind
        // a single sample.
        $this->assertSame(401, $this->get($url)->getStatusCode(),
            'Guest on an admin.php group route: Authenticate throws, handler answers 401.');
        $this->assertSame(401, $this->get('/admin/groups')->getStatusCode());

        $this->assertSame(403,
            $this->actingAs($this->user($f['churchA'], 5))->get($url)->getStatusCode(),
            'A usergroup-5 member clears auth and is then refused by MustBeChurchAdmin '
            .'with abort(403) — a DIFFERENT denial from the guest 401.');

        $ug1 = $this->actingAs($this->user($f['churchA'], 1))->get($url);
        $this->assertSame(302, $ug1->getStatusCode());
        $this->assertStringEndsWith('/portal', (string) $ug1->headers->get('Location'),
            'usergroup 1 is redirected by MustBeChurchAdmin, not denied.');

        $this->assertSame(401,
            $this->actingAs($this->user($f['churchA'], self::ADMIN_USERGROUP_ID))->get($url)->getStatusCode(),
            'Sub-admin holding no permission: AdminOrPermission aborts 401, not 403.');
    }

    /**
     * DOCUMENTS THAT THE GRANULAR GROUP PERMISSIONS DO NOT EXIST AT RUNTIME.
     *
     * `read-groups` is the only grant held, yet it reaches the create form, the edit
     * form, the member-add form and the messaging surface. routes/web.php gates each of
     * these behind create-groups / update-groups — that registration is shadowed.
     */
    public function test_documents_defect_read_groups_alone_reaches_every_group_route(): void
    {
        $f = $this->fixture();
        $admin = $this->actorWithPermissions($f['churchA'], ['read-groups']);

        foreach ([
            '/admin/groups',
            '/admin/group/create',
            '/admin/group/get',
            '/admin/group/show/'.$f['groupA'],
            '/admin/group/edit/'.$f['groupA'],
            '/admin/group/addMember/'.$f['groupA'],
            '/admin/group/showMember/'.$f['groupA'],
            '/admin/group/messages/'.$f['groupA'],
            '/admin/group/editMember/'.$f['linkA'],
        ] as $url) {
            $this->assertSame(200, $this->actingAs($admin)->get($url)->getStatusCode(),
                "GET {$url} should be reachable with read-groups alone. If this is now a "
                .'denial, the web.php registration has started winning — read the class '
                .'docblock before changing the assertion.');
        }
    }

    /**
     * DOCUMENTS that read-groups alone DELETES a group, with no delete-groups grant.
     */
    public function test_documents_defect_read_groups_alone_deletes_a_group(): void
    {
        $f = $this->fixture();
        $admin = $this->actorWithPermissions($f['churchA'], ['read-groups']);

        $response = $this->actingAs($admin)->delete('/admin/group/delete/'.$f['groupA']);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNotNull(DB::table('groups')->where('id', $f['groupA'])->value('deleted_at'),
            'The group was soft-deleted by an actor holding only read-groups.');
    }

    // ───────────────────────── church isolation ─────────────────────────

    /**
     * The control that DOES work: a scoped sub-admin is denied a foreign church's group.
     * Asserted so a refactor cannot remove the one isolation control the surface has.
     */
    public function test_foreign_church_group_is_denied_to_a_scoped_admin(): void
    {
        $f = $this->fixture();
        $admin = $this->actorWithPermissions($f['churchA'], ['read-groups']);

        $this->assertSame(403,
            $this->actingAs($admin)->get('/admin/group/show/'.$f['groupB'])->getStatusCode());
        $this->assertSame(403,
            $this->actingAs($admin)->get('/admin/group/edit/'.$f['groupB'])->getStatusCode());
    }

    /**
     * 🔴 DOCUMENTS THAT usergroup_id == 3 READS AND EDITS ANOTHER CHURCH'S GROUPS.
     *
     * SEC-001 is recorded as a permission-middleware bypass (Kernel.php:74). It is also
     * a Gate::before bypass, and on this surface that is the more serious half: the Gate
     * is the ONLY church scope GroupsController::show/edit/destroy have. The actor below
     * holds NO permission and belongs to a DIFFERENT church.
     *
     * Replace with the denial when FR-11 maps legacy groups. DO NOT DELETE.
     */
    public function test_documents_defect_usergroup_three_reaches_foreign_church_groups(): void
    {
        $f = $this->fixture();
        $bypass = $this->user($f['churchA'], self::BYPASS_USERGROUP_ID);

        $this->assertSame(200,
            $this->actingAs($bypass)->get('/admin/group/show/'.$f['groupB'])->getStatusCode(),
            'usergroup 3 reads a foreign church\'s group. Gate::before short-circuits the '
            .'church comparison in Gate::define(\'group\').');
        $this->assertSame(200,
            $this->actingAs($bypass)->get('/admin/group/edit/'.$f['groupB'])->getStatusCode());
    }

    // ───────────────────────── GroupLink write path ─────────────────────────

    /**
     * 🔴 DOCUMENTS THAT GroupLinksController::store() TRUSTS THE CLIENT'S church_id.
     *
     * `$churchId = $request->church_id;` — not Auth::user()->church_id. The membership
     * row is written under whatever church id the form posts. Every other write on this
     * surface derives it from the session.
     *
     * THIS IS THE CONSTRAINT THAT MATTERS FOR iCARE: a GroupLink's church_id is not
     * trustworthy provenance today. Anything keying attendance scope off it inherits
     * that.
     */
    public function test_documents_defect_add_member_trusts_client_supplied_church_id(): void
    {
        $f = $this->fixture();
        $admin = $this->actorWithPermissions($f['churchA'], ['read-groups']);

        $response = $this->actingAs($admin)->post('/admin/group/addMember/'.$f['groupA'], [
            'church_id' => $f['churchB'],
            'role' => 'member',
            'user_ids' => [$f['memberA']],
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $stored = DB::table('group_links')
            ->where('group_id', $f['groupA'])->where('user_id', $f['memberA'])
            ->orderByDesc('id')->value('church_id');

        $this->assertSame($f['churchB'], (int) $stored,
            'group_links.church_id came from the request body, not from the actor.');
        $this->assertNotSame($f['churchA'], (int) $stored);
    }

    /**
     * 🔴 DOCUMENTS THAT store() HAS NO Gate CHECK — it writes into a FOREIGN group.
     *
     * Every sibling action (index, create, edit, destroy) calls Gate::allows('group', …).
     * store() does not. A church-A admin adds a member to a church-B group.
     */
    public function test_documents_defect_add_member_writes_into_a_foreign_church_group(): void
    {
        $f = $this->fixture();
        $admin = $this->actorWithPermissions($f['churchA'], ['read-groups']);

        $response = $this->actingAs($admin)->post('/admin/group/addMember/'.$f['groupB'], [
            'church_id' => $f['churchA'],
            'role' => 'member',
            'user_ids' => [$f['memberA']],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, DB::table('group_links')
            ->where('group_id', $f['groupB'])->where('user_id', $f['memberA'])->count(),
            'A membership row was created on another church\'s group.');
    }

    /**
     * 🔴 DOCUMENTS THAT DUPLICATE GROUP MEMBERSHIP IS REACHABLE.
     *
     * The duplicate guard is controller logic keyed on the CLIENT-SUPPLIED church_id, so
     * posting two different church ids for the same (group, user) writes two rows. There
     * is NO unique constraint on group_links to catch it —
     * see test_documents_group_links_has_no_unique_constraint.
     *
     * CONTRAST, and this is the design input: `event_attendees` carries a DATABASE
     * UNIQUE (session_id, user_id). Attendance was given the constraint; membership was
     * not. FR-04's recorder must not assume GroupLink is single-valued.
     */
    public function test_documents_defect_duplicate_group_membership_is_reachable(): void
    {
        $f = $this->fixture();
        $admin = $this->actorWithPermissions($f['churchA'], ['read-groups']);
        $payload = fn (int $churchId) => [
            'church_id' => $churchId, 'role' => 'member', 'user_ids' => [$f['memberA']],
        ];

        $this->actingAs($admin)->post('/admin/group/addMember/'.$f['groupA'], $payload($f['churchB']));
        $this->actingAs($admin)->post('/admin/group/addMember/'.$f['groupA'], $payload($f['churchA']));

        $this->assertSame(2, DB::table('group_links')
            ->where('group_id', $f['groupA'])->where('user_id', $f['memberA'])->count(),
            'Two membership rows exist for one (group, user) pair.');

        // The guard DOES hold when the church_id is repeated unchanged.
        $repeat = $this->actingAs($admin)
            ->post('/admin/group/addMember/'.$f['groupA'], $payload($f['churchA']));
        $this->assertStringContainsString('already in group', $repeat->getContent());
    }

    /**
     * DOCUMENTS that group_links has no unique constraint on (group_id, user_id).
     * Structural counterpart to the test above; fails loudly if WP 0C adds one without
     * deduping first.
     */
    public function test_documents_group_links_has_no_unique_constraint(): void
    {
        $indexes = collect(DB::select('SHOW INDEXES FROM group_links'))
            ->filter(fn ($i) => (int) $i->Non_unique === 0)
            ->pluck('Key_name')->unique()->values()->all();

        $this->assertSame(['PRIMARY'], $indexes,
            'group_links carries only a primary key. Compare event_attendees, which has '
            .'UNIQUE (session_id, user_id).');
    }

    /**
     * 🔴 DOCUMENTS THAT A ROLE CHANGE HAS NO GATE AND NO CHURCH SCOPE.
     *
     * GroupLinksController::update() does `GroupLink::where('id', $id)->first()` and
     * saves. No Gate::allows, no church comparison. A church-A admin promotes a church-B
     * member to group_admin.
     */
    public function test_documents_defect_member_role_change_crosses_churches(): void
    {
        $f = $this->fixture();
        $admin = $this->actorWithPermissions($f['churchA'], ['read-groups']);

        $response = $this->actingAs($admin)
            ->post('/admin/group/editMember/'.$f['linkB'], ['role' => 'group_admin']);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('group_admin', DB::table('group_links')->where('id', $f['linkB'])->value('role'),
            'A foreign church\'s group membership was promoted to group_admin.');
    }

    // ───────────────────────── destructive side effects ─────────────────────────

    /**
     * 🔴🔴 DOCUMENTS THAT DELETING A GROUP REVOKES EVERY MEMBER'S PERMISSIONS.
     *
     * GroupsController::destroy() walks the group's members and, for each one, deletes
     * EVERY PermissionUser row that member holds:
     *
     *     $permissions = PermissionUser::where('user_id', $groupMember->user_id)->get();
     *     foreach ($permissions as $permission) { $permission->delete(); }
     *
     * The query is not scoped to the group, to the church, or to anything group-related.
     * The member below holds `read-members`, which has NOTHING to do with the group; it
     * is gone after the delete. Deleting a youth group silently strips permissions from
     * every member of it, with no warning, no confirmation and no record of what was
     * removed — the activity log records only "Group Deleted Successfully".
     *
     * This is a privilege-destruction side effect, not a cascade: the rows are not
     * recoverable from the group's own soft delete, because PermissionUser rows are
     * hard-deleted while the group is soft-deleted. Restoring the group does NOT restore
     * the permissions.
     */
    public function test_documents_defect_deleting_a_group_revokes_every_members_permissions(): void
    {
        $f = $this->fixture();
        $admin = $this->actorWithPermissions($f['churchA'], ['read-groups']);

        $this->grant($f['memberA'], 'read-members');
        $this->assertSame(1, DB::table('permission_user')->where('user_id', $f['memberA'])->count(),
            'Precondition: the group member holds one unrelated permission.');

        $this->actingAs($admin)->delete('/admin/group/delete/'.$f['groupA']);

        $this->assertSame(0, DB::table('permission_user')->where('user_id', $f['memberA'])->count(),
            'The member\'s unrelated read-members grant was destroyed by a group delete.');
        $this->assertNotNull(DB::table('groups')->where('id', $f['groupA'])->value('deleted_at'),
            'The group is only SOFT deleted, while the permissions are gone for good.');
    }

    // ───────────────────────── inconsistent failure shapes ─────────────────────────

    /**
     * DOCUMENTS that updating a foreign group is a 500, while VIEWING one is a 403.
     *
     * GroupsController::update() has no Gate. It scopes the lookup by church_id instead,
     * so a foreign id yields null and `$group->church_id = …` raises an \Error. \Error
     * does not extend \Exception, so the controller's own catch(Exception) does not
     * catch it. The isolation holds — nothing is written — but it holds by crashing.
     */
    public function test_documents_defect_updating_a_foreign_group_is_500_not_403(): void
    {
        $f = $this->fixture();
        $admin = $this->actorWithPermissions($f['churchA'], ['read-groups']);

        $response = $this->actingAs($admin)->post('/admin/group/update/'.$f['groupB'], [
            'category' => $f['category'], 'group_type' => 'youth',
            'name' => 'Hijacked', 'description' => 'x',
        ]);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('Group B', DB::table('groups')->where('id', $f['groupB'])->value('name'),
            'The write did not land — isolation holds, but by fataling rather than denying.');
    }

    /**
     * DOCUMENTS the one group URI where the web.php registration survives.
     *
     * `GET /admin/group/showMember` (no {id}) has no counterpart in admin.php, so the
     * web.php route lives — carrying ['permission:read-groups','permission:update-groups']
     * and NO auth gate. Every actor is denied 401 by the permission stack; the only actor
     * that gets through is the SEC-001 bypass, and it then fails inside the controller,
     * because GroupLinksController::index($id) requires an argument this route does not
     * supply. Unreachable for everyone, broken for the one who reaches it.
     */
    public function test_documents_defect_bare_show_member_route_survives_without_an_auth_gate(): void
    {
        $f = $this->fixture();

        $this->assertSame(401, $this->get('/admin/group/showMember')->getStatusCode(),
            'Guest is refused by the permission gate, NOT by an auth gate — there is none.');
        $this->assertSame(401, $this->actingAs($this->actorWithPermissions($f['churchA'], ['read-groups']))
            ->get('/admin/group/showMember')->getStatusCode());

        $this->assertSame(500, $this->actingAs($this->user($f['churchA'], self::BYPASS_USERGROUP_ID))
            ->get('/admin/group/showMember')->getStatusCode(),
            'The SEC-001 bypass reaches the controller and the controller cannot run.');
    }

    /**
     * DOCUMENTS that /admin/group/showMember/{id} returns a CANDIDATE list, not the
     * group's members — active members of the church in usergroup 5, ignoring $id
     * except for the Gate check. The name is misleading and the shape is worth pinning
     * before anything consumes it.
     */
    public function test_show_member_returns_church_candidates_not_group_members(): void
    {
        $f = $this->fixture();
        $admin = $this->actorWithPermissions($f['churchA'], ['read-groups']);

        $payload = $this->actingAs($admin)
            ->get('/admin/group/showMember/'.$f['groupA'])->json();

        $this->assertArrayHasKey('memberlist', $payload);
        $ids = collect($payload['memberlist'])->pluck('id')->all();
        $this->assertContains($f['memberA'], $ids,
            'The church\'s active usergroup-5 member is listed.');
        $this->assertNotContains($f['memberB'], $ids,
            'A member of another church is not listed — this query IS church scoped.');
    }

    // ───────────────────────── validation ─────────────────────────

    /**
     * DOCUMENTS that group creation requires a cover image, and that a rejected create
     * writes nothing. GroupAddRequest marks cover_image `required|mimes:...`.
     *
     * Also worth knowing: the custom `check_name` / `check_description` rules are
     * `preg_match('/\pL\pM*|./u', …)`, which matches any non-empty string. They are
     * validators that validate nothing. Not asserted — recorded so nobody trusts them.
     */
    public function test_group_create_requires_a_cover_image(): void
    {
        $f = $this->fixture();
        $admin = $this->actorWithPermissions($f['churchA'], ['read-groups']);
        $before = DB::table('groups')->count();

        $response = $this->actingAs($admin)->post('/admin/group/create', [
            'category' => $f['category'], 'group_type' => 'youth',
            'name' => 'No Cover', 'description' => 'x',
        ]);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($before, DB::table('groups')->count());
    }

    // ───────────────────────── fixtures ─────────────────────────

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

    private function user(int $churchId, int $usergroupId): \App\Models\User
    {
        return \App\Models\User::findOrFail($this->makeUser($churchId, $usergroupId));
    }

    private function makeUser(int $churchId, int $usergroupId): int
    {
        return (int) DB::table('users')->insertGetId([
            'church_id' => $churchId,
            'usergroup_id' => $usergroupId,
            'name' => 'group-'.uniqid(),
            'email' => 'group-'.uniqid().'@example.test',
            'mobile_no' => '0900000000',
            'password' => bcrypt('group-characterization'),
        ]);
    }

    private function grant(int $userId, string $permission): void
    {
        $permissionId = DB::table('permissions')->where('name', $permission)->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => $permission, 'display_name' => $permission,
            ]);

        DB::table('permission_user')->insert([
            'permission_id' => $permissionId,
            'user_id' => $userId,
            'user_type' => \App\Models\User::class,
        ]);
    }

    private function actorWithPermissions(int $churchId, array $permissions): \App\Models\User
    {
        $userId = $this->makeUser($churchId, self::ADMIN_USERGROUP_ID);

        foreach ($permissions as $permission) {
            $this->grant($userId, $permission);
        }

        // Resolve AFTER the grants — Laratrust caches on first check.
        return \App\Models\User::findOrFail($userId);
    }

    /** Two churches, each with one group, one usergroup-5 member and one membership. */
    private function fixture(): array
    {
        foreach ([1, 3, 4, 5] as $g) {
            if (! DB::table('user_group')->where('id', $g)->exists()) {
                DB::table('user_group')->insert(['id' => $g, 'name' => 'Characterization group '.$g]);
            }
        }

        $churchA = (int) DB::table('church')->insertGetId([
            'name' => 'Group Church A', 'address' => 'Taipei', 'pincode' => '106',
            'slug' => 'group-a-'.uniqid(),
        ]);
        $churchB = (int) DB::table('church')->insertGetId([
            'name' => 'Group Church B', 'address' => 'Taipei', 'pincode' => '106',
            'slug' => 'group-b-'.uniqid(),
        ]);

        $category = (int) DB::table('group_category')->insertGetId([
            'category' => 'Ministry', 'name' => 'Ministry', 'status' => 'active',
        ]);

        $memberA = $this->makeUser($churchA, 5);
        $memberB = $this->makeUser($churchB, 5);

        foreach ([[$churchA, $memberA], [$churchB, $memberB]] as [$churchId, $userId]) {
            DB::table('userprofiles')->insert([
                'church_id' => $churchId, 'user_id' => $userId,
                'firstname' => 'Group', 'lastname' => 'Member', 'gender' => 'male',
                'membership_type' => 'member', 'status' => 'active',
            ]);
        }

        $groupA = (int) DB::table('groups')->insertGetId([
            'church_id' => $churchA, 'category_id' => $category, 'name' => 'Group A',
            'description' => 'A', 'group_type' => 'youth', 'created_by' => $memberA,
        ]);
        $groupB = (int) DB::table('groups')->insertGetId([
            'church_id' => $churchB, 'category_id' => $category, 'name' => 'Group B',
            'description' => 'B', 'group_type' => 'youth', 'created_by' => $memberB,
        ]);

        $linkA = (int) DB::table('group_links')->insertGetId([
            'church_id' => $churchA, 'user_id' => $memberA, 'group_id' => $groupA, 'role' => 'member',
        ]);
        $linkB = (int) DB::table('group_links')->insertGetId([
            'church_id' => $churchB, 'user_id' => $memberB, 'group_id' => $groupB, 'role' => 'member',
        ]);

        return compact('churchA', 'churchB', 'category', 'memberA', 'memberB',
            'groupA', 'groupB', 'linkA', 'linkB');
    }
}
