# 🚀 OAuth Production Deployment Guide

## 🎯 What Changes After Deployment

When you deploy your crypto exchange to production, you'll need to update OAuth configurations to use your live domain instead of localhost.

---

## 📋 Pre-Deployment Checklist

### 1. Domain & SSL Setup
- ✅ Purchase domain (e.g., `cryptoexchange.com`)
- ✅ Set up SSL certificate (HTTPS required for OAuth)
- ✅ Configure DNS to point to your server
- ✅ Verify HTTPS is working

### 2. Server Requirements
- ✅ Web server (Apache/Nginx)
- ✅ PHP 8.2+
- ✅ Database (MySQL/PostgreSQL recommended for production)
- ✅ SSL certificate installed
- ✅ Firewall configured

---

## 🔧 OAuth Configuration Changes

### Current (Development):
```
Frontend URL: http://localhost:5175
Backend URL:  http://localhost:8000
Redirect URI: http://localhost:8000/api/auth/google/callback
```

### Production (Example):
```
Frontend URL: https://cryptoexchange.com
Backend URL:  https://api.cryptoexchange.com
Redirect URI: https://api.cryptoexchange.com/api/auth/google/callback
```

---

## 🔵 Google OAuth Production Setup

### Step 1: Update Google Cloud Console

1. **Go to Google Cloud Console:**
   - Visit: https://console.cloud.google.com/
   - Select your project

2. **Update OAuth Client:**
   - Go to APIs & Services → Credentials
   - Find your OAuth client ID: `your-google-client-id.apps.googleusercontent.com`
   - Click to edit

3. **Add Production URLs:**
   
   **Authorized JavaScript origins:**
   ```
   https://cryptoexchange.com
   https://www.cryptoexchange.com
   ```
   
   **Authorized redirect URIs:**
   ```
   https://api.cryptoexchange.com/api/auth/google/callback
   ```

4. **Keep Development URLs (Optional):**
   - You can keep localhost URLs for continued development
   - Or create separate OAuth clients for dev/prod

### Step 2: Update OAuth Consent Screen

1. **Publish OAuth Consent Screen:**
   - Go to APIs & Services → OAuth consent screen
   - Click "Publish App" (moves from Testing to Production)
   - This allows any Google user to login (not just test users)

2. **Update App Information:**
   - App name: Your production app name
   - User support email: Your support email
   - Developer contact: Your contact email
   - Privacy policy URL: `https://cryptoexchange.com/privacy`
   - Terms of service URL: `https://cryptoexchange.com/terms`

---

## 🍎 Apple OAuth Production Setup

### Step 1: Update Service ID

1. **Go to Apple Developer Console:**
   - Visit: https://developer.apple.com/account/
   - Go to Certificates, Identifiers & Profiles

2. **Update Service ID:**
   - Find your Service ID (e.g., `com.yourcompany.cryptoexchange.web`)
   - Click to edit
   - Update domains and return URLs:

   **Domains:**
   ```
   cryptoexchange.com
   api.cryptoexchange.com
   ```

   **Return URLs:**
   ```
   https://api.cryptoexchange.com/api/auth/apple/callback
   ```

---

## ⚙️ Environment Configuration

### Production .env File

Create a production `.env` file with updated URLs:

```env
# App Configuration
APP_NAME="Crypto Exchange"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.cryptoexchange.com

# Frontend URL
FRONTEND_URL=https://cryptoexchange.com

# OAuth Configuration - Google
GOOGLE_CLIENT_ID=your-google-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=https://api.cryptoexchange.com/api/auth/google/callback

# OAuth Configuration - Apple
APPLE_CLIENT_ID=com.yourcompany.cryptoexchange.web
APPLE_TEAM_ID=YOUR_TEAM_ID
APPLE_KEY_ID=YOUR_KEY_ID
APPLE_PRIVATE_KEY_PATH=apple-private-key.p8
APPLE_REDIRECT_URI=https://api.cryptoexchange.com/api/auth/apple/callback

# Database (Production)
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=crypto_exchange_prod
DB_USERNAME=crypto_user
DB_PASSWORD=secure_password

# Security
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=cryptoexchange.com,www.cryptoexchange.com
```

---

## 🌐 Domain Structure Examples

### Option 1: Subdomain API
```
Frontend: https://cryptoexchange.com
Backend:  https://api.cryptoexchange.com
```

### Option 2: Same Domain
```
Frontend: https://cryptoexchange.com
Backend:  https://cryptoexchange.com/api
```

### Option 3: Separate Domains
```
Frontend: https://cryptoexchange.com
Backend:  https://cryptoapi.com
```

---

## 🔒 Security Considerations

### 1. HTTPS Enforcement
```php
// In AppServiceProvider.php
public function boot()
{
    if (config('app.env') === 'production') {
        URL::forceScheme('https');
    }
}
```

