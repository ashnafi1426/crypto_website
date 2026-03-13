<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Investment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cryptocurrency_symbol',
        'investment_type',
        'amount',
        'duration_days',
        'expected_return_rate',
        'current_value',
        'status',
        'started_at',
        'maturity_date',
        'completed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'expected_return_rate' => 'decimal:4',
        'current_value' => 'decimal:8',
        'started_at' => 'datetime',
        'maturity_date' => 'datetime',
        'completed_at' => 'datetime'
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