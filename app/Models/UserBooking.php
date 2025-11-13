<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserBooking extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'client_id',
        'master_id',
        'payment_type',
        'discount_type',
        'discount_amount',
        'discount',
        'payment_amount',
        'payment_currency',
        'payment_status',
        'sub_service_id',
        'date',
        'time',
        'name',
        'email',
        'mobile',
        'notes',
        'type',
        'duration'
    ];
}
