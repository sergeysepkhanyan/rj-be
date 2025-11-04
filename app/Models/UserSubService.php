<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSubService extends Model
{
    protected $fillable = [
        'user_id',
        'sub_service_id',
    ];
}
