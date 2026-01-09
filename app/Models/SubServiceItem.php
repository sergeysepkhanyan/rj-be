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
        'vat_enabled'
    ];

    protected $casts = [
        'vat_enabled' => 'boolean',
    ];

    public function subService(): BelongsTo
    {
        return $this->belongsTo(SubService::class);
    }

    public function bookingServices(): MorphMany
    {
        return $this->morphMany(BookingService::class, 'bookable');
    }
}
