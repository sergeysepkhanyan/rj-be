<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageSeo extends Model
{
    protected $table = 'page_seo';

    protected $fillable = [
        'page_key',
        'meta_title',
        'meta_title_ar',
        'meta_description',
        'meta_description_ar',
        'keywords',
        'keywords_ar',
        'og_image',
        'canonical_url',
    ];
}
