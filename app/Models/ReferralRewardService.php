<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralRewardService extends Model
{
    protected $fillable = [
        'referral_rewards_config_id',
        'sub_service_id',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(ReferralRewardsConfig::class, 'referral_rewards_config_id');
    }

    public function subService(): BelongsTo
    {
        return $this->belongsTo(SubService::class);
    }
}
