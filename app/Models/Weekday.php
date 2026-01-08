<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $id
 */
class Weekday extends Model
{
    protected $fillable = [
        'name',
        'day'
    ];
}
