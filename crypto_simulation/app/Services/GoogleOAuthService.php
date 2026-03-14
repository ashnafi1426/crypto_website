<?php

namespace App\Services;

use App\Models\User;
use App\Models\OAuthSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleOAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId = config('services.google.client_id');
        $this->clientSecret = config('services.google.client_secret');
        $this->redirectUri = config('services.google.redirect_uri');
    }

    /**
     * Generate Google OAuth URL.
     */
    public function getAuthUrl(?string $redirectUrl = null): array
    {
        $session = OAuthSession::createSession('google', $redirectUrl);

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => 'openid email profile',
            'response_type' => 'code',
            'state' => $session->state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

        return [
            'url' => $url,
            'state' => $session->state,
        ];
    }

    /**
     * Handle Google OAuth callback.
     */
    public function handleCallback(string $code, string $state): array
    {
        try {
            // Verify state parameter
            $session = OAuthSession::findByState($state, 'google');
            if (!$session) {
                throw new \Exception('Invalid or expired OAuth state');
            }

            // Exchange code for access token
            $tokenResponse = $this->exchangeCodeForToken($code);
            
            // Get user profile
            $userProfile = $this->getUserProfile($tokenResponse['access_token']);
            
            // Check if user already exists
            $existingUser = User::where('provider', 'google')
                ->where('provider_id', $userProfile['id'])
                ->first();
            
            $isNewUser = !$existingUser;
            
            // Find or create user
            $user = $this->findOrCreateUser($userProfile);
            
            // Generate JWT token
            $token = $user->createToken('google-oauth')->plainTextToken;
            
            // Clean up OAuth session
            $session->delete();

            return [
                'success' => true,
                'user' => $user,
                'token' => $token,
                'redirect_url' => $session->redirect_url,
                'is_new_user' => $isNewUser,
                'requires_otp' => $isNewUser || !$user->email_verified_at, // Require OTP for new users or unverified emails
            ];

        } catch (\Exception $e) {
            Log::error('Google OAuth callback error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'OAuth authentication failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Exchange authorization code for access token.
     */
    private function exchangeCodeForToken(string $code): array
    {
        $response = Http::withOptions([
            'verify' => false, // Disable SSL verification for development
            'timeout' => 30,
        ])->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange code for token: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get user profile from Google.
     */
    private function getUserProfile(string $accessToken): array
    {
        $response = Http::withOptions([
            'verify' => false, // Disable SSL verification for development
            'timeout' => 30,
        ])->withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if (!$response->successful()) {
            throw new \Exception('Failed to get user profile: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Find or create user from Google profile.
     */
    private function findOrCreateUser(array $profile): User
    {
        // First, try to find user by provider and provider_id
        $user = User::where('provider', 'google')
            ->where('provider_id', $profile['id'])
            ->first();

        if ($user) {
            // Update user profile if needed
            $user->update([
                'name' => $profile['name'],
                'avatar' => $profile['picture'] ?? null,
                // Don't auto-verify - require OTP verification
                // 'email_verified_at' => now(),
            ]);
            return $user;
        }

        // Check if user exists with same email but different provider
        $existingUser = User::where('email', $profile['email'])->first();
        
        if ($existingUser) {
            // Link Google account to existing user
            $existingUser->update([
                'provider' => 'google',
                'provider_id' => $profile['id'],
                'avatar' => $profile['picture'] ?? $existingUser->avatar,
                // Don't auto-verify - require OTP verification
                // 'email_verified_at' => now(),
            ]);
            return $existingUser;
        }

        // Create new user - email NOT verified yet, requires OTP
        $user = User::create([
            'name' => $profile['name'],
            'email' => $profile['email'],
            'provider' => 'google',
            'provider_id' => $profile['id'],
            'avatar' => $profile['picture'] ?? null,
            'email_verified_at' => null, // Require OTP verification
            'password' => null, // No password for social login
            'is_admin' => false, // OAuth users are not admin by default
        ]);

        // Initialize user wallets
        $this->initializeUserWallets($user);

        return $user;
    }

    /**
     * Initialize wallets for new user.
     */
    private function initializeUserWallets(User $user): void
    {
        $cryptocurrencies = \App\Models\Cryptocurrency::where('is_active', true)->get();

        foreach ($cryptocurrencies as $crypto) {
            $balance = $crypto->symbol === 'USD' ? '10000.00000000' : '0.00000000';
            
            \App\Models\Wallet::create([
                'user_id' => $user->id,
                'cryptocurrency_symbol' => $crypto->symbol,
                'balance' => $balance,
                'reserved_balance' => '0.00000000',
            ]);
        }
    }
}
