<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Member;

use App\Enums\EducationLevel;
use App\Enums\TeamType;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterMemberRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Branch;
use App\Models\Team;
use App\Support\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in member's own profile (PRD FR-2). It takes no member from the URL, so it can
 * only ever read and change the person who is signed in. The email is never editable here.
 */
class ProfileController extends Controller
{
    /** The fixed choices of "Profesi saat ini"; anything else stored is typed text. */
    private const FIXED_OCCUPATIONS = ['worker', 'student', 'homemaker'];

    public function edit(Request $request): View
    {
        $member = $request->user()->load(['branch', 'icareTeam']);
        $typedOccupation = $member->occupation !== null && ! in_array($member->occupation, self::FIXED_OCCUPATIONS, true);

        return view('member.profile', [
            'member' => $member,
            'occupation' => $typedOccupation ? 'other' : $member->occupation,
            'occupationOther' => $typedOccupation ? $member->occupation : '',
            'occupations' => [
                'worker' => 'Pekerja',
                'student' => 'Siswa/Mahasiswa',
                'homemaker' => 'Ibu Rumah Tangga',
                'other' => 'Lainnya',
            ],
            'branches' => Branch::where('is_active', true)->orderBy('id')->get(),
            'educationLevels' => EducationLevel::cases(),
            'teams' => Team::where('type', TeamType::Icare)->where('is_active', true)->orderBy('name')->get(),
            'noIcare' => RegisterMemberRequest::NO_ICARE,
        ]);
    }

    public function update(UpdateProfileRequest $request, PhoneNormalizer $normalizer): RedirectResponse
    {
        $data = $request->validated();
        $occupation = $data['occupation'] ?? null;
        $icare = $data['icare'] ?? null;

        // The category follows from occupation, education and birthday (the model recomputes it),
        // and a changed name, birthday or iCare re-syncs the birthday calendar (MemberObserver).
        $request->user()->update([
            'full_name' => trim($data['full_name']),
            'chinese_name' => $this->blankToNull($data['chinese_name'] ?? null),
            'birthday' => $data['birthday'],
            'domicile_taiwan' => trim($data['domicile_taiwan']),
            'line_id' => $this->blankToNull($data['line_id'] ?? null),
            'phone' => $normalizer->normalize($data['phone']),
            'branch_id' => Branch::where('code', $data['branch'])->value('id'),
            'occupation' => $occupation === 'other'
                ? trim((string) $data['occupation_other'])
                : $this->blankToNull($occupation),
            'education_level' => $this->blankToNull($data['education_level'] ?? null),
            'school_or_company' => $this->blankToNull($data['school_or_company'] ?? null),
            'icare_team_id' => in_array($icare, [null, '', RegisterMemberRequest::NO_ICARE], true) ? null : (int) $icare,
        ]);

        return redirect()->route('profile.edit')->with('saved', true);
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
