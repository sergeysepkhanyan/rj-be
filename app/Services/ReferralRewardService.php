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
        return ReferralRewardsConfig::with(['services.subService', 'services.subServiceItem'])->first();
    }


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

            if (isset($data['services']) && is_array($data['services'])) {
                $config->services()->delete();

                foreach ($data['services'] as $entry) {
                    if (!is_array($entry) || empty($entry['id'])) {
                        continue;
                    }
                    $type = $entry['type'] ?? 'subservice';
                    if ($type === 'item') {
                        $config->services()->create([
                            'sub_service_id' => null,
                            'sub_service_item_id' => (int) $entry['id'],
                        ]);
                    } else {
                        $config->services()->create([
                            'sub_service_id' => (int) $entry['id'],
                            'sub_service_item_id' => null,
                        ]);
                    }
                }
            } elseif (isset($data['service_ids']) && is_array($data['service_ids'])) {
                // Legacy callers — treat every id as a subservice id.
                $config->services()->delete();

                foreach ($data['service_ids'] as $subServiceId) {
                    $config->services()->create([
                        'sub_service_id' => (int) $subServiceId,
                        'sub_service_item_id' => null,
                    ]);
                }
            }

            return $config->load(['services.subService', 'services.subServiceItem']);
        });
    }


    public function assignReferrer(Booking $booking, int $referrerUserId): ?BookingReferral
    {
        // No self-referral (by id, or by email/phone match on guest checkout).
        if ($this->isSelfReferral($booking, $referrerUserId)) {
            return null;
        }

        // Don't credit referrals for non-revenue bookings (gift / package).
        if (
            $booking->is_complimentary
            || $booking->is_package_booking
            || $booking->payment_status === 'gift'
            || $booking->payment_status === 'package'
        ) {
            return null;
        }

        // Don't create duplicate referrals for the same booking
        if (BookingReferral::where('booking_id', $booking->id)->exists()) {
            return null;
        }

        return BookingReferral::create([
            'booking_id' => $booking->id,
            'referrer_user_id' => $referrerUserId,
            'status' => 'pending',
        ]);
    }

    /**
     * True when the referrer would be referring themselves — same account, or the
     * booking's guest contact matches the referrer's email/phone. An unknown
     * referrer id also counts as invalid.
     */
    public function isSelfReferral(Booking $booking, int $referrerUserId): bool
    {
        if ($booking->user_id && (int) $booking->user_id === $referrerUserId) {
            return true;
        }

        $referrer = User::find($referrerUserId);
        if (!$referrer) {
            return true;
        }

        return ($booking->customer_email && $referrer->email && strcasecmp($booking->customer_email, $referrer->email) === 0)
            || ($booking->customer_phone && $referrer->mobile && $booking->customer_phone === $referrer->mobile);
    }

    /**
     * Apply a referrer chosen or changed while editing a booking: create, update,
     * or remove the pending referral. A referral that has already completed (the
     * referrer was credited toward a reward) is left untouched.
     */
    public function setReferrer(Booking $booking, ?int $referrerUserId): void
    {
        $existing = BookingReferral::where('booking_id', $booking->id)->first();

        // Never disturb an already-credited referral.
        if ($existing && $existing->status === 'completed') {
            return;
        }

        // Referrer removed, or the new choice is an invalid self-referral → no referral.
        if (!$referrerUserId || $this->isSelfReferral($booking, $referrerUserId)) {
            $existing?->delete();
            return;
        }

        if (!$existing) {
            $this->assignReferrer($booking, $referrerUserId);
        } elseif ((int) $existing->referrer_user_id !== $referrerUserId) {
            $existing->update(['referrer_user_id' => $referrerUserId]);
        }

        // If the booking is already paid, the payment-time completeReferral() ran
        // before this referral existed — so complete it now instead of leaving it
        // pending forever. On an unpaid booking this is a no-op (stays pending
        // until payment completes it normally).
        $this->completeReferral($booking->fresh() ?? $booking);
    }

    public function completeReferral(Booking $booking): void
    {
        // Defensive: never credit a referral for a non-revenue booking.
        if (
            $booking->is_complimentary
            || $booking->is_package_booking
            || $booking->payment_status === 'gift'
            || $booking->payment_status === 'package'
        ) {
            return;
        }

        // Defensive: never credit a referral until the booking is actually paid.
        if ($booking->payment_status !== 'paid') {
            return;
        }

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
