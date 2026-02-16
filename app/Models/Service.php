<?php

namespace App\Models;

use App\Traits\DeletesImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed $id
 */
class Service extends Model
{
    use SoftDeletes, DeletesImages;
    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'description_ar',
        'image',
        'gender',
        'category_id',
    ];

    public function subServices(): HasMany
    {
        return $this->hasMany(SubService::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
