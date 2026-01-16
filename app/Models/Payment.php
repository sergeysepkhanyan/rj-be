<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'payment_method_id',
        'amount',
        'currency',
        'provider',
        'flow',
        'status',
        'external_id',
        'session_id',
        'checkout_url',
        'idempotency_key',
        'raw',
        'authorized_at',
        'paid_at',
        'failed_at',
        'expired_at',
    ];

    protected $casts = [
        'raw' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
