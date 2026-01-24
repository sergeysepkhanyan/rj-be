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
 * @property mixed $type
 * @property mixed $delivery_status
 * @property mixed $user_id
 * @property mixed $user
 * @property mixed $reference
 * @property mixed $status
 * @property mixed $created_at
 * @property mixed $paid_at
 * @property mixed $items
 * @property mixed $shippingAddress
 * @property mixed $billingAddress
 * @property mixed $latestPayment
 * @property mixed $orderable
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
        'delivery_status',
        'reference',
        'meta',
        'paid_at',
        'cancelled_at',
        'refunded_at',
        'delivery_status_updated_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'delivery_status_updated_at' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function deliveryAddress(): HasOne
    {
        return $this->hasOne(Address::class, 'order_id');
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(Address::class, 'order_id')->where('type', 'shipping');
    }

    public function billingAddress(): HasOne
    {
        return $this->hasOne(Address::class, 'order_id')->where('type', 'billing');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'order_id')->orderBy('created_at');
    }
}
