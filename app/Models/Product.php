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
        'discount' => 'decimal:2',
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
     * Get the final price after applying the product-level discount.
     *
     * Both `discount` and `discount_amount` are legacy decimal columns.
     * We cast each to float and compare against 0 EXPLICITLY — never use
     * PHP string truthiness, because "0.00" is truthy in PHP and that's
     * exactly what produced the previous "discount applied while the
     * toggle was off" bug.
     */
    public function getFinalPrice(): float
    {
        $price = (float) $this->price;
        $amount = (float) $this->discount_amount;
        $flag = (float) $this->discount;

        if ($flag <= 0 || $amount <= 0) {
            return round($price, 2);
        }

        if ($this->discount_type === 'percentage' || $this->discount_type === 'percent') {
            $discountAmount = $price * ($amount / 100);
            return round(max(0, $price - $discountAmount), 2);
        }

        // Fixed amount discount
        return round(max(0, $price - $amount), 2);
    }

    /**
     * Check if the product has an active product-level discount.
     * Same rule as getFinalPrice() — numeric `> 0` on BOTH columns.
     */
    public function hasDiscount(): bool
    {
        return ((float) $this->discount) > 0 && ((float) $this->discount_amount) > 0;
    }

    /**
     * Get the price that a specific user should see, applying BOTH the
     * product-level discount (if active) AND that user's Product Discount
     * Tier percentage (if any). The tier discount compounds on top of the
     * product-level final price. Passing `null` returns the public final
     * price (tier not applied), so anonymous visitors and the public
     * storefront always see the same value.
     */
    public function getFinalPriceForUser(?\App\Models\User $user = null): float
    {
        $final = $this->getFinalPrice();
        $tierPercent = $this->getTierDiscountPercentForUser($user);

        if ($tierPercent > 0) {
            $final = $final * (1 - $tierPercent / 100);
        }

        return round(max(0, $final), 2);
    }

    /**
     * Returns the tier discount percentage for a user, or 0 if none. Kept
     * on the model so every resource / service can share one implementation
     * without dragging the tier service around.
     */
    public function getTierDiscountPercentForUser(?\App\Models\User $user): float
    {
        if (!$user) {
            return 0.0;
        }
        $user->loadMissing('productDiscountTier');
        $tier = $user->productDiscountTier;
        if (!$tier || !$tier->enabled) {
            return 0.0;
        }
        return (float) $tier->discount_percentage;
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
