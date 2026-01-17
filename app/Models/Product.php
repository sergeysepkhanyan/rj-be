<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'description_ar',
        'product_category_id',
        'sku_id',
        'max_quantity',
        'price',
        'currency',
        'main_image',
        'referral_id',
        'discount',
        'discount_type',
        'discount_amount',
        'status'
    ];

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ProductDetail::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }
}
