<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed $id
 */
class SubServiceItem extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'sub_service_id',
        'name',
        'name_ar',
        'price',
        'currency',
        'duration',
        'duration_unit',
        'show_duration',
        'vat_enabled',
        'discount',
        'discount_type',
        'discount_amount'
    ];

    protected $casts = [
        'vat_enabled' => 'boolean',
        'show_duration' => 'boolean',
        'discount' => 'boolean',
        'discount_amount' => 'decimal:2',
    ];

    public function subService(): BelongsTo
    {
        return $this->belongsTo(SubService::class);
    }

    public function getFinalPrice(): float
    {
        $price = (float) $this->price;
        if (!$this->discount || !$this->discount_amount || $this->discount_amount <= 0) {
            return $price;
        }
        if ($this->discount_type === 'percentage') {
            return max(0, $price - ($price * (float) $this->discount_amount / 100));
        }
        return max(0, $price - (float) $this->discount_amount);
    }

    public function hasDiscount(): bool
    {
        return $this->discount && $this->discount_amount && $this->discount_amount > 0;
    }

    public function bookingServices(): MorphMany
    {
        return $this->morphMany(BookingService::class, 'bookable');
    }
}
