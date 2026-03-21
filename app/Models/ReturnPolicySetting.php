<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $id
 * @property mixed $return_window_days
 * @property mixed $is_active
 */
class ReturnPolicySetting extends Model
{
    protected $table = 'return_policy_settings';

    protected $fillable = [
        'return_window_days',
        'is_active',
    ];

    protected $casts = [
        'return_window_days' => 'integer',
        'is_active' => 'boolean',
    ];
}
