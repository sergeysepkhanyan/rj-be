<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingConfig extends Model
{
    protected $table = 'tracking_config';

    protected $fillable = [
        'google_analytics_id',
        'google_tag_manager_id',
        'facebook_pixel_id',
        'snapchat_pixel_id',
    ];

    public function customScripts(): HasMany
    {
        return $this->hasMany(CustomScript::class);
    }
}
