<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Admin;

use App\Enums\EducationLevel;
use App\Enums\MemberStatus;
use App\Enums\TeamRole;
use App\Enums\TeamType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SearchMembersRequest;
use App\Http\Requests\Admin\UpdateMemberRequest;
use App\Http\Requests\RegisterMemberRequest;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Team;
use App\Services\MemberEditor;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Member management for IT admins. Every route here is behind `auth` and the `admin` Gate.
 * Members are addressed by public_ref, never by their row number.
 */
class MemberController extends Controller
{
    /** The search lives in the session, so a typed name never appears in a URL or a log. */
    private const FILTER = 'admin.members.filter';

    private const FIXED_OCCUPATIONS = ['worker', 'student', 'homemaker'];

    public function index(Request $request): View
    {
        $filter = $request->session()->get(self::FILTER, []) + ['q' => '', 'status' => '', 'branch' => '', 'icare' => ''];
        $like = '%'.addcslashes($filter['q'], '%_\\').'%';

        $members = Member::query()
            ->with(['branch', 'icareTeam'])
            ->when($filter['q'] !== '', fn ($query) => $query->where(fn ($where) => $where
                ->where('full_name', 'ilike', $like)
                ->orWhere('chinese_name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)))
            ->when($filter['status'] !== '', fn ($query) => $query->where('status', $filter['status']))
            ->when($filter['branch'] !== '', fn ($query) => $query->whereRelation('branch', 'code', $filter['branch']))
            ->when($filter['icare'] === RegisterMemberRequest::NO_ICARE, fn ($query) => $query->whereNull('icare_team_id'))
            ->when(ctype_digit((string) $filter['icare']), fn ($query) => $query->where('icare_team_id', (int) $filter['icare']))
            ->orderBy('full_name')
            ->orderBy('id')
            ->paginate(25);

        return view('admin.members.index', [
            'members' => $members,
            'filter' => $filter,
            'filtered' => array_filter($filter) !== [],
            'statuses' => MemberStatus::cases(),
            'branches' => Branch::orderBy('id')->get(),
            'teams' => Team::where('type', TeamType::Icare)->orderBy('name')->get(),
            'noIcare' => RegisterMemberRequest::NO_ICARE,
        ]);
    }

    public function search(SearchMembersRequest $request): RedirectResponse
    {
        $request->session()->put(self::FILTER, array_map(fn ($value) => trim((string) $value), $request->validated()));

        return redirect()->route('admin.members.index');
    }

    public function edit(Member $member): View
    {
        $member->load(['branch', 'icareTeam', 'teams']);
        $typedOccupation = $member->occupation !== null && ! in_array($member->occupation, self::FIXED_OCCUPATIONS, true);

        return view('admin.members.edit', [
            'member' => $member,
            'occupation' => $typedOccupation ? 'other' : $member->occupation,
            'occupationOther' => $typedOccupation ? $member->occupation : '',
            'occupations' => [
                'worker' => 'Pekerja',
                'student' => 'Siswa/Mahasiswa',
                'homemaker' => 'Ibu Rumah Tangga',
                'other' => 'Lainnya',
            ],
            'statuses' => MemberStatus::cases(),
            'branches' => Branch::orderBy('id')->get(),
            'educationLevels' => EducationLevel::cases(),
            'icareTeams' => Team::where('type', TeamType::Icare)->orderBy('name')->get(),
            'allTeams' => Team::where('is_active', true)->orderBy('type')->orderBy('name')->get(),
            'roles' => TeamRole::cases(),
            'noIcare' => RegisterMemberRequest::NO_ICARE,
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member, MemberEditor $editor): RedirectResponse
    {
        $data = $request->validated();
        $before = $member->status;

        $member->update([...$editor->attributes($data), 'status' => $data['status']]);

        if ($member->status !== $before) {
            Audit::record('member.status', $member, ['from' => $before->value, 'to' => $member->status->value]);
        }

        return redirect()->route('admin.members.edit', $member)->with('saved', true);
    }
}
