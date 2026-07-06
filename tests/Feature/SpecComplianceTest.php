<?php

namespace Tests\Feature;

use App\Mail\MarketingCampaignMail;
use App\Models\Booking;
use App\Models\Order;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Covers the final spec-compliance additions: strict UAE phone validation,
 * the first-class no_show appointment status, and the marketing (stream 3)
 * campaign send gated on opt-in with an unsubscribe link.
 */
class SpecComplianceTest extends TestCase
{
    // ---------- UAE phone validation ----------

    public function test_signup_rejects_invalid_uae_phone_lengths(): void
    {
        $payload = fn (string $mobile, string $email) => [
            'first_name' => 'Test', 'last_name' => 'User', 'email' => $email,
            'mobile' => $mobile, 'password' => 'secret123', 'passwordConfirmation' => 'secret123',
        ];

        // +971 with only 5 national digits — too short.
        $this->postJson('/api/auth/signup', $payload('+97150123', 'p1@example.com'))
            ->assertStatus(422)->assertJsonValidationErrors(['mobile']);

        // +971 with 11 national digits — too long.
        $this->postJson('/api/auth/signup', $payload('+97150123456789', 'p2@example.com'))
            ->assertStatus(422)->assertJsonValidationErrors(['mobile']);

        // Valid UAE: +971 + exactly 9 digits.
        $this->postJson('/api/auth/signup', $payload('+971501234567', 'p3@example.com'))
            ->assertSuccessful();

        // Valid non-UAE international number is still accepted.
        $this->postJson('/api/auth/signup', $payload('+15551234567', 'p4@example.com'))
            ->assertSuccessful();
    }

    // ---------- First-class no_show status ----------

    private function makeBooking(array $overrides = []): Booking
    {
        $user = User::factory()->create();

        return Booking::create(array_merge([
            'user_id' => $user->id,
            'date' => now()->subDay()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 'confirmed',
        ], $overrides));
    }

    public function test_admin_can_mark_a_booking_no_show_and_paid_fee_is_kept(): void
    {
        $this->actingAsAdmin();
        $svc = app(CustomerService::class);

        $booking = $this->makeBooking(['payment_status' => 'paid']);
        $customer = User::find($booking->user_id);
        $svc->markTransacted($customer, now()); // paid booking → they are a Client

        $order = Order::create([
            'user_id' => $booking->user_id,
            'type' => 'booking',
            'orderable_type' => Booking::class,
            'orderable_id' => $booking->id,
            'status' => 'paid',
            'amount' => 200,
            'currency' => 'AED',
            'reference' => 'NS-' . uniqid(),
            'paid_at' => now(),
        ]);

        $this->patchJson("/api/admin/bookings/{$booking->id}/mark-no-show")
            ->assertSuccessful();

        $booking->refresh();
        $this->assertSame('no_show', $booking->status);
        $this->assertSame('no_show', $booking->cancel_reason, 'legacy no-show stats keep counting');

        // Spec: fee kept — order stays paid (revenue retained), person stays a Client.
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('client', $customer->fresh()->customer_status);
    }

    public function test_no_show_on_unpaid_booking_cancels_its_pending_order_and_leaves_a_lead(): void
    {
        $this->actingAsAdmin();

        $booking = $this->makeBooking(['payment_status' => 'unpaid']);
        $order = Order::create([
            'user_id' => $booking->user_id,
            'type' => 'booking',
            'orderable_type' => Booking::class,
            'orderable_id' => $booking->id,
            'status' => 'pending',
            'amount' => 200,
            'currency' => 'AED',
            'reference' => 'NS-' . uniqid(),
        ]);

        $this->patchJson("/api/admin/bookings/{$booking->id}/mark-no-show")
            ->assertSuccessful();

        $this->assertSame('no_show', $booking->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status, 'no money moved — pending order is dropped');
    }

    public function test_no_show_cannot_be_applied_twice_or_to_a_cancelled_booking(): void
    {
        $this->actingAsAdmin();

        $booking = $this->makeBooking(['status' => 'cancelled']);
        $this->patchJson("/api/admin/bookings/{$booking->id}/mark-no-show")
            ->assertStatus(422);
    }

    // ---------- Marketing stream (3) ----------

    public function test_marketing_campaign_goes_only_to_opted_in_clients(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $svc = app(CustomerService::class);

        $optedIn = $svc->resolveForTransaction(['email' => 'yes@example.com', 'phone' => '+971501111222', 'marketing_opt_in' => true]);
        $svc->resolveForTransaction(['email' => 'no@example.com', 'phone' => '+971501111333']); // not opted in

        $response = $this->postJson('/api/admin/marketing/campaign', [
            'subject' => 'Summer offer',
            'body' => '20% off facials this week.',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.sent', 1);

        Mail::assertQueued(MarketingCampaignMail::class, 1);
        Mail::assertQueued(MarketingCampaignMail::class, function (MarketingCampaignMail $mail) use ($optedIn) {
            return $mail->hasTo($optedIn->email)
                && str_contains($mail->unsubscribeUrl, (string) $optedIn->unsubscribe_token);
        });
        Mail::assertNotQueued(MarketingCampaignMail::class, fn ($mail) => $mail->hasTo('no@example.com'));
    }

    public function test_marketing_audience_counts_only_opted_in_clients(): void
    {
        $this->actingAsAdmin();
        $svc = app(CustomerService::class);
        $svc->resolveForTransaction(['email' => 'a1@example.com', 'phone' => '+971502222111', 'marketing_opt_in' => true]);
        $svc->resolveForTransaction(['email' => 'a2@example.com', 'phone' => '+971502222333']);

        $this->getJson('/api/admin/marketing/audience')
            ->assertSuccessful()
            ->assertJsonPath('data.audienceCount', 1);
    }
}
