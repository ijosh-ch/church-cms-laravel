<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * PRD FR-1 — the one reminder, a week after registering, to fill in the rest of the profile.
 * Like the welcome email it may go to an address nobody has proved, so it carries nothing the
 * registrant typed: no name, only a link.
 */
class CompleteYourProfile extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('IFGF — two minutes to complete your profile'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.complete-profile',
        );
    }
}
