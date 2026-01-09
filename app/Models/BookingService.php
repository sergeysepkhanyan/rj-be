<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BookingService extends Model
{
    protected $fillable = [
        'booking_id',
        'master_id',
        'is_any_master',
        'bookable_id',
        'bookable_type',
        'duration_minutes',
        'price',
        'base_price',
        'vat_enabled',
        'vat_rate',
        'vat_amount',
        'final_price',
        'sort_order',
        'date',
        'timezone',
        'start_time',
        'end_time',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(User::class, 'master_id');
    }
}

