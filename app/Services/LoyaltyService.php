<?php

namespace App\Services;

use App\Mail\LoyaltyTierDowngradedMail;
use App\Mail\LoyaltyTierUpgradedMail;
use App\Models\Booking;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class LoyaltyService
{
    /**
     * Get all referral tiers ordered by visit_threshold ASC.
     */
    public function getTiers(): Collection
    {
        return Referral::orderBy('visit_threshold', 'asc')->get();
    }

    /**
     * Check and upgrade (or downgrade) a user's loyalty tier based on completed bookings.
     * Does NOT override manual_referral_id.
     */
    public function checkAndUpgradeUser(User $user): void
    {
        // Never override a manually assigned referral
        if ($user->manual_referral_id) {
            return;
        }

        $visitCount = Booking::where('user_id', $user->id)
            ->where('type', 'booking')
            ->where('status', '!=', 'cancelled')
            ->whereIn('payment_status', ['paid', 'gift'])
            ->count();

        $matchingTier = Referral::where('enabled', true)
            ->whereNotNull('visit_threshold')
            ->where('visit_threshold', '<=', $visitCount)
            ->orderBy('visit_threshold', 'desc')
            ->first();

        $newReferralId = $matchingTier?->id;
        $previousReferralId = $user->referral_id;

        if ($previousReferralId !== $newReferralId) {
            $user->update(['referral_id' => $newReferralId]);

            if ($user->email) {
                if ($matchingTier && ($previousReferralId === null || $this->isUpgrade($previousReferralId, $matchingTier))) {
                    // Upgrade: new tier is higher or user had no tier before
                    Mail::to($user->email)->queue(new LoyaltyTierUpgradedMail($user, $matchingTier));
                } elseif ($previousReferralId !== null) {
                    // Downgrade: user had a tier and now has a lower one or none
                    $firstTier = Referral::where('enabled', true)
                        ->whereNotNull('visit_threshold')
                        ->orderBy('visit_threshold', 'asc')
                        ->first();
                    Mail::to($user->email)->queue(new LoyaltyTierDowngradedMail($user, $matchingTier, $firstTier));
                }
            }
        }
    }

    /**
     * Check if the new tier is an upgrade compared to the previous referral_id.
     */
    protected function isUpgrade(int $previousReferralId, Referral $newTier): bool
    {
        $previousTier = Referral::find($previousReferralId);
        if (!$previousTier || $previousTier->visit_threshold === null) {
            return true;
        }

        return $newTier->visit_threshold > $previousTier->visit_threshold;
    }

    /**
     * Get active tiers for client dashboard display.
     */
    public function getActiveTiersForClient(): Collection
    {
        return Referral::where('enabled', true)
            ->whereNotNull('visit_threshold')
            ->orderBy('visit_threshold', 'asc')
            ->get(['id', 'name', 'name_ar', 'value', 'type', 'visit_threshold']);
    }
}
