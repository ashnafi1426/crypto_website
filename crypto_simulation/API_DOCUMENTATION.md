# 🔐 Authentication API Documentation

Complete API documentation for the enhanced authentication system with OAuth support.

## 📋 Base URL

```
Development: http://localhost:8000/api
Production: https://api.yourdomain.com/api
```

## 🔑 Authentication

The API uses JWT (JSON Web Tokens) for authentication. Include the token in the Authorization header:

```
Authorization: Bearer your-jwt-token-here
```

## 📚 Endpoints

### 🔓 Public Endpoints (No Authentication Required)

#### Register User
```http
POST /auth/register
```

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "User registered successfully.",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "provider": "local",
    "is_admin": false,
    "email_verified_at": null
  }
}
```

#### Login User
```http
POST /auth/login
```

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "SecurePassword123!"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Authentication successful.",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "provider": "local",
    "is_admin": false,
    "email_verified_at": "2024-01-15T10:30:00Z"
  }
}
```

#### Request Password Reset
```http
POST /auth/password/reset
```

**Request Body:**
```json
{
  "email": "john@example.com"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Password reset link has been sent to your email."
}
```

#### Reset Password
```http
POST /auth/password/reset/confirm
```

**Request Body:**
```json
{
  "email": "john@example.com",
  "token": "reset-token-from-email",
  "password": "NewSecurePassword123!",
  "password_confirmation": "NewSecurePassword123!"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Password reset successfully."
}
```

### 🔵 Google OAuth Endpoints

#### Get OAuth Providers
```http
GET /auth/providers
```

**Response (200 OK):**
```json
{
  "success": true,
  "providers": {
    "google": {
      "enabled": true,
      "name": "Google",
      "icon": "google"
    },
    "apple": {
      "enabled": true,
      "name": "Apple", 
      "icon": "apple"
    }
  }
}
```

#### Initiate Google OAuth
```http
GET /auth/google?redirect_url=http://localhost:5175/dashboard
```

**Response (200 OK):**
```json
{
  "success": true,
  "auth_url": "https://accounts.google.com/o/oauth2/v2/auth?client_id=...",
  "state": "random-state-string"
}
```

#### Google OAuth Callback
```http
GET /auth/google/callback?code=auth_code&state=state_string
```

**Response (302 Redirect):**
Redirects to frontend with authentication result:
```
http://localhost:5175/login?auth_success=true&token=jwt_token&user=base64_encoded_user_data
```

### 🍎 Apple OAuth Endpoints

#### Initiate Apple OAuth
```http
GET /auth/apple?redirect_url=http://localhost:5175/dashboard
```

**Response (200 OK):**
```json
{
  "success": true,
  "auth_url": "https://appleid.apple.com/auth/authorize?client_id=...",
  "state": "random-state-string"
}
```

#### Apple OAuth Callback
```http
POST /auth/apple/callback
```

**Request Body (Form Data):**
```
code=auth_code
state=state_string
user={"name":{"firstName":"John","lastName":"Doe"}}
```

**Response (302 Redirect):**
Redirects to frontend with authentication result.

### 🔒 Protected Endpoints (Authentication Required)

#### Get Current User
```http
GET /auth/user
Authorization: Bearer jwt-token
```

