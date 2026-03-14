# 🔐 OAuth Setup Guide - Google & Apple Login

This guide will walk you through setting up Google OAuth 2.0 and Apple Sign In for the crypto exchange application.

## 📋 Prerequisites

- Laravel application running
- Frontend React application
- SSL certificate (required for production)
- Google Cloud Console account
- Apple Developer account (for Apple Sign In)

## 🔧 Backend Setup

### 1. Install Required Packages

```bash
cd crypto_website/crypto_simulation
composer require firebase/php-jwt
composer require phpseclib/phpseclib
```

### 2. Run Database Migrations

```bash
php artisan migrate
```

### 3. Update Environment Variables

Copy the OAuth configuration from `.env` and update with your credentials:

```env
# OAuth Configuration - Google
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback

# OAuth Configuration - Apple
APPLE_CLIENT_ID=your-apple-service-id
APPLE_TEAM_ID=your-apple-team-id
APPLE_KEY_ID=your-apple-key-id
APPLE_PRIVATE_KEY_PATH=apple-private-key.p8
APPLE_REDIRECT_URI=http://localhost:8000/api/auth/apple/callback

# Frontend URL
FRONTEND_URL=http://localhost:5175
```

## 🔵 Google OAuth Setup

### Step 1: Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable the Google+ API

### Step 2: Configure OAuth Consent Screen

1. Go to **APIs & Services** → **OAuth consent screen**
2. Choose **External** user type
3. Fill in required information:
   - **App name**: Crypto Exchange
   - **User support email**: your-email@example.com
   - **Developer contact**: your-email@example.com
4. Add scopes:
   - `openid`
   - `email`
   - `profile`
5. Add test users (for development)

### Step 3: Create OAuth Credentials

1. Go to **APIs & Services** → **Credentials**
2. Click **Create Credentials** → **OAuth client ID**
3. Choose **Web application**
4. Configure:
   - **Name**: Crypto Exchange Web Client
   - **Authorized JavaScript origins**: 
     - `http://localhost:5175` (development)
     - `https://yourdomain.com` (production)
   - **Authorized redirect URIs**:
     - `http://localhost:8000/api/auth/google/callback` (development)
     - `https://api.yourdomain.com/api/auth/google/callback` (production)

### Step 4: Update Environment

```env
GOOGLE_CLIENT_ID=your-client-id-from-google
GOOGLE_CLIENT_SECRET=your-client-secret-from-google
```

## 🍎 Apple Sign In Setup

### Step 1: Apple Developer Account Setup

