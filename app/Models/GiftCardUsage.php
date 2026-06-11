<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftCardUsage extends Model
{
    protected $fillable = [
        'gift_card_purchase_id',
        'amount_used',
        'used_for_type',
        'used_for_id',
        'used_for_name',
        'used_for',
        'notes',
        'verified_by',
        'reversed_at',
    ];

    protected $casts = [
        'amount_used' => 'decimal:2',
        'reversed_at' => 'datetime',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(GiftCardPurchase::class, 'gift_card_purchase_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
