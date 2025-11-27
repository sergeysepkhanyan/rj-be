<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $id
 */
class Post extends Model
{
    protected $fillable = [
        'lang',
        'author',
        'title',
        'meta_title',
        'meta_description',
        'slug',
        'preview',
        'content',
        'image',
        'show_author',
        'status',
        'publish_date',
    ];

    protected $casts = [
        'publish_date' => 'date',
    ];

}
