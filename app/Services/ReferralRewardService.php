<?php

namespace App\Services;

use App\Mail\ReferralRewardEarnedMail;
use App\Models\Booking;
use App\Models\BookingReferral;
use App\Models\ComplimentaryReward;
use App\Models\ReferralRewardsConfig;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ReferralRewardService
{
    /**
     * Get the current referral rewards configuration with its services.
     */
    public function getConfig(): ?ReferralRewardsConfig
    {
        return ReferralRewardsConfig::with('services.subService')->first();
    }

    /**
     * Create or update the referral rewards configuration and sync service IDs.
     */
    public function updateConfig(array $data): ReferralRewardsConfig
    {
        return DB::transaction(function () use ($data) {
            $config = ReferralRewardsConfig::first();

            if ($config) {
                $config->update([
                    'referrals_needed' => $data['referrals_needed'] ?? $config->referrals_needed,
                    'is_active' => $data['is_active'] ?? $config->is_active,
                ]);
            } else {
                $config = ReferralRewardsConfig::create([
                    'referrals_needed' => $data['referrals_needed'] ?? 5,
                    'is_active' => $data['is_active'] ?? true,
                ]);
            }

            if (isset($data['service_ids']) && is_array($data['service_ids'])) {
                $config->services()->delete();

                foreach ($data['service_ids'] as $subServiceId) {
                    $config->services()->create([
                        'sub_service_id' => $subServiceId,
                    ]);
                }
            }

            return $config->load('services.subService');
        });
    }

    /**
     * Assign a referrer to a booking by creating a BookingReferral record.
     */
    public function assignReferrer(Booking $booking, int $referrerUserId): ?BookingReferral
    {
        // Don't allow self-referral
        if ($booking->user_id && (int) $booking->user_id === $referrerUserId) {
            return null;
        }

        // Don't create duplicate referrals for the same booking
        if (BookingReferral::where('booking_id', $booking->id)->exists()) {
            return null;
        }

        // Verify referrer exists
        if (!User::where('id', $referrerUserId)->exists()) {
            return null;
        }

        return BookingReferral::create([
            'booking_id' => $booking->id,
            'referrer_user_id' => $referrerUserId,
            'status' => 'pending',
        ]);
    }

    /**
     * Mark a booking's referral as completed and check for reward threshold.
     */
    public function completeReferral(Booking $booking): void
    {
        $referral = BookingReferral::where('booking_id', $booking->id)->first();

        if (!$referral || $referral->status !== 'pending') {
            return;
        }

        $referral->update(['status' => 'completed']);

        $this->checkAndAwardRewards($referral->referrer_user_id);
    }

    /**
     * Mark a booking's referral as cancelled.
     */
    public function cancelReferral(Booking $booking): void
    {
        $referral = BookingReferral::where('booking_id', $booking->id)->first();

        if (!$referral || $referral->status === 'cancelled') {
            return;
        }

        $referral->update(['status' => 'cancelled']);
    }

    /**
     * Check if the referrer has reached the reward threshold and award rewards if so.
     */
    public function checkAndAwardRewards(int $referrerUserId): void
    {
        $config = ReferralRewardsConfig::with('services')->first();

        if (!$config || !$config->is_active || $config->services->isEmpty()) {
            return;
        }

        $completedCount = BookingReferral::where('referrer_user_id', $referrerUserId)
            ->where('status', 'completed')
            ->count();

        $referralsNeeded = $config->referrals_needed;

        if ($referralsNeeded <= 0) {
            return;
        }

        $cyclesCompleted = (int) floor($completedCount / $referralsNeeded);

        // Count already earned rewards (each cycle awards one reward per service)
        $alreadyEarnedCycles = (int) floor(
            ComplimentaryReward::where('user_id', $referrerUserId)->count() / max(1, $config->services->count())
        );

        if ($cyclesCompleted <= $alreadyEarnedCycles) {
            return;
        }

        // Award new rewards for each new cycle
        $newCycles = $cyclesCompleted - $alreadyEarnedCycles;
        $newRewards = new \Illuminate\Database\Eloquent\Collection();

        for ($c = 0; $c < $newCycles; $c++) {
            foreach ($config->services as $rewardService) {
                $reward = ComplimentaryReward::create([
                    'user_id' => $referrerUserId,
                    'sub_service_id' => $rewardService->sub_service_id,
                    'status' => 'available',
                    'earned_at' => now(),
                ]);
                $newRewards->push($reward);
            }
        }

        // Send notification email
        $referrer = User::find($referrerUserId);
        if ($referrer && $referrer->email && $newRewards->isNotEmpty()) {
            Mail::to($referrer->email)->queue(new ReferralRewardEarnedMail($referrer, $newRewards));
        }
    }

    /**
     * Get available rewards for a user.
     */
    public function getUserRewards(int $userId): Collection
    {
        return ComplimentaryReward::where('user_id', $userId)
            ->where('status', 'available')
            ->with('subService')
            ->orderBy('earned_at', 'desc')
            ->get();
    }

    /**
     * Redeem a reward against a booking.
     */
    public function redeemReward(ComplimentaryReward $reward, Booking $booking): ComplimentaryReward
    {
        $reward->update([
            'status' => 'redeemed',
            'redeemed_at' => now(),
            'redeemed_booking_id' => $booking->id,
        ]);

        return $reward->fresh();
    }
}
