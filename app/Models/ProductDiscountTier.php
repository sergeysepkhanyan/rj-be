<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductDiscountTier extends Model
{
    protected $fillable = [
        'name',
        'name_ar',
        'spend_threshold',
        'discount_percentage',
        'enabled',
    ];

    protected $casts = [
        'spend_threshold' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'enabled' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'product_discount_tier_id');
    }
}
