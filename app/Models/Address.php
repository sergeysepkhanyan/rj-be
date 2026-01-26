<?php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory, SoftDeletes, BelongsToUser;

    protected $fillable = [
        'user_id',
        'order_id',
        'type',
        'is_default',
        'name',
        'last_name',
        'mobile',
        'address',
        'additional_address',
        'city',
        'country_id',
        'zip_code',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Each address belongs to a user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Each address belongs to a country.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

//    /**
//     * Optional: If order table exists in future
//     */
//    public function order(): BelongsTo
//    {
//        return $this->belongsTo(Order::class);
//    }
}
