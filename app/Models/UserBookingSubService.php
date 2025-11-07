<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBookingSubService extends Model
{
    protected $fillable = [
        'user_booking_id',
        'sub_service_id',
    ];
}
