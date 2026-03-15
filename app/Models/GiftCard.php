<?php

namespace App\Models;

use App\Traits\DeletesImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GiftCard extends Model
{
    use SoftDeletes, DeletesImages;

    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'description_ar',
        'price',
        'currency',
        'image',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function purchases(): HasMany
    {
        return $this->hasMany(GiftCardPurchase::class);
    }
}
