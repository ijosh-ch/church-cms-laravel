<?php

/*
 * Copyright (C) 2026 IFGF Taipei Zhongli
 *
 * This file is part of the IFGF church operations system.
 *
 * It is free software: you may redistribute it and/or modify it under the terms of
 * the GNU Affero General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 *
 * It is distributed in the hope that it will be useful to other churches and
 * ministries, but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General
 * Public License for more details: <https://www.gnu.org/licenses/>.
 *
 * See NOTICE.md for how this relates to the MIT-licensed upstream it builds on.
 */

namespace App\Http\Controllers\Usher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Sign-in for the usher station.
 *
 * Deliberately a separate controller from Auth\LoginController, and deliberately NOT
 * reachable from the member site. RegisterController on the member side hard-codes
 * usergroup_id = 3 and grants every permission row (SEC-001); nothing on this site may
 * share a code path with it.
 *
 * There is no registration counterpart here by design. Usher accounts are provisioned by
 * an administrator — a church door station is not a place anyone signs themselves up.
 */
class SessionController extends Controller
{
    public function show()
    {
        if (Auth::check()) {
            return redirect()->route('usher.scan');
        }

        return view('ifgf.usher.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        // Authenticating is not the same as being allowed to operate a station. A member
        // with a valid password but no usher role gets signed straight back out, so a
        // shared door device never holds a member session.
        if (! $request->user()->hasRole(['usher', 'churchadmin', 'superadmin'])) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('usher.not_authorised'),
            ]);
        }

        return redirect()->intended(route('usher.scan'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('usher.login');
    }
}
