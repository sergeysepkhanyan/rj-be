<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralRewardsConfig extends Model
{
    protected $table = 'referral_rewards_config';

    protected $fillable = [
        'referrals_needed',
        'is_active',
    ];

    protected $casts = [
        'referrals_needed' => 'integer',
        'is_active' => 'boolean',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(ReferralRewardService::class, 'referral_rewards_config_id');
    }
}