### 2. CORS Configuration
```php
// In config/cors.php
'allowed_origins' => [
    'https://cryptoexchange.com',
    'https://www.cryptoexchange.com',
],
```

### 3. Cookie Security
```env
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

---

## 📱 Frontend Configuration

### Update API Base URL

In your frontend (React), update the API base URL:

```javascript
// src/utils/api.js
const API_BASE_URL = process.env.NODE_ENV === 'production' 
  ? 'https://api.cryptoexchange.com'
  : 'http://localhost:8000';
```

### Environment Variables

Create `.env.production` in your frontend:

```env
VITE_API_URL=https://api.cryptoexchange.com
VITE_APP_URL=https://cryptoexchange.com
```

---

## 🧪 Testing Production OAuth

### 1. Test Google OAuth
1. Visit: `https://cryptoexchange.com/login`
2. Click "Continue with Google"
3. Should redirect to Google login
4. After authentication, should redirect to: `https://cryptoexchange.com/dashboard`

### 2. Test Apple OAuth
1. Visit: `https://cryptoexchange.com/login`
2. Click "Continue with Apple"
3. Should redirect to Apple login
4. After authentication, should redirect back successfully

### 3. Verify Database
- Check that OAuth users are created correctly
- Verify wallets are initialized
- Test logout/login flow

---

## 🚀 Deployment Steps

### 1. Server Setup
```bash
# Clone repository
git clone your-repo.git
cd crypto-exchange

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# Set permissions
chmod -R 755 storage bootstrap/cache
```

### 2. Database Setup
```bash
# Run migrations
php artisan migrate --force

# Seed data
php artisan db:seed --class=CryptocurrencySeeder
php artisan db:seed --class=AdminUserSeeder
```

### 3. Configure Web Server

**Nginx Example:**
```nginx
server {
    listen 443 ssl;
    server_name api.cryptoexchange.com;
    
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    root /var/www/crypto-exchange/public;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 4. Update OAuth Providers
- Update Google Cloud Console with production URLs
- Update Apple Developer Console with production URLs
- Test OAuth flows

---

## 📊 Production Checklist

### Before Going Live:
- [ ] Domain purchased and configured
- [ ] SSL certificate installed and working
- [ ] Production database set up
- [ ] Environment variables updated
- [ ] Google OAuth updated with production URLs
- [ ] Apple OAuth updated with production URLs
- [ ] OAuth consent screen published
- [ ] Frontend built and deployed
- [ ] Backend deployed and configured
- [ ] Database migrations run
- [ ] Test OAuth flows working
- [ ] Security headers configured
- [ ] Monitoring set up

### After Deployment:
- [ ] Test Google OAuth login
- [ ] Test Apple OAuth login
- [ ] Test user registration/login
- [ ] Test trading functionality
- [ ] Test admin panel access
- [ ] Monitor error logs
- [ ] Set up backups
- [ ] Configure monitoring/alerts

---

## 🐛 Common Production Issues

### 1. "redirect_uri_mismatch" in Production
**Solution:** Ensure production redirect URI is exactly:
```
https://api.cryptoexchange.com/api/auth/google/callback
```

### 2. CORS Errors
**Solution:** Update CORS configuration:
```php
'allowed_origins' => ['https://cryptoexchange.com'],
```

### 3. Mixed Content Errors
**Solution:** Ensure all resources use HTTPS:
```javascript
// Use relative URLs or HTTPS
const API_URL = '/api' // or 'https://api.cryptoexchange.com'
```

### 4. Cookie Issues
**Solution:** Configure secure cookies:
```env
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=.cryptoexchange.com
```

---

## 📞 Support & Monitoring

### 1. Error Logging
```bash
# Monitor Laravel logs
tail -f storage/logs/laravel.log

# Monitor web server logs
tail -f /var/log/nginx/error.log
```

### 2. OAuth Debugging
```bash
# Test OAuth endpoints
curl https://api.cryptoexchange.com/api/auth/providers
curl https://api.cryptoexchange.com/api/auth/google
```

### 3. Database Monitoring
```sql
-- Check OAuth users
SELECT * FROM users WHERE provider IN ('google', 'apple');

-- Check OAuth sessions
SELECT * FROM oauth_sessions WHERE created_at > NOW() - INTERVAL 1 DAY;
```

---

## ✅ Summary

**Key Changes for Production:**
1. **URLs:** localhost → your domain (HTTPS)
2. **Google Console:** Add production redirect URIs
3. **Apple Console:** Add production domains/URLs
4. **Environment:** Update .env with production values
5. **Security:** Enable HTTPS, secure cookies, CORS
6. **Testing:** Verify OAuth flows work in production

**The OAuth system will work exactly the same in production, just with different URLs!**

---

**Next Steps:**
1. Set up your production domain and SSL
2. Update OAuth provider configurations
3. Deploy and test
4. Monitor and maintain

Your OAuth implementation is production-ready - just needs the URL updates! 🚀