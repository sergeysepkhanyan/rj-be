<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed $type
 * @property mixed $id
 */
class SubService extends Model
{
    use SoftDeletes;
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
        'type'
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

    public function masters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_sub_services', 'sub_service_id', 'user_id')
            ->withTimestamps();
    }
}
