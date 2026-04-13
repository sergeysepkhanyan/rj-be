<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralRewardService extends Model
{
    protected $fillable = [
        'referral_rewards_config_id',
        'sub_service_id',

        'sub_service_item_id',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(ReferralRewardsConfig::class, 'referral_rewards_config_id');
    }

    public function subService(): BelongsTo
    {
        return $this->belongsTo(SubService::class);
    }

    public function subServiceItem(): BelongsTo
    {
        return $this->belongsTo(SubServiceItem::class);
    }
}
