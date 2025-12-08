<?php

namespace App\Models;

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
 */
class User extends Authenticatable implements JWTSubject, CanResetPasswordContract
{

    use HasFactory, Notifiable, SoftDeletes, CanResetPassword;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'name_ar',
        'email',
        'mobile',
        'password',
        'user_role_id',
        'date_of_birth',
        'is_temporary_password',
        'referral_id',
        'description',
        'description_ar',
        'image',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];


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
    public function getJWTCustomClaims()
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
        return $this->hasMany(UserBooking::class, 'master_id');
    }

    public function clientBookings(): HasMany
    {
        return $this->hasMany(UserBooking::class, 'client_id');
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
}
