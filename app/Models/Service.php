<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed $id
 */
class Service extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'description_ar',
        'image',
        'gender'
    ];

    public function subServices(): HasMany
    {
        return $this->hasMany(SubService::class);
    }
}
