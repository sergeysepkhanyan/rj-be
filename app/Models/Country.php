<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'code',
        'code3',
        'enabled',
        'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get all enabled countries ordered by sort_order
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true)->orderBy('sort_order')->orderBy('name');
    }
}
