<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Mail;

use App\Models\Member;
use App\Services\QrIssuer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeWithQr extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Member $member)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Welcome to IFGF — your attendance QR'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.welcome',
        );
    }

    /**
     * The QR travels as an attachment — except when mail is only written to the log or kept in
     * memory, because the QR is a secret and must not end up in a log line.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $token = $this->member->qr_token_enc;

        if ($token === null || in_array(config('mail.default'), ['log', 'array'], true)) {
            return [];
        }

        return [
            Attachment::fromData(fn () => app(QrIssuer::class)->svg($token), 'ifgf-qr.svg')
                ->withMime('image/svg+xml'),
        ];
    }
}
