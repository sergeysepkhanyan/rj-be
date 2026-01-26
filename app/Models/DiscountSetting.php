<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountSetting extends Model
{
    protected $table = 'discount_settings';

    protected $fillable = [
        'quantity_threshold',
        'discount_percentage',
        'discount_label',
        'enabled',
    ];

    protected $casts = [
        'quantity_threshold' => 'integer',
        'discount_percentage' => 'decimal:2',
        'enabled' => 'boolean',
    ];
}
