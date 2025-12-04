<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubServiceItem extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'sub_service_id',
        'name',
        'name_ar',
        'type',
        'price',
        'currency',
        'duration',
        'duration_unit'
    ];

    public function subService(): BelongsTo
    {
        return $this->belongsTo(SubService::class);
    }

    public function variants(): HasMany{
        return $this->hasMany(SubServiceItemVariant::class);
    }

    public function booking(): MorphOne
    {
        return $this->morphOne(UserBookingSubserviceItem::class, 'bookable');
    }
}
