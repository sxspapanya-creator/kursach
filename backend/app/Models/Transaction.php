<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Transaction extends Model
{
    protected $fillable = [
        'amount',
        'type',
        'description',
        'date',
        'payment_method',
        'user_id',
        'currency_id',
        'is_anomaly',
    ];

    protected $casts = [
        'amount' => 'decimal:6',
        'date' => 'date',
        'is_anomaly' => 'boolean',
    ];

    // Связь с пользователем
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Связь с валютой
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    // Связь "многие ко многим" с категориями
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_transaction');
    }

    public function scopeRegular($query)
    {
        return $query->where('is_anomaly', false);
    }

    public function scopeAnomaly($query)
    {
        return $query->where('is_anomaly', true);
    }
}