<?php

namespace App\Models;

use App\Traits\DeletesImages;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $id
 */
class Post extends Model
{
    use DeletesImages;
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
