<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCardPurchase extends Model
{
    protected $fillable = [
        'gift_card_id',
        'order_id',
        'code',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'recipient_name',
        'recipient_email',
        'amount',
        'balance',
        'currency',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(GiftCardUsage::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isFullyUsed(): bool
    {
        return $this->balance <= 0;
    }

    public static function generateCode(): string
    {
        do {
            $code = 'GC-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
