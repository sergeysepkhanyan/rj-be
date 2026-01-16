<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property mixed $id
 * @property mixed $amount
 * @property mixed $currency
 */
class Order extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'orderable_type',
        'orderable_id',
        'amount',
        'currency',
        'status',
        'reference',
        'meta',
        'paid_at',
        'cancelled_at',
        'refunded_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderable(): MorphTo
    {
        return $this->morphTo();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

}
