<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionRecord extends Model
{
    use HasFactory;

    public $timestamps = false; // Disable automatic timestamps

    protected $fillable = [
        'user_id',
        'transaction_type',
        'cryptocurrency_symbol',
        'amount',
        'balance_before',
        'balance_after',
        'reason',
        'description',
        'reference_id',
        'metadata',
        'created_at'
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'balance_before' => 'decimal:8',
        'balance_after' => 'decimal:8',
        'metadata' => 'array'
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