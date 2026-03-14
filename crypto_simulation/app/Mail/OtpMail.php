<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otpCode;
    public string $purpose;
    public User $user;
    public int $expiryMinutes;

    /**
     * Create a new message instance.
     */
    public function __construct(string $otpCode, string $purpose, User $user, int $expiryMinutes = 10)
    {
        $this->otpCode = $otpCode;
        $this->purpose = $purpose;
        $this->user = $user;
        $this->expiryMinutes = $expiryMinutes;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->purpose) {
            'email_verification' => 'Verify Your Email Address',
            'registration' => 'Complete Your Registration',
            'login' => 'Login Verification Code',
            'password_reset' => 'Password Reset Code',
            'transaction' => 'Transaction Verification Code',
            default => 'Verification Code'
        };

        return new Envelope(
            subject: $subject . ' - Crypto Exchange',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            html: 'emails.otp',
            text: 'emails.otp-text',
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