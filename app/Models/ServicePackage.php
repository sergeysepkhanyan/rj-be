<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePackage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'description_ar',
        'image',
        'price',
        'currency',
        'validity_days',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ServicePackageItem::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(ServicePackagePurchase::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
