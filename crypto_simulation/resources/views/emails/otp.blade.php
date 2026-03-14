<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification - Crypto Exchange</title>
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
        .otp-code {
            background: #f8f9fa;
            border: 2px dashed #00e5cc;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 30px 0;
        }
        .otp-number {
            font-size: 36px;
            font-weight: bold;
            color: #00e5cc;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
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
            <div class="logo">🔐 CRYPTO EXCHANGE</div>
            <h2>{{ ucfirst(str_replace('_', ' ', $purpose)) }} Code</h2>
        </div>

        <p>Hello {{ $user->name }},</p>

        <p>Your verification code is:</p>

        <div class="otp-code">
            <div class="otp-number">{{ $otpCode }}</div>
        </div>

        <p><strong>Important:</strong></p>
        <ul>
            <li>This code will expire in {{ $expiryMinutes }} minutes</li>
            <li>Do not share this code with anyone</li>
            <li>If you didn't request this code, please ignore this email</li>
        </ul>

        <div class="footer">
            <p>Best regards,<br>Crypto Exchange Security Team</p>
            <p><small>This is an automated message. Please do not reply to this email.</small></p>
        </div>
    </div>
</body>
</html>