<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $fillable = [
        'name',
        'name_ar',
        'type',
        'value'
    ];
}
