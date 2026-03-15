<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deposit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'currency',
        'type',
        'amount',
        'fee',
        'net_amount',
        'wallet_address',
        'from_address',
        'txid',
        'confirmations',
        'required_confirmations',
        'block_number',
        'transaction_fee',
        'payment_method',
        'payment_reference',
        'payment_details',
        'status',
        'confirmed_at',
        'completed_at',
        'processed_at',
        'notes',
        'processed_by',
        'metadata',
        'transaction_image',
        'network',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'fee' => 'decimal:8',
        'net_amount' => 'decimal:8',
        'transaction_fee' => 'decimal:8',
        'payment_details' => 'array',
        'metadata' => 'array',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirming($query)
    {
        return $query->where('status', 'confirming');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCrypto($query)
    {
        return $query->where('type', 'crypto');
    }

    public function scopeFiat($query)
    {
        return $query->where('type', 'fiat');
    }

    // Accessors
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getIsConfirmingAttribute(): bool
    {
        return $this->status === 'confirming';
    }

    public function getConfirmationProgressAttribute(): float
    {
        if ($this->required_confirmations <= 0) {
            return 100;
        }
        
        return min(100, ($this->confirmations / $this->required_confirmations) * 100);
    }

    // Methods
    public function markAsConfirming(): void
    {
        $this->update([
            'status' => 'confirming',
            'confirmed_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(?string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'notes' => $reason,
        ]);
    }

    public function updateConfirmations(int $confirmations): void
    {
        $this->update(['confirmations' => $confirmations]);
        
        if ($confirmations >= $this->required_confirmations && $this->status === 'confirming') {
            $this->markAsCompleted();
        }
    }

    // ==================== EDUCATIONAL METHODS ====================
    // ⚠️ FOR EDUCATIONAL PURPOSES ONLY ⚠️

    /**
     * Get educational warning for this deposit
     */
    public function getEducationalWarning(): string
    {
        if ($this->type === 'crypto') {
            return 'In real scams, cryptocurrency sent to this address goes directly to the scammer\'s wallet.';
        } elseif ($this->type === 'fiat') {
            return 'In real scams, fiat payments are collected by scammers while fake balances are shown to users.';
        }
        
        return 'This deposit demonstrates how scammers collect real money while showing fake balances.';
    }

    /**
     * Get status display name with educational context
     */
    public function getStatusDisplayName(): string
    {
        return match($this->status) {
            'pending' => 'Pending Confirmation',
            'confirming' => 'Confirming (Fake Process)',
            'completed' => 'Completed (Fake Balance Credited)',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status)
        };
    }

    /**
     * Get deposit type display name
     */
    public function getTypeDisplayName(): string
    {
        return match($this->type) {
            'crypto' => 'Cryptocurrency',
            'fiat' => 'Fiat Currency',
            'bank_transfer' => 'Bank Transfer',
            'credit_card' => 'Credit Card',
            'paypal' => 'PayPal',
            default => ucfirst($this->type)
        };
    }

    /**
     * Get blockchain explorer URL (educational - may be fake)
     */
    public function getBlockchainExplorerUrl(): ?string
    {
        if (!$this->txid || $this->type !== 'crypto') {
            return null;
        }

        $explorers = [
            'BTC' => 'https://blockchair.com/bitcoin/transaction/',
            'ETH' => 'https://etherscan.io/tx/',
            'USDT' => 'https://etherscan.io/tx/',
            'LTC' => 'https://blockchair.com/litecoin/transaction/',
            'ADA' => 'https://cardanoscan.io/transaction/',
            'DOT' => 'https://polkascan.io/polkadot/transaction/',
        ];

        $baseUrl = $explorers[$this->currency] ?? null;
        
        return $baseUrl ? $baseUrl . $this->txid : null;
    }

    /**
     * Check if this deposit has a fake transaction ID
     */
    public function hasFakeTransactionId(): bool
    {
        // In educational context, we can mark certain patterns as fake
        if (!$this->txid) {
            return false;
        }

        // Simple check for obviously fake patterns
        return str_contains($this->txid, 'fake_') || 
               str_contains($this->txid, 'test_') ||
               str_contains($this->txid, 'educational_');
    }

    /**
     * Get educational notes about this deposit
     */
    public function getEducationalNotes(): array
    {
        $notes = [];

        if ($this->type === 'crypto') {
            $notes[] = 'Real cryptocurrency deposits go to scammer-controlled addresses';
            $notes[] = 'Transaction IDs may be fake or point to unrelated transactions';
            $notes[] = 'Confirmations are often simulated to appear legitimate';
        }

        if ($this->type === 'fiat') {
            $notes[] = 'Fiat deposits collect real payment information';
            $notes[] = 'Bank details and card information are harvested';
            $notes[] = 'Processing delays are used to buy time before disappearing';
        }

        $notes[] = 'User balances are updated in database without real backing';
        $notes[] = 'Withdrawal attempts will be blocked with various excuses';

        return $notes;
    }
}