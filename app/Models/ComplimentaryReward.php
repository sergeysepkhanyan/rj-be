<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplimentaryReward extends Model
{
    protected $fillable = [
        'user_id',
        'sub_service_id',
        'sub_service_item_id',
        'status',
        'earned_at',
        'redeemed_at',
        'redeemed_booking_id',
        'expires_at',
    ];

    protected $casts = [
        'earned_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subService(): BelongsTo
    {
        return $this->belongsTo(SubService::class);
    }

    public function subServiceItem(): BelongsTo
    {
        return $this->belongsTo(SubServiceItem::class);
    }

    /**
     * The reward's service name, whether it is a sub-service or a specific item.
     * Named resolve* (not serviceName) to avoid Eloquent treating a `serviceName`
     * property access as a relationship.
     */
    public function resolveServiceName(): ?string
    {
        return $this->subService?->name ?? $this->subServiceItem?->name;
    }

    public function redeemedBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'redeemed_booking_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    public function scopeRedeemed(Builder $query): Builder
    {
        return $query->where('status', 'redeemed');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'expired');
    }
}
