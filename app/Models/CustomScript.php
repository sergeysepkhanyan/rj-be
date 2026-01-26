<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomScript extends Model
{
    protected $table = 'custom_scripts';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tracking_config_id',
        'name',
        'code',
        'position',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($script) {
            if (empty($script->id)) {
                $script->id = (string) Str::uuid();
            }
        });
    }

    public function trackingConfig(): BelongsTo
    {
        return $this->belongsTo(TrackingConfig::class);
    }
}
