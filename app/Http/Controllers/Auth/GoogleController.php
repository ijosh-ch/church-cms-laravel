<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RegistrationController;
use App\Models\Member;
use App\Services\GoogleBirthday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * PRD FR-2 — sign in with Google. Only an email that Google has verified is accepted, and it
 * is matched to members.email. No match: on to the registration form with that email filled in,
 * and the birthday too when the switch is on and the person lets Google share it.
 */
class GoogleController extends Controller
{
    public function redirect(GoogleBirthday $birthdays): RedirectResponse|SymfonyRedirectResponse
    {
        if (! config('services.google.client_id')) {
            return $this->backToSignIn(__('Google sign-in is not set up on this server.'));
        }

        // Set, not added: the driver may outlive one request, and scopes must not pile up on it.
        $scopes = ['openid', 'profile', 'email', ...($birthdays->enabled() ? [GoogleBirthday::SCOPE] : [])];

        return Socialite::driver('google')->setScopes($scopes)->redirect();
    }

    public function callback(Request $request, GoogleBirthday $birthdays): RedirectResponse
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
                // Asked only for someone who is about to fill in the form; a member's is on record.
                'birthday' => $birthdays->fetch($google->token ?? null),
            ]);

            return redirect()->route('register');
        }

        if ($member->email_verified_at === null) {
            // Until now nobody had proved this email: registration signs in whoever typed it.
            // Its owner has just arrived, so every earlier sign-in for this member ends here.
            DB::table(config('session.table', 'sessions'))->where('user_id', $member->id)->delete();

            $member->forceFill(['email_verified_at' => now(), 'remember_token' => Str::random(60)])->save();
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
