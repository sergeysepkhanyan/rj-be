<?php

namespace App\Models;

use App\Traits\DeletesImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use DeletesImages;
    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'description_ar',
        'product_category_id',
        'supplier_id',
        'sku_id',
        'max_quantity',
        'reorder_point',
        'price',
        'cost_price',
        'currency',
        'main_image',
        'referral_id',
        'discount',
        'discount_type',
        'discount_amount',
        'status',
        'production_date',
        'expiry_date',
        'unit_of_sale',
        'sales_channel',
        'product_type',
        'meta_title',
        'meta_title_ar',
        'meta_description',
        'meta_description_ar',
        'slug',
        'redirect_url',
    ];

    protected $casts = [
        'production_date' => 'date',
        'expiry_date' => 'date',
        'cost_price' => 'decimal:2',
        'price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isLowStock(): bool
    {
        return $this->max_quantity <= $this->reorder_point;
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->expiry_date) {
            return false;
        }
        return $this->expiry_date->diffInDays(now()) <= $days && $this->expiry_date->isFuture();
    }

    public function isExpired(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }
        return $this->expiry_date->isPast();
    }

    public function getProfitMargin(): ?float
    {
        if (!$this->cost_price || $this->cost_price <= 0) {
            return null;
        }
        return (($this->price - $this->cost_price) / $this->cost_price) * 100;
    }

    /**
     * Get the final price after applying discount.
     */
    public function getFinalPrice(): float
    {
        $price = (float) $this->price;

        if (!$this->discount || !$this->discount_amount || $this->discount_amount <= 0) {
            return $price;
        }

        if ($this->discount_type === 'percentage' || $this->discount_type === 'percent') {
            $discountAmount = $price * ((float) $this->discount_amount / 100);
            return max(0, $price - $discountAmount);
        }

        // Fixed amount discount
        return max(0, $price - (float) $this->discount_amount);
    }

    /**
     * Check if product has an active discount.
     */
    public function hasDiscount(): bool
    {
        return $this->discount && $this->discount_amount && $this->discount_amount > 0;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug) && !empty($product->name)) {
                $product->slug = static::generateUniqueSlug($product->name);
            }
        });

        static::updating(function ($product) {
            if ($product->isDirty('name') && empty($product->slug)) {
                $product->slug = static::generateUniqueSlug($product->name);
            }
        });
    }

    protected static function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
