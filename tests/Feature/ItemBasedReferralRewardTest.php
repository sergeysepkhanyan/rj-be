<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingReferral;
use App\Models\ComplimentaryReward;
use App\Models\ReferralRewardsConfig;
use App\Models\SubService;
use App\Models\SubServiceItem;
use App\Models\User;
use Tests\TestCase;

/**
 * A referral reward can be configured as a specific sub-service ITEM (not just a
 * sub-service). Awarding it must create a complimentary_reward with the item —
 * previously it inserted a null sub_service_id and 500'd the whole mark-paid flow.
 */
class ItemBasedReferralRewardTest extends TestCase
{
    public function test_item_based_referral_reward_is_awarded_without_crashing(): void
    {
        $subService = SubService::create([
            'name' => 'Trim', 'price' => 100, 'currency' => 'AED',
            'duration' => 30, 'duration_unit' => 'minutes',
        ]);
        $item = SubServiceItem::create([
            'sub_service_id' => $subService->id, 'name' => 'Short Hair', 'price' => 100,
            'currency' => 'AED', 'duration' => 30, 'duration_unit' => 'minutes',
        ]);

        // Reward config: 1 referral needed; the reward is the ITEM (no sub_service_id).
        $config = ReferralRewardsConfig::create(['referrals_needed' => 1, 'is_active' => true]);
        \App\Models\ReferralRewardService::create([
            'referral_rewards_config_id' => $config->id,
            'sub_service_item_id' => $item->id,
        ]);

        $referrer = User::factory()->create();
        $booking = Booking::create([
            'user_id' => User::factory()->create()->id,
            'date' => now()->toDateString(), 'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'status' => 'confirmed', 'payment_status' => 'paid',
        ]);
        BookingReferral::create([
            'booking_id' => $booking->id, 'referrer_user_id' => $referrer->id, 'status' => 'completed',
        ]);

        app(\App\Services\ReferralRewardService::class)->checkAndAwardRewards($referrer->id);

        $this->assertDatabaseHas('complimentary_rewards', [
            'user_id' => $referrer->id,
            'sub_service_item_id' => $item->id,
            'sub_service_id' => null,
        ]);

        // The reward resolves its name from the item.
        $this->assertSame('Short Hair', ComplimentaryReward::where('user_id', $referrer->id)->first()->resolveServiceName());
    }
}
