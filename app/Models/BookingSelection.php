<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BookingSelection extends Model
{
    protected $fillable = [
        'user_id',
        'guest_session_id',
        'master_id',
        'bookable_type',
        'bookable_id',
        'duration_minutes',
        'date',
        'timezone',
        'start_time',
        'end_time',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(User::class, 'master_id');
    }

    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }
}
