<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'cryptocurrency_symbol',
        'balance',
        'reserved_balance',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:8',
            'reserved_balance' => 'decimal:8',
        ];
    }

    /**
     * Get the user that owns the wallet.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the cryptocurrency for this wallet.
     */
    public function cryptocurrency(): BelongsTo
    {
        return $this->belongsTo(Cryptocurrency::class, 'cryptocurrency_symbol', 'symbol');
    }

    /**
     * Get the available balance (total balance minus reserved).
     */
    public function getAvailableBalanceAttribute(): string
    {
        return bcsub($this->balance, $this->reserved_balance, 8);
    }

    /**
     * Get the wallet value in USD.
     */
    public function getValueAttribute(): string
    {
        return bcmul($this->balance, $this->cryptocurrency->current_price, 8);
    }

    /**
     * Update the wallet balance.
     */
    public function updateBalance(string $amount, string $reason = 'balance_update'): void
    {
        $newBalance = bcadd($this->balance, $amount, 8);
        
        if (bccomp($newBalance, '0', 8) < 0) {
            throw new \InvalidArgumentException('Insufficient balance for this operation');
        }

        $this->update(['balance' => $newBalance]);

        // Create transaction record
        TransactionRecord::create([
            'user_id' => $this->user_id,
            'cryptocurrency_symbol' => $this->cryptocurrency_symbol,
            'amount' => $amount,
            'transaction_type' => $this->getTransactionType($amount),
            'reason' => $reason,
            'metadata' => [
                'previous_balance' => $this->getOriginal('balance'),
                'new_balance' => $newBalance,
            ],
        ]);
    }

    /**
     * Reserve balance for pending orders.
     */
    public function reserveBalance(string $amount): void
    {
        if (bccomp($this->available_balance, $amount, 8) < 0) {
            throw new \InvalidArgumentException('Insufficient available balance to reserve');
        }

        $this->update([
            'reserved_balance' => bcadd($this->reserved_balance, $amount, 8)
        ]);
    }

    /**
     * Release reserved balance.
     */
    public function releaseReservedBalance(string $amount): void
    {
        $newReserved = bcsub($this->reserved_balance, $amount, 8);
        
        if (bccomp($newReserved, '0', 8) < 0) {
            $newReserved = '0.00000000';
        }

        $this->update(['reserved_balance' => $newReserved]);
    }

    /**
     * Get transaction type based on amount.
     */
    private function getTransactionType(string $amount): string
    {
        return bccomp($amount, '0', 8) >= 0 ? 'deposit' : 'withdrawal';
    }
}
