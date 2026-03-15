<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $resetUrl;
    public string $token;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $resetUrl, string $token)
    {
        $this->user = $user;
        $this->resetUrl = $resetUrl;
        $this->token = $token;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your NEXUS Account Password',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.password-reset',
            text: 'emails.password-reset-text',
            with: [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
                'token' => $this->token,
                'expiryMinutes' => 60,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}