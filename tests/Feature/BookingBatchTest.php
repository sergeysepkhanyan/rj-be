<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\Order;
use App\Models\User;
use App\Services\BookingService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A multi-service appointment is stored as a BATCH of N bookings (one per
 * service, sharing a batch_id) and ONE order. Two behaviours must hold:
 *  - paying the order attributes EVERY booking in the batch to the customer
 *    (else the other services are orphaned from the client's history, spend
 *    total and loyalty visit count);
 *  - the customer receives ONE consolidated confirmation email, not one per
 *    service.
 */
class BookingBatchTest extends TestCase
{
    /** @return \Illuminate\Support\Collection<int, Booking> */
    private function makeBatch(string $batchId, array $overrides = [])
    {
        return collect([
            '10:00:00' => '10:20:00',
            '10:20:00' => '11:05:00',
            '11:05:00' => '12:05:00',
        ])->map(fn ($end, $start) => Booking::create(array_merge([
            'batch_id' => $batchId,
            'type' => 'booking',
            'date' => now()->toDateString(),
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'customer_name' => 'Batch Guest',
            'customer_email' => 'batchguest@example.com',
            'customer_phone' => '+971501234567',
            'final_price' => 100,
        ], $overrides)))->values();
    }

    public function test_paying_a_batch_order_attributes_all_bookings_to_the_customer(): void
    {
        $batchId = 'batch-' . uniqid();
        $bookings = $this->makeBatch($batchId); // guests: user_id null

        $this->assertTrue($bookings->pluck('user_id')->filter()->isEmpty());

        $order = Order::create([
            'type' => 'booking',
            'orderable_type' => Booking::class,
            'orderable_id' => $bookings->first()->id,
            'status' => 'paid',
            'amount' => 300,
            'currency' => 'AED',
            'reference' => 'BB-' . uniqid(),
            'paid_at' => now(),
            'meta' => [
                'customer_name' => 'Batch Guest',
                'customer_email' => 'batchguest@example.com',
                'customer_phone' => '+971501234567',
            ],
        ]);

        app(OrderService::class)->promoteOrderCustomer($order);

        // Every booking in the batch — not just the order's first booking — is
        // now linked to the one resolved customer.
        $userIds = Booking::where('batch_id', $batchId)->pluck('user_id')->unique()->values();
        $this->assertCount(1, $userIds, 'all batch bookings share one customer');
        $this->assertNotNull($userIds->first(), 'batch bookings are attributed, not orphaned');
    }

    public function test_multi_service_batch_sends_a_single_confirmation_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $batchId = 'batch-' . uniqid();
        $bookings = $this->makeBatch($batchId, ['user_id' => $user->id, 'customer_email' => 'batch2@example.com']);

        $svc = app(BookingService::class);
        foreach ($bookings as $booking) {
            $svc->sendBookingConfirmation($booking);
        }

        // One appointment → one email listing every service, not three.
        Mail::assertQueued(BookingConfirmedMail::class, 1);
    }
}
