<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BookingService extends Model
{
    protected $fillable = [
        'booking_id',
        'bookable_id',
        'bookable_type',
        'duration_minutes',
        'price',
        'sort_order',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }
}

