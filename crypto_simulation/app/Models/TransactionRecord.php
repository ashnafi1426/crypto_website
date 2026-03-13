<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'cryptocurrency_symbol',
        'amount',
        'fee',
        'status',
        'description',
        'reference_id',
        'wallet_address',
        'transaction_hash',
        'processed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'fee' => 'decimal:8',
        'processed_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cryptocurrency(): BelongsTo
    {
        return $this->belongsTo(Cryptocurrency::class, 'cryptocurrency_symbol', 'symbol');
    }
}