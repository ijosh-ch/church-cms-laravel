<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RegistrationController;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * PRD FR-2 — sign in with Google. Only an email that Google has verified is accepted, and it
 * is matched to members.email. No match: on to the registration form with that email filled in.
 */
class GoogleController extends Controller
{
    public function redirect(): RedirectResponse|SymfonyRedirectResponse
    {
        if (! config('services.google.client_id')) {
            return $this->backToSignIn(__('Google sign-in is not set up on this server.'));
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // Cancelling at Google, or coming back with a stale tab, is not worth a log line.
            if (! $e instanceof InvalidStateException) {
                report($e);
            }

            return $this->backToSignIn(__('Google sign-in did not finish. Please try again.'));
        }

        $email = mb_strtolower(trim((string) $google->getEmail()));
        $raw = (array) ($google->user ?? []);
        $verified = filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($email === '' || ! $verified) {
            return $this->backToSignIn(__('That Google account has no verified email address, so it cannot be used here.'));
        }

        $member = Member::where('email', $email)->first();

        if ($member === null) {
            $request->session()->put(RegistrationController::GOOGLE, [
                'email' => $email,
                'name' => trim((string) $google->getName()),
            ]);

            return redirect()->route('register');
        }

        if ($member->email_verified_at === null) {
            $member->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($member, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    private function backToSignIn(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => $message]);
    }
}
