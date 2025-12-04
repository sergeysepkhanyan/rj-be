<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubServiceItemVariant extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'sub_service_item_id',
        'name',
        'name_ar',
        'price',
        'currency',
        'duration',
        'duration_unit'
    ];

    public function subServiceItem(): BelongsTo
    {
        return $this->belongsTo(SubServiceItem::class);
    }

    public function booking(): MorphOne
    {
        return $this->morphOne(UserBookingSubserviceItem::class, 'bookable');
    }
}
