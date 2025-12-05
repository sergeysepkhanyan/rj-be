<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'content',
        'url'
    ];

    protected $casts = [
        'content' => 'array',
    ];
}
