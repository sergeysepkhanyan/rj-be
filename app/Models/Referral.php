<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    protected $fillable = [
        'name',
        'name_ar',
        'type',
        'value',
        'visit_threshold',
        'enabled',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'visit_threshold' => 'integer',
        'enabled' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'referral_id');
    }

    public function manualUsers(): HasMany
    {
        return $this->hasMany(User::class, 'manual_referral_id');
    }
}
