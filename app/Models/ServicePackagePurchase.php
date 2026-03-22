<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePackagePurchase extends Model
{
    protected $fillable = [
        'service_package_id',
        'user_id',
        'order_id',
        'code',
        'status',
        'purchased_at',
        'expires_at',
        'expiry_warning_sent_at',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
        'expiry_warning_sent_at' => 'datetime',
    ];

    public function servicePackage(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(ServicePackageUsage::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isFullyUsed(): bool
    {
        $package = $this->servicePackage()->with('items')->first();
        if (!$package) {
            return false;
        }

        foreach ($package->items as $item) {
            if ($item->isUnlimited()) {
                return false; // Unlimited items can never be fully used
            }

            $usedCount = $this->usages()
                ->where('service_package_item_id', $item->id)
                ->count();

            if ($usedCount < $item->total_visits) {
                return false;
            }
        }

        return true;
    }

    public static function generateCode(): string
    {
        do {
            $code = 'PKG-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function getRemainingVisits(int $itemId): int
    {
        $item = ServicePackageItem::find($itemId);
        if (!$item) {
            return 0;
        }

        if ($item->isUnlimited()) {
            return -1; // -1 indicates unlimited
        }

        $usedCount = $this->usages()
            ->where('service_package_item_id', $itemId)
            ->count();

        return max(0, $item->total_visits - $usedCount);
    }

    public function canRedeemToday(int $itemId): bool
    {
        $item = ServicePackageItem::find($itemId);
        if (!$item) {
            return false;
        }

        // For unlimited services, check daily limit
        if ($item->isUnlimited()) {
            $todayCount = $this->usages()
                ->where('service_package_item_id', $itemId)
                ->whereDate('used_at', today())
                ->count();

            return $todayCount < $item->daily_limit;
        }

        // For limited services, just check remaining visits
        return $this->getRemainingVisits($itemId) > 0;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
