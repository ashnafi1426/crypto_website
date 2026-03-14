# 🧪 OAuth Testing Guide - Complete Flow

## 🎯 Testing OAuth Authentication & Admin Access

This guide will help you test the complete OAuth flow and verify that users can access both regular dashboard and admin panel.

---

## 📋 Prerequisites

1. ✅ Backend server running: `php artisan serve`
2. ✅ Frontend server running: `npm run dev`
3. ✅ Google OAuth configured in Google Console
4. ✅ Redirect URI added: `http://localhost:8000/api/auth/google/callback`

---

## 🔄 Step-by-Step Testing

### Step 1: Test Regular OAuth User Flow

1. **Visit Login Page**
   ```
   http://localhost:5175/login
   ```

2. **Click "Continue with Google"**
   - Should redirect to Google login
   - Sign in with your Google account
   - Grant permissions (email, profile)

3. **Verify Redirect**
   - Should redirect back to your app
   - Should show success toast: "Successfully logged in with social account!"
   - Should redirect to dashboard: `http://localhost:5175/dashboard`

4. **Check User Creation**
   ```bash
   cd crypto_website/crypto_simulation
   php artisan tinker
   >>> User::where('provider', 'google')->latest()->first()
   ```

### Step 2: Test Admin Access (Make User Admin)

1. **Find Your OAuth User**
   ```bash
   php make_oauth_user_admin.php
   # This will show available OAuth users
   ```

2. **Make User Admin**
   ```bash
   php make_oauth_user_admin.php your-email@gmail.com
   ```

3. **Verify Admin Status**
   ```bash
   php artisan tinker
   >>> $user = User::where('email', 'your-email@gmail.com')->first();
   >>> $user->is_admin; // Should return true
   ```

### Step 3: Test Admin Panel Access

1. **Logout and Login Again**
   - Go to dashboard and logout
   - Login again with Google OAuth

2. **Verify Admin Redirect**
   - After OAuth login, should redirect to: `http://localhost:5175/admin`
   - Should see admin panel interface

3. **Test Admin Panel Features**
   - Check if admin tabs are visible
   - Verify admin-only functionality works

---

## 🐛 Troubleshooting

### Issue: OAuth Login Doesn't Redirect Properly

**Symptoms:**
- User logs in with Google but stays on login page
- No redirect to dashboard/admin

**Solution:**
1. Check browser console for errors
2. Verify OAuth callback URL parameters
3. Check AuthContext logs

**Debug Commands:**
```bash
# Check if user was created
php artisan tinker
>>> User::where('provider', 'google')->latest()->first()

# Check OAuth sessions
>>> App\Models\OAuthSession::latest()->first()
```

### Issue: Admin User Not Redirecting to Admin Panel

**Symptoms:**
- OAuth user is admin but redirects to dashboard instead of admin panel

**Solution:**
1. Verify user is actually admin:
   ```bash
   php artisan tinker
   >>> $user = User::where('email', 'your-email@gmail.com')->first();
   >>> $user->is_admin; // Should be true
   ```

2. Check frontend logs in browser console
3. Verify AdminRoute component is working

### Issue: "redirect_uri_mismatch" Error

**Solution:**
1. Go to Google Cloud Console
2. Add exact redirect URI: `http://localhost:8000/api/auth/google/callback`
3. Save changes and try again

---

## 🧪 Manual Testing Checklist

### OAuth Flow Testing
- [ ] Google OAuth button appears on login page
- [ ] Clicking button redirects to Google
- [ ] Google login works
- [ ] Redirects back to app successfully
- [ ] Success toast appears
- [ ] User is logged in

### Regular User Testing
- [ ] New OAuth user redirects to dashboard
- [ ] User can access all regular features
- [ ] User cannot access admin panel
- [ ] Logout works properly

### Admin User Testing
- [ ] Admin OAuth user redirects to admin panel
- [ ] Admin can access admin features
- [ ] Admin can switch between admin and regular views
- [ ] Admin logout works properly

### Database Testing
- [ ] OAuth user created in database
- [ ] User has correct provider ('google' or 'apple')
- [ ] User has provider_id set
- [ ] User has avatar from OAuth provider
- [ ] User wallets are initialized
- [ ] Admin status is correctly set

---

## 📊 Test Data Verification

### Check OAuth User in Database
```sql
SELECT id, name, email, provider, provider_id, is_admin, created_at 
FROM users 
WHERE provider IN ('google', 'apple') 
ORDER BY created_at DESC;
```

### Check User Wallets
```sql
SELECT u.name, u.email, w.cryptocurrency_symbol, w.balance 
FROM users u 
JOIN wallets w ON u.id = w.user_id 
WHERE u.provider IN ('google', 'apple');
```

### Check OAuth Sessions
```sql
SELECT * FROM oauth_sessions 
WHERE created_at > NOW() - INTERVAL 1 HOUR;
```

---

## 🎯 Expected Results

### For Regular OAuth User:
1. **Login Flow:**
   - Google OAuth → Success → Dashboard
   - User data stored correctly
   - Wallets initialized with $10,000 USD

2. **Access Control:**
   - Can access: Dashboard, Markets, Trade, etc.
   - Cannot access: Admin panel
   - Redirect to dashboard if tries to access /admin

### For Admin OAuth User:
1. **Login Flow:**
   - Google OAuth → Success → Admin Panel
   - User data stored correctly
   - Admin flag set to true

2. **Access Control:**
   - Can access: All regular features + Admin panel
   - Direct access to /admin works
   - Can switch between admin and user views

---

## 🔧 Useful Commands

### Make OAuth User Admin
```bash
php make_oauth_user_admin.php user@gmail.com
```

### Check OAuth Configuration
```bash
php check_oauth_config.php
```

### Test OAuth User Creation
```bash
php test_oauth_user_creation.php
```

### View Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

### Clear OAuth Sessions
```bash
php artisan tinker
>>> App\Models\OAuthSession::truncate();
```

---

## 📞 Support

If you encounter issues:

1. **Check Logs:**
   - Browser console (F12)
   - Laravel logs: `storage/logs/laravel.log`

2. **Verify Configuration:**
   - Google Cloud Console settings
   - .env file OAuth credentials
   - Database migrations

3. **Test API Endpoints:**
   ```bash
   curl http://localhost:8000/api/auth/providers
   curl http://localhost:8000/api/auth/google
   ```

4. **Database Verification:**
   - Check if users are being created
   - Verify admin status is set correctly
   - Confirm wallets are initialized

---

## ✅ Success Criteria

OAuth implementation is working correctly when:

- ✅ Google OAuth login works smoothly
- ✅ Regular users redirect to dashboard
- ✅ Admin users redirect to admin panel
- ✅ User data is stored correctly in database
- ✅ Wallets are initialized for new users
- ✅ Admin access control works properly
- ✅ Logout and re-login works consistently

---

**Ready to test!** Follow the steps above to verify your OAuth implementation is working perfectly. 🚀