<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

/**
 * @property mixed $role
 * @property mixed $id
 * @property mixed $name
 * @property mixed $stripe_customer_id
 * @method static Builder|User masters()
 * @method static Builder|User active()
 */

class User extends Authenticatable implements JWTSubject, CanResetPasswordContract, MustVerifyEmail
{

    use HasFactory, Notifiable, SoftDeletes, CanResetPassword, \App\Traits\DeletesImages;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'name_ar',
        'email',
        'google_id',
        'stripe_customer_id',
        'mobile',
        'password',
        'user_role_id',
        'date_of_birth',
        'is_temporary_password',
        'temporary_password_hash',
        'temporary_password_used_at',
        'referral_id',
        'manual_referral_id',
        'description',
        'description_ar',
        'image',
        'timezone',
        'email_verified_at',
        'status',
        // tracks how a client was acquired (online | walk_in | offline | booking | manual).
        'registration_source',
        'product_discount_tier_id',
        'has_account',
        'customer_status',
        'contact_declined',
        'marketing_opt_in',
        'marketing_opt_in_at',
        'unsubscribe_token',
        'first_transacted_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'temporary_password_used_at' => 'datetime',
        'has_account' => 'boolean',
        'contact_declined' => 'boolean',
        'marketing_opt_in' => 'boolean',
        'marketing_opt_in_at' => 'datetime',
        'first_transacted_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification());
    }


    /**
     * Get the identifier that will be stored in the JWT token.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return an array with custom claims to be added to the JWT token.
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function getEmailForPasswordReset()
    {
        return $this->email;
    }

    public static function findForPasswordReset($identifier)
    {
        return self::where('email', $identifier)
            ->orWhere('mobile', $identifier)
            ->first();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(UserRole::class, 'user_role_id');
    }

    public function masterBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'master_id');
    }

    public function clientBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClientNote::class, 'client_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'user_id');
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function wishlistProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wishlists')->withTimestamps();
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function isRegistered(): bool
    {
        return (bool) $this->has_account;
    }

    public function isClient(): bool
    {
        return $this->customer_status === 'client';
    }

    public function isLead(): bool
    {
        return $this->customer_status === 'lead';
    }

    public function scopeCustomers($query)
    {
        return $query->whereHas('role', function ($q) {
            $q->where('slug', 'client');
        });
    }


    public function subservices(): BelongsToMany
    {
        return $this->belongsToMany(SubService::class, 'user_sub_services');
    }

    public function weekends(): BelongsToMany
    {
        return $this->belongsToMany(Weekday::class, 'user_weekends');
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function manualReferral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'manual_referral_id');
    }

    public function productDiscountTier(): BelongsTo
    {
        return $this->belongsTo(ProductDiscountTier::class);
    }

    public function bookingReferrals(): HasMany
    {
        return $this->hasMany(BookingReferral::class, 'referrer_user_id');
    }

    public function getActiveTierDiscount(): ?array
    {
        $this->loadMissing(['manualReferral', 'referral']);

        $referral = null;
        $bypassVisitCheck = false;

        if ($this->manual_referral_id && $this->manualReferral) {
            $referral = $this->manualReferral;
            $bypassVisitCheck = true;
        } elseif ($this->referral_id && $this->referral) {
            $referral = $this->referral;
        }

        if (!$referral || !$referral->enabled || $referral->type !== 'percentage' || $referral->value <= 0) {
            return null;
        }

        if (!$bypassVisitCheck && $referral->visit_threshold !== null) {
            $visitCount = Booking::where('user_id', $this->id)
                ->where('type', 'booking')
                ->where('status', '!=', 'cancelled')
                ->whereIn('payment_status', ['paid', 'gift'])
                ->count();

            if ($visitCount < $referral->visit_threshold) {
                return null;
            }
        }

        return [
            'value' => (float) $referral->value,
            'label' => $referral->name . ' Tier Discount',
            'name' => $referral->name,
        ];
    }

    public function complimentaryRewards(): HasMany
    {
        return $this->hasMany(ComplimentaryReward::class);
    }

    public function servicePackagePurchases(): HasMany
    {
        return $this->hasMany(ServicePackagePurchase::class);
    }
    public function scopeMasters($query)
    {
        return $query->whereHas('role', function ($q) {
            $q->where('slug', 'master');
        });
    }

    public function isAdmin(): bool
    {
        return in_array(optional($this->role)->slug, ['admin', 'superadmin']);
    }

    public function isMarketer(): bool
    {
        return optional($this->role)->slug === 'marketer';
    }

    public function isMarketerOrAbove(): bool
    {
        return in_array(optional($this->role)->slug, ['marketer', 'admin', 'superadmin']);
    }

}