**Response (200 OK):**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "provider": "google",
    "avatar": "https://lh3.googleusercontent.com/...",
    "is_admin": false,
    "email_verified_at": "2024-01-15T10:30:00Z",
    "created_at": "2024-01-15T10:30:00Z",
    "portfolio_value": "15000.00"
  }
}
```

#### Logout User
```http
POST /auth/logout
Authorization: Bearer jwt-token
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Successfully logged out."
}
```

#### Send Email Verification
```http
POST /auth/send-verification
Authorization: Bearer jwt-token
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Verification email sent successfully."
}
```

#### Verify Email
```http
GET /auth/verify-email?token=verification-token
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Email verified successfully."
}
```

#### Get Email Verification Status
```http
GET /auth/verification-status
Authorization: Bearer jwt-token
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "is_verified": true,
    "verified_at": "2024-01-15T10:30:00Z"
  }
}
```

## 🔐 Two-Factor Authentication Endpoints

#### Generate 2FA Secret
```http
POST /auth/2fa/generate
Authorization: Bearer jwt-token
```

**Response (200 OK):**
```json
{
  "success": true,
  "secret": "JBSWY3DPEHPK3PXP",
  "qr_code": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
  "backup_codes": ["12345678", "87654321", ...]
}
```

#### Confirm 2FA Setup
```http
POST /auth/2fa/confirm
Authorization: Bearer jwt-token
```

**Request Body:**
```json
{
  "code": "123456"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Two-factor authentication enabled successfully.",
  "backup_codes": ["12345678", "87654321", ...]
}
```

#### Verify 2FA Code
```http
POST /auth/2fa/verify
```

**Request Body:**
```json
{
  "email": "john@example.com",
  "code": "123456"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "2FA verification successful.",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "is_admin": false
  }
}
```

## 📧 OTP Verification Endpoints

#### Generate OTP
```http
POST /auth/otp/generate
Authorization: Bearer jwt-token
```

**Request Body:**
```json
{
  "identifier": "john@example.com",
  "type": "email",
  "purpose": "registration"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "OTP sent successfully.",
  "expires_at": "2024-01-15T10:35:00Z"
}
```

#### Verify OTP
```http
POST /auth/otp/verify
Authorization: Bearer jwt-token
```

**Request Body:**
```json
{
  "identifier": "john@example.com",
  "otp_code": "123456",
  "type": "email",
  "purpose": "registration"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "OTP verified successfully."
}
```

## ❌ Error Responses

### Validation Error (422 Unprocessable Entity)
```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password field must be at least 8 characters."]
  }
}
```

### Authentication Error (401 Unauthorized)
```json
{
  "success": false,
  "message": "Invalid credentials."
}
```

### Rate Limit Error (429 Too Many Requests)
```json
{
  "success": false,
  "message": "Rate limit exceeded. Please try again later.",
  "error_code": "RATE_LIMIT_EXCEEDED"
}
```

### Account Locked Error (423 Locked)
```json
{
  "success": false,
  "message": "Account is temporarily locked due to multiple failed login attempts.",
  "error_code": "ACCOUNT_LOCKED",
  "locked_until": "2024-01-15T11:00:00Z"
}
```

### Server Error (500 Internal Server Error)
```json
{
  "success": false,
  "message": "Authentication failed due to server error."
}
```

## 🔒 Security Features

### Rate Limiting
- **Login attempts**: 5 attempts per 15 minutes per IP
- **Registration**: 100 requests per hour per IP
- **Password reset**: 5 requests per hour per email
- **OTP generation**: 10 requests per hour per user

### Account Locking
- Account locked after 5 failed login attempts
- Lock duration: 15 minutes
- Automatic unlock after lock period expires

### Token Security
- JWT tokens expire after 24 hours
- Refresh tokens valid for 14 days
- Tokens are revoked on logout
- Secure token storage recommended

### Password Requirements
- Minimum 8 characters
- Must contain uppercase letters
- Must contain lowercase letters
- Must contain numbers
- Must contain special characters

## 📱 Frontend Integration

### JavaScript Example
```javascript
// Login with email/password
const login = async (email, password) => {
  const response = await fetch('/api/auth/login', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ email, password }),
  });
  
  const data = await response.json();
  
  if (data.success) {
    localStorage.setItem('auth_token', data.token);
    // Redirect to dashboard
  }
};

// OAuth login
const loginWithGoogle = async () => {
  const response = await fetch('/api/auth/google');
  const data = await response.json();
  
  if (data.success) {
    window.location.href = data.auth_url;
  }
};

// Make authenticated requests
const makeAuthenticatedRequest = async (url) => {
  const token = localStorage.getItem('auth_token');
  
  const response = await fetch(url, {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
  });
  
  return response.json();
};
```

## 🧪 Testing

### Using cURL

#### Register User
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "TestPassword123!",
    "password_confirmation": "TestPassword123!"
  }'
```

#### Login User
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "TestPassword123!"
  }'
```

#### Get User Profile
```bash
curl -X GET http://localhost:8000/api/auth/user \
  -H "Authorization: Bearer your-jwt-token-here"
```

## 📞 Support

For API support:
- Check error responses for specific error codes
- Review rate limiting if getting 429 errors
- Ensure proper Authorization headers for protected endpoints
- Verify OAuth configuration for social login issues