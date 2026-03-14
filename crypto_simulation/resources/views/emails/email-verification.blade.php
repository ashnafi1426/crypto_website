<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Crypto Exchange</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #00e5cc;
            margin-bottom: 10px;
        }
        .verify-button {
            display: inline-block;
            background: linear-gradient(135deg, #00e5cc 0%, #00d084 100%);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            margin: 20px 0;
        }
        .verify-link {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            word-break: break-all;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">📧 CRYPTO EXCHANGE</div>
            <h2>Verify Your Email Address</h2>
        </div>

        <p>Hello {{ $user->name }},</p>

        <p>Thank you for registering with Crypto Exchange! To complete your registration and secure your account, please verify your email address by clicking the button below:</p>

        <div style="text-align: center;">
            <a href="{{ $verificationUrl }}" class="verify-button">
                ✅ Verify Email Address
            </a>
        </div>

        <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
        
        <div class="verify-link">{{ $verificationUrl }}</div>

        <p><strong>Important:</strong></p>
        <ul>
            <li>This verification link will expire in 24 hours</li>
            <li>If you didn't create an account, please ignore this email</li>
            <li>For security reasons, do not share this link with anyone</li>
        </ul>

        <div class="footer">
            <p>Best regards,<br>Crypto Exchange Team</p>
            <p><small>This is an automated message. Please do not reply to this email.</small></p>
        </div>
    </div>
</body>
</html>