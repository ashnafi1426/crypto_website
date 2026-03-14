<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'provider',
        'provider_id',
        'avatar',
        'email_verification_token',
        'password_reset_token',
        'password_reset_expires_at',
        'is_admin',
        'failed_login_attempts',
        'status',
        'kyc_status',
        'kyc_approved_at',
        'locked_until',
        'referred_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'locked_until' => 'datetime',
            'kyc_approved_at' => 'datetime',
            'password_reset_expires_at' => 'datetime',
        ];
    }

    /**
     * Get the wallets for the user.
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    /**
     * Get the orders for the user.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the trades for the user.
     */
    public function trades(): HasMany
    {
        return $this->hasMany(Trade::class);
    }

    /**
     * Get the transaction records for the user.
     */
    public function transactionRecords(): HasMany
    {
        return $this->hasMany(TransactionRecord::class);
    }

    /**
     * Get the KYC documents for the user.
     */
    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    /**
     * Get the support tickets for the user.
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Get the referral program for the user.
     */
    public function referralProgram(): HasOne
    {
        return $this->hasOne(ReferralProgram::class);
    }

    /**
     * Get the investments for the user.
     */
    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    /**
     * Get the user who referred this user.
     */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /**
     * Get users referred by this user.
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    /**
     * Get the user's portfolio value.
     */
    public function getPortfolioValueAttribute(): string
    {
        return $this->wallets()
            ->join('cryptocurrencies', 'wallets.cryptocurrency_symbol', '=', 'cryptocurrencies.symbol')
            ->selectRaw('SUM(wallets.balance * cryptocurrencies.current_price) as total_value')
            ->value('total_value') ?? '0.00000000';
    }

    /**
     * Check if the user account is locked.
     */
    public function isLocked(): bool
    {
        return $this->locked_until?->isFuture() ?? false;
    }

    /**
     * Lock the user account for the specified duration.
     */
    public function lockAccount(int $minutes = 15): void
    {
        $this->update([
            'locked_until' => now()->addMinutes($minutes),
            'failed_login_attempts' => ($this->failed_login_attempts ?? 0) + 1,
        ]);
    }

    /**
     * Reset failed login attempts.
     */
    public function resetFailedAttempts(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->is_admin ?? false;
    }

    /**
     * Check if user has completed KYC.
     */
    public function hasCompletedKyc(): bool
    {
        return $this->kyc_status === 'approved';
    }

    /**
     * Get user's active referral code.
     */
    public function getReferralCodeAttribute(): ?string
    {
        return $this->referralProgram?->referral_code;
    }

    /**
     * Check if user is a social login user.
     */
    public function isSocialUser(): bool
    {
        return in_array($this->provider, ['google', 'apple']);
    }

    /**
     * Check if user is a local user.
     */
    public function isLocalUser(): bool
    {
        return $this->provider === 'local';
    }

    /**
     * Generate email verification token.
     */
    public function generateEmailVerificationToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->update(['email_verification_token' => $token]);
        return $token;
    }

    /**
     * Generate password reset token.
     */
    public function generatePasswordResetToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->update([
            'password_reset_token' => $token,
            'password_reset_expires_at' => now()->addHours(1),
        ]);
        return $token;
    }

    /**
     * Clear password reset token.
     */
    public function clearPasswordResetToken(): void
    {
        $this->update([
            'password_reset_token' => null,
            'password_reset_expires_at' => null,
        ]);
    }

    /**
     * Verify email using token.
     */
    public function verifyEmail(string $token): bool
    {
        if ($this->email_verification_token === $token) {
            $this->update([
                'email_verified_at' => now(),
                'email_verification_token' => null,
            ]);
            return true;
        }
        return false;
    }
}
