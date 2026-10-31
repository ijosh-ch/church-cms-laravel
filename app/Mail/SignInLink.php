<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Mail;

use App\Services\SignInLinks;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * PRD FR-2 — the one-time sign-in link. Nothing the registrant typed goes into it, not even the
 * name: the address may not be proved yet. Sent at once, never queued (see Jobs\SendSignInLink).
 */
class SignInLink extends Mailable
{
    public function __construct(public readonly string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your sign-in link — :app', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.sign-in-link',
            with: ['minutes' => SignInLinks::MINUTES],
        );
    }
}
