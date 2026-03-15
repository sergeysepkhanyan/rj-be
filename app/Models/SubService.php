<?php

namespace App\Models;

use App\Traits\DeletesImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\HigherOrderCollectionProxy;

/**
 * @property mixed $type
 * @property mixed $id
 * @property HigherOrderCollectionProxy|mixed $masters
 */
class SubService extends Model
{
    use SoftDeletes, DeletesImages;
    protected $fillable = [
        'service_id',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'image',
        'price',
        'currency',
        'duration',
        'duration_unit',
        'show_duration',
        'type',
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubServiceItem::class);
    }

    public function bookingServices(): MorphMany
    {
        return $this->morphMany(BookingService::class, 'bookable');
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

    public function masters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_sub_services', 'sub_service_id', 'user_id')
            ->withTimestamps();
    }
}