1. Enroll in [Apple Developer Program](https://developer.apple.com/programs/)
2. Access [Apple Developer Console](https://developer.apple.com/account/)

### Step 2: Create App ID

1. Go to **Certificates, Identifiers & Profiles**
2. Click **Identifiers** → **App IDs**
3. Click **+** to create new App ID
4. Configure:
   - **Description**: Crypto Exchange
   - **Bundle ID**: `com.yourcompany.cryptoexchange`
   - **Capabilities**: Enable **Sign In with Apple**

### Step 3: Create Service ID

1. Go to **Identifiers** → **Services IDs**
2. Click **+** to create new Service ID
3. Configure:
   - **Description**: Crypto Exchange Web Service
   - **Identifier**: `com.yourcompany.cryptoexchange.web`
4. Enable **Sign In with Apple**
5. Configure domains and URLs:
   - **Primary App ID**: Select the App ID created above
   - **Domains**: `localhost:8000`, `yourdomain.com`
   - **Return URLs**: 
     - `http://localhost:8000/api/auth/apple/callback`
     - `https://api.yourdomain.com/api/auth/apple/callback`

### Step 4: Create Private Key

1. Go to **Keys**
2. Click **+** to create new key
3. Configure:
   - **Key Name**: Crypto Exchange Sign In Key
   - **Services**: Enable **Sign In with Apple**
   - **Primary App ID**: Select your App ID
4. Download the `.p8` file
5. Save it as `storage/apple-private-key.p8` in your Laravel project

### Step 5: Get Required Information

From Apple Developer Console, collect:
- **Team ID**: Found in top-right corner or Membership section
- **Service ID**: The identifier you created (e.g., `com.yourcompany.cryptoexchange.web`)
- **Key ID**: From the key you created
- **Private Key**: The `.p8` file you downloaded

### Step 6: Update Environment

```env
APPLE_CLIENT_ID=com.yourcompany.cryptoexchange.web
APPLE_TEAM_ID=YOUR_TEAM_ID
APPLE_KEY_ID=YOUR_KEY_ID
APPLE_PRIVATE_KEY_PATH=apple-private-key.p8
```

## 🚀 Frontend Integration

The frontend is already configured to work with OAuth. The login page will automatically show Google and Apple login buttons when the backend is properly configured.

### Testing OAuth Integration

1. Start the backend server:
   ```bash
   cd crypto_website/crypto_simulation
   php artisan serve
   ```

2. Start the frontend server:
   ```bash
   cd crypto_frontend/crypto-vite
   npm run dev
   ```

3. Visit `http://localhost:5175/login`
4. You should see Google and Apple login buttons (if configured)

## 🔒 Security Considerations

### Production Setup

1. **Use HTTPS**: OAuth requires HTTPS in production
2. **Secure Storage**: Store private keys securely
3. **Environment Variables**: Never commit OAuth credentials to version control
4. **CORS Configuration**: Properly configure CORS for your domain
5. **Rate Limiting**: Implement rate limiting for OAuth endpoints

### SSL Certificate Setup

For production, you'll need SSL certificates:

```bash
# Using Let's Encrypt with Certbot
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com -d api.yourdomain.com
```

Update your production environment:

```env
APP_URL=https://api.yourdomain.com
FRONTEND_URL=https://yourdomain.com

GOOGLE_REDIRECT_URI=https://api.yourdomain.com/api/auth/google/callback
APPLE_REDIRECT_URI=https://api.yourdomain.com/api/auth/apple/callback
```

## 🧪 Testing

### Test Google OAuth

1. Click "Continue with Google" on login page
2. Should redirect to Google login
3. After authentication, should redirect back with user data
4. User should be logged in and redirected to dashboard

### Test Apple Sign In

1. Click "Continue with Apple" on login page
2. Should redirect to Apple login
3. After authentication, should redirect back with user data
4. User should be logged in and redirected to dashboard

## 🐛 Troubleshooting

### Common Issues

1. **"redirect_uri_mismatch"**
   - Check that redirect URIs match exactly in OAuth provider settings
   - Ensure no trailing slashes

2. **"invalid_client"**
   - Verify client ID and secret are correct
   - Check that OAuth consent screen is published (Google)

3. **"Token verification failed"**
   - Ensure Apple private key is in correct location
   - Verify Team ID, Key ID, and Service ID are correct

4. **CORS Errors**
   - Update CORS configuration in Laravel
   - Ensure frontend URL is whitelisted

### Debug Mode

Enable debug logging in `.env`:

```env
LOG_LEVEL=debug
```

Check logs for OAuth errors:

```bash
tail -f storage/logs/laravel.log
```

## 📚 API Endpoints

### OAuth Endpoints

- `GET /api/auth/providers` - Get available OAuth providers
- `GET /api/auth/google` - Initiate Google OAuth
- `GET /api/auth/google/callback` - Handle Google callback
- `GET /api/auth/apple` - Initiate Apple OAuth
- `POST /api/auth/apple/callback` - Handle Apple callback

### Response Format

```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "provider": "google",
    "avatar": "https://...",
    "is_admin": false
  },
  "token": "jwt-token-here"
}
```

## 🎯 Next Steps

1. Test OAuth integration in development
2. Set up production OAuth credentials
3. Configure SSL certificates for production
4. Implement additional security measures
5. Add user profile management for OAuth users
6. Consider implementing OAuth account linking

## 📞 Support

If you encounter issues:

1. Check the troubleshooting section above
2. Review Laravel logs for errors
3. Verify OAuth provider configurations
4. Test with different browsers/devices
5. Check network connectivity and firewall settings