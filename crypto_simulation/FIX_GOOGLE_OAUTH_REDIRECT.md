# 🔧 Fix Google OAuth Redirect URI Mismatch

## ❌ Current Error
```
Error 400: redirect_uri_mismatch
Access blocked: This app's request is invalid
```

## 🎯 Solution

You need to add the exact redirect URI to your Google Cloud Console.

---

## 📋 Step-by-Step Fix

### Step 1: Go to Google Cloud Console
1. Visit: https://console.cloud.google.com/
2. Select your project (the one with your OAuth credentials)

### Step 2: Navigate to OAuth Settings
1. Go to **APIs & Services** → **Credentials**
2. Find your OAuth 2.0 Client ID (the one ending in `.apps.googleusercontent.com`)
3. Click on it to edit

### Step 3: Add Redirect URI
In the **Authorized redirect URIs** section, add this EXACT URI:

```
http://localhost:8000/api/auth/google/callback
```

**IMPORTANT:**
- ✅ Must be exactly: `http://localhost:8000/api/auth/google/callback`
- ❌ NO trailing slash: `http://localhost:8000/api/auth/google/callback/`
- ❌ NO https: `https://localhost:8000/api/auth/google/callback`
- ❌ NO different port: `http://localhost:3000/api/auth/google/callback`

### Step 4: Save Changes
1. Click **Save** at the bottom
2. Wait a few seconds for changes to propagate

### Step 5: Test Again
1. Go back to: http://localhost:5175/login
2. Click "Continue with Google"
3. Should work now! 🎉

---

## 🔍 Current Configuration Check

Let me verify what redirect URI we're currently using:

**From .env file:**
```
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

**From API response:**
The OAuth URL includes: `redirect_uri=http%3A%2F%2Flocalhost%3A8000%2Fapi%2Fauth%2Fgoogle%2Fcallback`

This decodes to: `http://localhost:8000/api/auth/google/callback`

---

## 📸 Visual Guide

### What you should see in Google Console:

```
Authorized redirect URIs
┌─────────────────────────────────────────────────────────────┐
│ http://localhost:8000/api/auth/google/callback              │
│                                                       [×]   │
└─────────────────────────────────────────────────────────────┘
[+ ADD URI]

[SAVE]
```

### Common Mistakes to Avoid:

❌ **Wrong Protocol:**
```
https://localhost:8000/api/auth/google/callback  ← Wrong (https)
```

❌ **Trailing Slash:**
```
http://localhost:8000/api/auth/google/callback/  ← Wrong (extra /)
```

❌ **Wrong Port:**
```
http://localhost:3000/api/auth/google/callback   ← Wrong (port 3000)
```

❌ **Missing Path:**
```
http://localhost:8000/                           ← Wrong (no path)
```

✅ **Correct:**
```
http://localhost:8000/api/auth/google/callback   ← Correct!
```

---

## 🚀 After Fixing

Once you've added the correct redirect URI:

1. **Test the OAuth Flow:**
   - Visit: http://localhost:5175/login
   - Click "Continue with Google"
   - Should redirect to Google login
   - After signing in, should redirect back successfully

2. **Expected Flow:**
   ```
   Your App → Google Login → Google Callback → Your App Dashboard
   ```

3. **Success Indicators:**
   - No more "redirect_uri_mismatch" error
   - Successful redirect to Google
   - Successful redirect back to your app
   - User logged in and on dashboard

---

## 🐛 Still Having Issues?

### Double-Check These:

1. **Exact URI Match:**
   - Google Console URI: `http://localhost:8000/api/auth/google/callback`
   - .env file URI: `http://localhost:8000/api/auth/google/callback`
   - Must be identical!

2. **Backend Server Running:**
   ```bash
   # Make sure this is running:
   cd crypto_website/crypto_simulation
   php artisan serve
   # Should show: Laravel development server started: http://127.0.0.1:8000
   ```

3. **Correct Client ID:**
   - Make sure you're editing the right OAuth client in Google Console
   - Client ID should be: `1042430415967-cndr6ncvku2grh9vlouj6h874q1s2v2j.apps.googleusercontent.com`

### Alternative: Create New OAuth Client

If you can't find the right client or are unsure:

1. **Create New OAuth Client:**
   - Go to Google Console → APIs & Services → Credentials
   - Click "Create Credentials" → "OAuth client ID"
   - Choose "Web application"
   - Name: "Crypto Exchange Local"
   - Authorized redirect URIs: `http://localhost:8000/api/auth/google/callback`
   - Click "Create"

2. **Update .env with New Credentials:**
   - Copy the new Client ID and Client Secret
   - Update your .env file
   - Restart backend server

---

## 📞 Need Help?

If you're still having issues:

1. **Check Google Console:**
   - Make sure you're in the right project
   - Verify the OAuth client exists
   - Confirm redirect URI is exactly right

2. **Check Backend Logs:**
   ```bash
   # Check for any errors:
   tail -f crypto_website/crypto_simulation/storage/logs/laravel.log
   ```

3. **Test API Endpoint:**
   ```bash
   # This should return a Google OAuth URL:
   curl http://localhost:8000/api/auth/google
   ```

---

## ✅ Quick Fix Summary

1. Go to Google Cloud Console
2. Find your OAuth client credentials
3. Add redirect URI: `http://localhost:8000/api/auth/google/callback`
4. Save changes
5. Test OAuth login again

**That's it!** The redirect URI mismatch should be resolved.