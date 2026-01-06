<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'description_ar',
        'image',
        'gender',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
