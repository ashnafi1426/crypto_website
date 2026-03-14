<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OAuthSession extends Model
{
    use HasFactory;

    protected $table = 'oauth_sessions';

    protected $fillable = [
        'state',
        'provider',
        'redirect_url',
        'data',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Check if the OAuth session is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Create a new OAuth session.
     */
    public static function createSession(string $provider, ?string $redirectUrl = null, array $data = []): self
    {
        return self::create([
            'state' => bin2hex(random_bytes(16)),
            'provider' => $provider,
            'redirect_url' => $redirectUrl,
            'data' => $data,
            'expires_at' => now()->addMinutes(10), // 10 minutes expiry
        ]);
    }

    /**
     * Find session by state and provider.
     */
    public static function findByState(string $state, string $provider): ?self
    {
        return self::where('state', $state)
            ->where('provider', $provider)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Clean up expired sessions.
     */
    public static function cleanupExpired(): int
    {
        return self::where('expires_at', '<', now())->delete();
    }
}