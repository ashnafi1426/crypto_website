NEXUS - Password Reset Request

Hello {{ $user->name }},

We received a request to reset the password for your NEXUS account associated with {{ $user->email }}.

To reset your password, please visit the following link:
{{ $resetUrl }}

SECURITY INFORMATION:
- This link will expire in {{ $expiryMinutes }} minutes
- If you didn't request this reset, please ignore this email
- Your password won't change until you create a new one
- For security, this link can only be used once

If you didn't request a password reset, please ignore this email or contact our support team if you have concerns about your account security.

---
NEXUS Crypto Exchange
© {{ date('Y') }} NEXUS. All rights reserved.

If you have any questions, please contact our support team.