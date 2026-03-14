# Google OAuth Setup Guide

## Fix "redirect_uri_mismatch" Error

The error `redirect_uri_mismatch` occurs when the redirect URI in your Google OAuth configuration doesn't match what's configured in Google Cloud Console.

### Current Configuration
- **Redirect URI**: `http://localhost:8000/api/auth/google/callback`
- **Client ID**: `1042430415967-cndr6ncvku2grh9vlouj6h874q1s2v2j.apps.googleusercontent.com`

### Steps to Fix:

1. **Go to Google Cloud Console**
   - Visit: https://console.cloud.google.com/
   - Select your project

2. **Navigate to OAuth Configuration**
   - Go to "APIs & Services" > "Credentials"
   - Find your OAuth 2.0 Client ID
   - Click on it to edit

3. **Add Authorized Redirect URIs**
   Add these URIs to the "Authorized redirect URIs" section:
   ```
   http://localhost:8000/api/auth/google/callback
   http://127.0.0.1:8000/api/auth/google/callback
   ```

4. **Save Changes**
   - Click "Save"
   - Changes may take a few minutes to propagate

### Test OAuth Flow
After updating the redirect URIs, test the OAuth flow:
1. Visit: http://localhost:5175/login
2. Click "Continue with Google"
3. Complete Google authentication
4. Should redirect back to your application

### Production Setup
For production, add your production domain:
```
https://yourdomain.com/api/auth/google/callback
```

## Current Status
✅ SSL certificates fixed
✅ OAuth services updated
✅ Database configured
✅ API endpoints working

The system is ready for testing once the Google OAuth redirect URIs are updated.