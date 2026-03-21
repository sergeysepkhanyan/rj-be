<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property mixed $id
 * @property mixed $order_id
 * @property mixed $reason
 * @property mixed $pickup_address_id
 * @property mixed $pickup_address_snapshot
 * @property mixed $status
 * @property mixed $admin_notes
 * @property mixed $approved_by
 * @property mixed $approved_at
 * @property mixed $rejected_by
 * @property mixed $rejected_at
 * @property mixed $created_at
 * @property mixed $updated_at
 */
class OrderReturn extends Model
{
    protected $fillable = [
        'order_id',
        'reason',
        'pickup_address_id',
        'pickup_address_snapshot',
        'status',
        'admin_notes',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
    ];

    protected $casts = [
        'pickup_address_snapshot' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function pickupAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'pickup_address_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }
}
