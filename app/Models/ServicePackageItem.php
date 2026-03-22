<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePackageItem extends Model
{
    protected $fillable = [
        'service_package_id',
        'sub_service_id',
        'total_visits',
        'daily_limit',
    ];

    public function servicePackage(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class);
    }

    public function subService(): BelongsTo
    {
        return $this->belongsTo(SubService::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(ServicePackageUsage::class);
    }

    public function isUnlimited(): bool
    {
        return $this->total_visits === 0;
    }
}
