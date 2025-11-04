<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWeekend extends Model
{
    protected $fillable = [
        'user_id',
        'weekday_id',
    ];
}
