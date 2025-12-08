<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserBookingSubserviceItem extends Model
{

    protected $fillable = [
        'user_booking_id',
        'bookable_id',
        'bookable_type',
    ];
    public function bookable(): MorphTo
    {
        return $this->morphTo();
    }
}
