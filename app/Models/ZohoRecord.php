<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ZohoRecord extends Model
{
    protected $fillable = [
        'syncable_type',
        'syncable_id',
        'module',
        'zoho_id',
        'synced_at',
        'last_error',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function syncable(): MorphTo
    {
        return $this->morphTo();
    }
}
