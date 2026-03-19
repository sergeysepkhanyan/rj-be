<?php

// app/Models/Booking.php

namespace App\Models;

use App\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property mixed $type
 * @property mixed $id
 * @property mixed $customer_name
 * @property mixed $customer_phone
 * @property mixed $customer_email
 * @property mixed $notes
 * @property mixed $date
 * @property mixed $master_id
 * @property mixed $start_time
 * @property mixed $end_time
 * @property mixed $status
 * @property mixed $price
 * @property mixed $payment_mode
 * @property mixed $services
 */
class Booking extends Model
{
    use BelongsToUser, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'master_id',
        'type',
        'reference',
        'batch_id',
        'date',
        'start_time',
        'end_time',
        'duration',
        'duration_unit',
        'customer_name',
        'customer_phone',
        'customer_email',
        'price',
        'discount_type',
        'discount_value',
        'discount_label',
        'final_price',
        'payment_mode',
        'payment_status',
        'status',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancel_reason',
        'notes',
        'timezone',
        'expires_at',
        'post_service_followup_sent_at',
    ];

    protected $casts = [
        'date' => 'date',
        'price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'final_price' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
        'post_service_followup_sent_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Alias for client() relationship - used by email notifications
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(User::class, 'master_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(BookingService::class);
    }

    public function order(): MorphOne
    {
        return $this->morphOne(Order::class, 'orderable');
    }

    public function isBreak(): bool
    {
        return $this->type === 'break';
    }

    public function scopeForMaster(Builder $query, int $masterId): Builder
    {
        return $query->where('master_id', $masterId);
    }

    public function scopeOnDate(Builder $query, $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }

    public function scopeOnlyBreaks(Builder $query): Builder
    {
        return $query->where('type', 'break');
    }

    public function scopeOnlyBookings(Builder $query): Builder
    {
        return $query->where('type', 'booking');
    }
}
