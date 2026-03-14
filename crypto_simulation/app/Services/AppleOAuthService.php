<?php

namespace App\Services;

use App\Models\User;
use App\Models\OAuthSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AppleOAuthService
{
    private string $clientId;
    private string $teamId;
    private string $keyId;
    private string $privateKeyPath;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId = config('services.apple.client_id');
        $this->teamId = config('services.apple.team_id');
        $this->keyId = config('services.apple.key_id');
        $this->privateKeyPath = config('services.apple.private_key_path');
        $this->redirectUri = config('services.apple.redirect_uri');
    }

    /**
     * Generate Apple OAuth URL.
     */
    public function getAuthUrl(?string $redirectUrl = null): array
    {
        $session = OAuthSession::createSession('apple', $redirectUrl);

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'name email',
            'response_mode' => 'form_post',
            'state' => $session->state,
        ];

        $url = 'https://appleid.apple.com/auth/authorize?' . http_build_query($params);

        return [
            'url' => $url,
            'state' => $session->state,
        ];
    }

    /**
     * Handle Apple OAuth callback.
     */
    public function handleCallback(string $code, string $state, ?string $user = null): array
    {
        try {
            // Verify state parameter
            $session = OAuthSession::findByState($state, 'apple');
            if (!$session) {
                throw new \Exception('Invalid or expired OAuth state');
            }

            // Exchange code for tokens
            $tokenResponse = $this->exchangeCodeForToken($code);
            
            // Decode and verify identity token
            $userProfile = $this->verifyIdentityToken($tokenResponse['id_token'], $user);
            
            // Find or create user
            $user = $this->findOrCreateUser($userProfile);
            
            // Generate JWT token
            $token = $user->createToken('apple-oauth')->plainTextToken;
            
            // Clean up OAuth session
            $session->delete();

            return [
                'success' => true,
                'user' => $user,
                'token' => $token,
                'redirect_url' => $session->redirect_url,
            ];

        } catch (\Exception $e) {
            Log::error('Apple OAuth callback error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'OAuth authentication failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Exchange authorization code for tokens.
     */
    private function exchangeCodeForToken(string $code): array
    {
        $clientSecret = $this->generateClientSecret();

        $response = Http::withOptions([
            'verify' => false, // Disable SSL verification for development
            'timeout' => 30,
        ])->asForm()->post('https://appleid.apple.com/auth/token', [
            'client_id' => $this->clientId,
            'client_secret' => $clientSecret,
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
     * Generate client secret JWT for Apple.
     */
    private function generateClientSecret(): string
    {
        $privateKey = file_get_contents(storage_path($this->privateKeyPath));
        
        if (!$privateKey) {
            throw new \Exception('Apple private key not found');
        }

        $payload = [
            'iss' => $this->teamId,
            'iat' => time(),
            'exp' => time() + 3600, // 1 hour
            'aud' => 'https://appleid.apple.com',
            'sub' => $this->clientId,
        ];

        $header = [
            'alg' => 'ES256',
            'kid' => $this->keyId,
        ];

        return JWT::encode($payload, $privateKey, 'ES256', null, $header);
    }

    /**
     * Verify and decode Apple identity token.
     */
    private function verifyIdentityToken(string $idToken, ?string $userJson = null): array
    {
        // Get Apple's public keys
        $appleKeys = $this->getApplePublicKeys();
        
        // Decode token header to get key ID
        $tokenParts = explode('.', $idToken);
        $header = json_decode(base64_decode($tokenParts[0]), true);
        
        if (!isset($header['kid'])) {
            throw new \Exception('Invalid token header');
        }

        // Find matching public key
        $publicKey = null;
        foreach ($appleKeys['keys'] as $key) {
            if ($key['kid'] === $header['kid']) {
                $publicKey = $this->convertJWKToPEM($key);
                break;
            }
        }

        if (!$publicKey) {
            throw new \Exception('Public key not found');
        }

        // Verify and decode token
        try {
            $decoded = JWT::decode($idToken, new Key($publicKey, 'RS256'));
            $payload = (array) $decoded;
        } catch (\Exception $e) {
            throw new \Exception('Token verification failed: ' . $e->getMessage());
        }

        // Verify token claims
        if ($payload['iss'] !== 'https://appleid.apple.com') {
            throw new \Exception('Invalid token issuer');
        }

        if ($payload['aud'] !== $this->clientId) {
            throw new \Exception('Invalid token audience');
        }

        if ($payload['exp'] < time()) {
            throw new \Exception('Token expired');
        }

        // Parse user data if provided (only on first login)
        $userData = [];
        if ($userJson) {
            $userData = json_decode($userJson, true);
        }

        return [
            'id' => $payload['sub'],
            'email' => $payload['email'] ?? null,
            'email_verified' => $payload['email_verified'] ?? false,
            'name' => $userData['name']['firstName'] ?? '' . ' ' . $userData['name']['lastName'] ?? '',
        ];
    }

    /**
     * Get Apple's public keys for token verification.
     */
    private function getApplePublicKeys(): array
    {
        $response = Http::withOptions([
            'verify' => false, // Disable SSL verification for development
            'timeout' => 30,
        ])->get('https://appleid.apple.com/auth/keys');
        
        if (!$response->successful()) {
            throw new \Exception('Failed to get Apple public keys');
        }

        return $response->json();
    }

    /**
     * Convert JWK to PEM format.
     */
    private function convertJWKToPEM(array $jwk): string
    {
        // Decode base64url encoded values
        $n = $this->base64UrlDecode($jwk['n']);
        $e = $this->base64UrlDecode($jwk['e']);
        
        // Create RSA public key from modulus and exponent
        $rsa = \phpseclib3\Crypt\PublicKeyLoader::load([
            'n' => new \phpseclib3\Math\BigInteger($n, 256),
            'e' => new \phpseclib3\Math\BigInteger($e, 256)
        ]);
        
        return $rsa->toString('PKCS8');
    }
    
    /**
     * Base64 URL decode.
     */
    private function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * Find or create user from Apple profile.
     */
    private function findOrCreateUser(array $profile): User
    {
        // First, try to find user by provider and provider_id
        $user = User::where('provider', 'apple')
            ->where('provider_id', $profile['id'])
            ->first();

        if ($user) {
            return $user;
        }

        // Check if user exists with same email but different provider
        if ($profile['email']) {
            $existingUser = User::where('email', $profile['email'])->first();
            
            if ($existingUser) {
                // Link Apple account to existing user
                $existingUser->update([
                    'provider' => 'apple',
                    'provider_id' => $profile['id'],
                    'email_verified_at' => $profile['email_verified'] ? now() : null,
                ]);
                return $existingUser;
            }
        }

        // Create new user
        $user = User::create([
            'name' => trim($profile['name']) ?: 'Apple User',
            'email' => $profile['email'],
            'provider' => 'apple',
            'provider_id' => $profile['id'],
            'email_verified_at' => $profile['email_verified'] ? now() : null,
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