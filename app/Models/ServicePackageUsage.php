<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePackageUsage extends Model
{
    protected $fillable = [
        'service_package_purchase_id',
        'service_package_item_id',
        'booking_id',
        'used_at',
        'notes',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(ServicePackagePurchase::class, 'service_package_purchase_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ServicePackageItem::class, 'service_package_item_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
