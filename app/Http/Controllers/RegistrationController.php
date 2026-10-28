<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers;

use App\Http\Requests\RegisterMemberRequest;
use App\Models\Branch;
use App\Models\CellGroup;
use App\Models\Member;
use App\Services\MemberRegistrar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * PRD FR-1 — the public registration form: only the basics, on one page, so a newcomer is done
 * in a minute (owner, 2026-10-21). The rest of the profile is asked for afterwards.
 */
class RegistrationController extends Controller
{
    /** Set by Auth\GoogleController when Google vouches for an email that is not registered yet. */
    public const GOOGLE = 'registration.google';

    public function create(Request $request): View
    {
        // "Register without Google" drops the email Google supplied, so any email can be typed.
        if ($request->boolean('manual')) {
            $request->session()->forget(self::GOOGLE);
        }

        $google = $request->session()->get(self::GOOGLE);
        $google = is_array($google) ? $google : null;

        return view('registration.create', [
            'branches' => Branch::where('is_active', true)->orderBy('id')->get(),
            'teams' => CellGroup::where('is_active', true)->orderBy('name')->get(),
            'noIcare' => Member::NO_ICARE,
            'google' => $google,
            // What Google supplied fills the form in; the person can still change it.
            'saved' => array_filter([
                'email' => $google['email'] ?? null,
                'full_name' => $google['name'] ?? null,
                'birthday' => $google['birthday'] ?? null,
            ]),
        ]);
    }

    public function store(RegisterMemberRequest $request, MemberRegistrar $registrar): RedirectResponse
    {
        // The email counts as verified only if it is the one Google just vouched for.
        $data = $request->validated();
        $google = $request->session()->pull(self::GOOGLE);
        $emailVerified = is_array($google) && ($google['email'] ?? null) === $data['email'];

        $member = $registrar->register($data, $emailVerified);

        // Already registered: nothing changed, and the answer does not say so. The same answer
        // goes to someone who is signed in — an usher helping a newcomer on their own phone
        // stays the usher; the newcomer signs in on theirs.
        if ($member === null || $request->user() !== null) {
            return redirect()->route('register.done');
        }

        Auth::login($member, remember: true);
        $request->session()->regenerate();

        return redirect()->route('home')->with('registered', true);
    }

    public function done(): View
    {
        return view('registration.done');
    }
}
