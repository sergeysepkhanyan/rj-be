<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'last_name',
        'mobile',
        'address',
        'additional_address',
        'city',
        'state',
        'zip_code',
        'is_default',
        'is_billing',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_billing' => 'boolean',
    ];

    /**
     * Each address belongs to a user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
