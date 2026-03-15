<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password - NEXUS</title>
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
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        .content {
            margin-bottom: 30px;
        }
        .reset-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 20px 0;
            text-align: center;
        }
        .reset-button:hover {
            opacity: 0.9;
        }
        .security-info {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 12px;
        }
        .token-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">NEXUS</div>
            <div class="subtitle">Crypto Exchange Platform</div>
        </div>

        <div class="content">
            <h2>Password Reset Request</h2>
            
            <p>Hello {{ $user->name }},</p>
            
            <p>We received a request to reset the password for your NEXUS account associated with <strong>{{ $user->email }}</strong>.</p>
            
            <p>Click the button below to reset your password:</p>
            
            <div style="text-align: center;">
                <a href="{{ $resetUrl }}" class="reset-button">Reset My Password</a>
            </div>
            
            <div class="security-info">
                <strong>Security Information:</strong>
                <ul>
                    <li>This link will expire in {{ $expiryMinutes }} minutes</li>
                    <li>If you didn't request this reset, please ignore this email</li>
                    <li>Your password won't change until you create a new one</li>
                    <li>For security, this link can only be used once</li>
                </ul>
            </div>
            
            <p><strong>Can't click the button?</strong> Copy and paste this link into your browser:</p>
            <div class="token-info">{{ $resetUrl }}</div>
            
            <p>If you didn't request a password reset, please ignore this email or contact our support team if you have concerns about your account security.</p>
        </div>

        <div class="footer">
            <p>This email was sent by NEXUS Crypto Exchange</p>
            <p>If you have any questions, please contact our support team.</p>
            <p>&copy; {{ date('Y') }} NEXUS. All rights reserved.</p>
        </div>
    </div>
</body>
</html>