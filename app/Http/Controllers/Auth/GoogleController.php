<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RegistrationController;
use App\Models\Member;
use App\Services\EmailVerifier;
use App\Services\GoogleBirthday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

/**
 * PRD FR-2 — sign in with Google. Only an email that Google has verified is accepted, and it
 * is matched to members.email. No match: on to the registration form with that email filled in,
 * and the birthday too when the switch is on and the person lets Google share it.
 *
 * The same flow also runs in "connect" mode ("Hubungkan dengan Google"): a signed-in member who
 * registered without Google proves their email by choosing the Google account that has it.
 */
class GoogleController extends Controller
{
    /** Session key: the id of the signed-in member who started connect mode. Never in the URL. */
    public const CONNECT = 'google.connect';

    public function __construct(private readonly EmailVerifier $verifier) {}

    public function redirect(Request $request, GoogleBirthday $birthdays): RedirectResponse|SymfonyRedirectResponse
    {
        // A plain sign-in never finishes a connect that was started and abandoned.
        $request->session()->forget(self::CONNECT);

        if (! config('services.google.client_id')) {
            return $this->backToSignIn(__('Google sign-in is not set up on this server.'));
        }

        // Set, not added: the driver may outlive one request, and scopes must not pile up on it.
        $scopes = ['openid', 'profile', 'email', ...($birthdays->enabled() ? [GoogleBirthday::SCOPE] : [])];

        return Socialite::driver('google')->setScopes($scopes)->redirect();
    }

    /** "Hubungkan dengan Google" — only for a signed-in member whose email nobody has proved yet. */
    public function connect(Request $request): RedirectResponse|SymfonyRedirectResponse
    {
        $member = $request->user();

        if ($member->email_verified_at !== null) {
            return redirect()->route('profile.edit');
        }

        if (! config('services.google.client_id')) {
            return $this->backToProfile(__('Google sign-in is not set up on this server.'));
        }

        $request->session()->put(self::CONNECT, $member->getKey());

        // No birthday scope: the member's birthday is already on record.
        return Socialite::driver('google')->setScopes(['openid', 'profile', 'email'])->redirect();
    }

    public function callback(Request $request, GoogleBirthday $birthdays): RedirectResponse
    {
        // Read once: a second trip through the callback is an ordinary sign-in.
        $connecting = $request->session()->pull(self::CONNECT);
        $fail = fn (string $message) => $connecting === null ? $this->backToSignIn($message) : $this->backToProfile($message);

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // Cancelling at Google, or coming back with a stale tab, is not worth a log line.
            if (! $e instanceof InvalidStateException) {
                report($e);
            }

            return $fail(__('Google sign-in did not finish. Please try again.'));
        }

        $email = mb_strtolower(trim((string) $google->getEmail()));
        $raw = (array) ($google->user ?? []);
        $verified = filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($email === '' || ! $verified) {
            return $fail(__('That Google account has no verified email address, so it cannot be used here.'));
        }

        if ($connecting !== null) {
            return $this->finishConnect($request, $email, (int) $connecting);
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

        $this->verifier->prove($member, 'google');

        Auth::login($member, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    private function finishConnect(Request $request, string $googleEmail, int $connecting): RedirectResponse
    {
        $member = $request->user();

        // The member who started connecting must be the one still signed in.
        if ($member === null || $member->getKey() !== $connecting) {
            return $this->backToSignIn(__('Google sign-in did not finish. Please try again.'));
        }

        if ($googleEmail !== mb_strtolower(trim((string) $member->email))) {
            // Never link a different address: whoever registered with someone else's email could
            // then keep that person's roles. The account stays exactly as it was.
            return $this->backToProfile(__('Akun Google itu memakai email yang berbeda, jadi tidak ada yang diubah. Pilih akun Google dengan email yang sama seperti saat pendaftaran.'));
        }

        $this->verifier->prove($member, 'google-connect');

        // prove() ended every session of this member, this one too: carry on in a fresh one,
        // with a remember cookie for the new token.
        Auth::login($member, remember: true);
        $request->session()->regenerate();

        return redirect()->route('profile.edit')->with('google_connected', true);
    }

    private function backToSignIn(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => $message]);
    }

    /** A flash message, not a form error: the profile form itself is untouched. */
    private function backToProfile(string $message): RedirectResponse
    {
        return redirect()->route('profile.edit')->with('google_error', $message);
    }
}
