<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'subscription_id',
        'amount',
        'currency_id',
        'payment_date',
        'period_start',
        'period_end',
        'payment_method',
        'payments_id',
        'status',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}