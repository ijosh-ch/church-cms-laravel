<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Member;

use App\Enums\EducationLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Branch;
use App\Models\CellGroup;
use App\Models\Member;
use App\Services\MemberEditor;
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
        $member = $request->user()->load(['branch', 'cellGroup']);
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
            'teams' => CellGroup::where('is_active', true)->orderBy('name')->get(),
            'noIcare' => Member::NO_ICARE,
        ]);
    }

    public function update(UpdateProfileRequest $request, MemberEditor $editor): RedirectResponse
    {
        // The category follows from occupation, education and birthday (the model recomputes it),
        // and a changed name, birthday or iCare re-syncs the birthday calendar (MemberObserver).
        $request->user()->update($editor->attributes($request->validated()));

        return redirect()->route('profile.edit')->with('saved', true);
    }
}
