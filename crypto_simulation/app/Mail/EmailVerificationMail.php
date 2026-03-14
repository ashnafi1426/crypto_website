<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $verificationToken;
    public string $verificationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $verificationToken)
    {
        $this->user = $user;
        $this->verificationToken = $verificationToken;
        $this->verificationUrl = url("/api/auth/verify-email?token={$verificationToken}");
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Email Address - Crypto Exchange',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.email-verification',
            text: 'emails.email-verification-text',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}