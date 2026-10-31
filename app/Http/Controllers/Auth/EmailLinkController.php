<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendSignInLinkRequest;
use App\Jobs\SendSignInLink;
use App\Services\EmailVerifier;
use App\Services\SignInLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

use function Illuminate\Support\defer;

/**
 * PRD FR-2 — sign in with a one-time link sent by email. The app has no passwords; this is how a
 * member who registered without Google signs in again, and how they prove their email.
 */
class EmailLinkController extends Controller
{
    /** Always the same answer, whether or not a member has this email. */
    public function send(SendSignInLinkRequest $request): RedirectResponse
    {
        $email = $request->validated('email');

        // After the response, so a member's email does not take longer to answer.
        defer(fn () => SendSignInLink::dispatchSync($email));

        return redirect()->route('login')->with('link_sent', true);
    }

    /**
     * Opening the link only shows a button: mail filters that open every link to check it must
     * not use it up. The token is not looked at here.
     */
    public function show(string $token): Response
    {
        return response()
            ->view('auth.sign-in-link', ['token' => $token])
            // The token is in this page's address: never pass it on, never keep the page.
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store');
    }

    public function consume(Request $request, string $token, SignInLinks $links, EmailVerifier $verifier): RedirectResponse
    {
        $member = $links->consume($token);

        if ($member === null) {
            return redirect()->route('login')->withErrors([
                'link' => __('That sign-in link has expired or was already used. Ask for a new one below.'),
            ]);
        }

        // Following the link proves the email, and the first time ends every earlier sign-in.
        $verifier->prove($member, 'email-link');

        // Someone else was signed in on this browser: nothing of their session carries over.
        if (Auth::check() && Auth::id() !== $member->getKey()) {
            Auth::logout();
            $request->session()->invalidate();
        }

        Auth::login($member, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }
}
