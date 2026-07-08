<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingReferral;
use App\Models\User;
use App\Services\ReferralRewardService;
use Tests\TestCase;

/**
 * Changing the "referred by" on an existing booking must actually take effect:
 * create/update/remove the pending referral — while refusing self-referrals and
 * never disturbing a referral that already counted toward a reward.
 */
class ReferrerUpdateTest extends TestCase
{
    private function booking(array $overrides = []): Booking
    {
        $user = User::factory()->create();

        return Booking::create(array_merge([
            'user_id' => $user->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'confirmed',
            'payment_status' => 'unpaid',
        ], $overrides));
    }

    public function test_set_referrer_creates_updates_and_removes_pending_referral(): void
    {
        $svc = app(ReferralRewardService::class);
        $booking = $this->booking();
        $refA = User::factory()->create();
        $refB = User::factory()->create();

        $svc->setReferrer($booking, $refA->id);
        $this->assertDatabaseHas('booking_referrals', [
            'booking_id' => $booking->id, 'referrer_user_id' => $refA->id, 'status' => 'pending',
        ]);

        // Change to a different referrer — no duplicate row.
        $svc->setReferrer($booking, $refB->id);
        $this->assertSame(1, BookingReferral::where('booking_id', $booking->id)->count());
        $this->assertSame($refB->id, (int) BookingReferral::where('booking_id', $booking->id)->value('referrer_user_id'));

        // Remove the referrer entirely.
        $svc->setReferrer($booking, null);
        $this->assertSame(0, BookingReferral::where('booking_id', $booking->id)->count());
    }

    public function test_adding_a_referrer_to_an_already_paid_booking_completes_it_immediately(): void
    {
        $svc = app(ReferralRewardService::class);
        $booking = $this->booking(['payment_status' => 'paid']);
        $ref = User::factory()->create();

        // Payment already happened, so completeReferral() ran before this referral
        // existed — setReferrer must complete it now instead of leaving it pending.
        $svc->setReferrer($booking, $ref->id);

        $this->assertSame('completed', BookingReferral::where('booking_id', $booking->id)->value('status'));
    }

    public function test_set_referrer_rejects_self_referral(): void
    {
        $svc = app(ReferralRewardService::class);
        $booking = $this->booking();

        $svc->setReferrer($booking, (int) $booking->user_id);
        $this->assertSame(0, BookingReferral::where('booking_id', $booking->id)->count());
    }

    public function test_set_referrer_leaves_a_completed_referral_untouched(): void
    {
        $svc = app(ReferralRewardService::class);
        $booking = $this->booking();
        $refA = User::factory()->create();
        $refB = User::factory()->create();

        BookingReferral::create([
            'booking_id' => $booking->id, 'referrer_user_id' => $refA->id, 'status' => 'completed',
        ]);

        $svc->setReferrer($booking, $refB->id);

        $ref = BookingReferral::where('booking_id', $booking->id)->first();
        $this->assertSame($refA->id, (int) $ref->referrer_user_id, 'completed referral is not reassigned');
        $this->assertSame('completed', $ref->status);
    }
}
