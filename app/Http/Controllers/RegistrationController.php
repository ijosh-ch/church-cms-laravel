<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Enums\EducationLevel;
use App\Enums\TeamType;
use App\Http\Requests\RegisterMemberRequest;
use App\Http\Requests\RegisterStepOneRequest;
use App\Models\Branch;
use App\Models\Team;
use App\Services\MemberRegistrar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * PRD FR-1 — the public registration form, in the two pages the Google Form has today.
 * Answering "no" to "plan to attend again?" ends it after page one.
 */
class RegistrationController extends Controller
{
    /** Page one waits here until page two is submitted. */
    private const STEP_ONE = 'registration.step_one';

    /** Set by Auth\GoogleController when Google vouches for an email that is not registered yet. */
    public const GOOGLE = 'registration.google';

    public function create(Request $request): View
    {
        // "Register without Google" drops the email Google supplied, so any email can be typed.
        if ($request->boolean('manual')) {
            $request->session()->forget(self::GOOGLE);
        }

        $google = $request->session()->get(self::GOOGLE);

        return view('registration.step-one', [
            'branches' => Branch::where('is_active', true)->orderBy('id')->get(),
            'google' => is_array($google) ? $google : null,
            // What was typed on an earlier visit wins over what Google supplied.
            'saved' => $request->session()->get(self::STEP_ONE, []) + array_filter([
                'email' => $google['email'] ?? null,
                'full_name' => $google['name'] ?? null,
            ]),
        ]);
    }

    public function storeStepOne(RegisterStepOneRequest $request, MemberRegistrar $registrar): RedirectResponse
    {
        $stepOne = $request->validated();

        if ($stepOne['attend_again'] === 'no') {
            return $this->finish($request, $registrar, $stepOne);
        }

        $request->session()->put(self::STEP_ONE, $stepOne);

        return redirect()->route('register.details');
    }

    public function details(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::STEP_ONE)) {
            return redirect()->route('register');
        }

        return view('registration.step-two', [
            // The live form's wording for "Profesi saat ini".
            'occupations' => [
                'worker' => 'Pekerja',
                'student' => 'Siswa/Mahasiswa',
                'homemaker' => 'Ibu Rumah Tangga',
                'other' => 'Lainnya',
            ],
            'educationLevels' => EducationLevel::cases(),
            'teams' => Team::where('type', TeamType::Icare)->where('is_active', true)->orderBy('name')->get(),
            'noIcare' => RegisterMemberRequest::NO_ICARE,
        ]);
    }

    public function store(RegisterMemberRequest $request, MemberRegistrar $registrar): RedirectResponse
    {
        $stepOne = $request->session()->get(self::STEP_ONE);

        if (! is_array($stepOne)) {
            return redirect()->route('register');
        }

        return $this->finish($request, $registrar, [...$stepOne, ...$request->validated()]);
    }

    public function done(): View
    {
        return view('registration.done');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function finish(Request $request, MemberRegistrar $registrar, array $data): RedirectResponse
    {
        $request->session()->forget(self::STEP_ONE);

        // The email counts as verified only if it is the one Google just vouched for.
        $google = $request->session()->pull(self::GOOGLE);
        $emailVerified = is_array($google) && ($google['email'] ?? null) === $data['email'];

        $member = $registrar->register($data, $emailVerified);

        // Already registered: nothing changed, and the answer does not say so.
        if ($member === null) {
            return redirect()->route('register.done');
        }

        Auth::login($member, remember: true);
        $request->session()->regenerate();

        return redirect()->route('home')->with('registered', true);
    }
}
