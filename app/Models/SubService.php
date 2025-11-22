<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubService extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'service_id',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'image'
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubServiceItem::class);
    }
}
