<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $start_time
 * @property mixed $end_time
 */
class BusinessHour extends Model
{
    protected $fillable = ['start_time', 'end_time'];
}
