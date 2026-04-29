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

    /**
     * The DB stores `image` as a relative path (e.g. "images/blog/abc.webp").
     * The frontend receives a full URL via PostResource (asset('storage/...'))
     * and naively sends that URL back on update — so without normalization the
     * column accumulates "https://.../storage/" prefixes on every save until
     * it overflows VARCHAR (BUG: "Data too long for column 'image'").
     * Strip any number of leading "http(s)://host/storage/" layers on write.
     */
    public function setImageAttribute($value): void
    {
        if (is_string($value) && $value !== '') {
            while (preg_match('#^https?://[^/]+/storage/(.+)$#i', $value, $m)) {
                $value = $m[1];
            }
        }
        $this->attributes['image'] = $value;
    }
}
