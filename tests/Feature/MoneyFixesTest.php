<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\GiftCard;
use App\Models\GiftCardPurchase;
use App\Models\GiftCardUsage;
use App\Models\Order;
use App\Models\User;
use App\Services\GiftCardService;
use App\Services\OrderService;
use Tests\TestCase;

/**
 * Regression coverage for money-critical fixes.
 */
class MoneyFixesTest extends TestCase
{
    public function test_refunding_a_service_package_restores_the_gift_card_balance(): void
    {
        // A gift card that spent 60 on a service package (balance now 40).
        $giftCard = GiftCard::create(['name' => 'Gift Card', 'price' => 100]);
        $purchase = GiftCardPurchase::create([
            'gift_card_id' => $giftCard->id,
            'code' => 'GC-' . uniqid(),
            'buyer_name' => 'Buyer',
            'buyer_email' => 'buyer@example.com',
            'recipient_name' => 'Recipient',
            'amount' => 100,
            'balance' => 40,
            'currency' => 'AED',
            'status' => 'active',
            'expires_at' => now()->addYear(),
        ]);
        $order = Order::create([
            'type' => 'service_package',
            'status' => 'refunded',
            'amount' => 60,
            'currency' => 'AED',
            'reference' => 'SP-' . uniqid(),
        ]);
        $usage = GiftCardUsage::create([
            'gift_card_purchase_id' => $purchase->id,
            'amount_used' => 60,
            'used_for_type' => 'service_package',
            'used_for_id' => $order->id,
            'used_for' => 'order',
            'used_for_name' => 'Service Package',
        ]);

        // FOUND-03: reverseForOrder must now handle used_for_type='service_package'.
        app(GiftCardService::class)->reverseForOrder($order);

        $this->assertEqualsWithDelta(100.0, (float) $purchase->fresh()->balance, 0.001, 'balance restored');
        $this->assertNotNull($usage->fresh()->reversed_at, 'usage marked reversed');
    }

    public function test_gift_card_reversal_is_idempotent(): void
    {
        $giftCard = GiftCard::create(['name' => 'Gift Card', 'price' => 100]);
        $purchase = GiftCardPurchase::create([
            'gift_card_id' => $giftCard->id,
            'code' => 'GC-' . uniqid(),
            'buyer_name' => 'Buyer',
            'buyer_email' => 'buyer@example.com',
            'recipient_name' => 'Recipient',
            'amount' => 100,
            'balance' => 40,
            'currency' => 'AED',
            'status' => 'active',
            'expires_at' => now()->addYear(),
        ]);
        $order = Order::create([
            'type' => 'service_package',
            'status' => 'refunded',
            'amount' => 60,
            'currency' => 'AED',
            'reference' => 'SP-' . uniqid(),
        ]);
        GiftCardUsage::create([
            'gift_card_purchase_id' => $purchase->id,
            'amount_used' => 60,
            'used_for_type' => 'service_package',
            'used_for_id' => $order->id,
            'used_for' => 'order',
            'used_for_name' => 'Service Package',
        ]);

        $service = app(GiftCardService::class);
        $service->reverseForOrder($order);
        $service->reverseForOrder($order); // second call must not double-credit

        $this->assertEqualsWithDelta(100.0, (float) $purchase->fresh()->balance, 0.001, 'no double credit');
    }

    public function test_refunding_a_gift_card_paid_booking_restores_balance(): void
    {
        // BUG-018: cancelling/refunding a gift-card-paid booking must restore the balance.
        $user = User::factory()->create();
        $booking = Booking::create([
            'user_id' => $user->id,
            'date' => now()->addDays(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'type' => 'booking',
            'orderable_type' => Booking::class,
            'orderable_id' => $booking->id,
            'status' => 'paid',
            'amount' => 0,
            'currency' => 'AED',
            'reference' => 'B-' . uniqid(),
            'paid_at' => now(),
        ]);

        $giftCard = GiftCard::create(['name' => 'Gift Card', 'price' => 100]);
        $purchase = GiftCardPurchase::create([
            'gift_card_id' => $giftCard->id,
            'code' => 'GC-' . uniqid(),
            'buyer_name' => 'Buyer',
            'buyer_email' => 'buyer@example.com',
            'recipient_name' => 'Recipient',
            'amount' => 100,
            'balance' => 40,
            'currency' => 'AED',
            'status' => 'active',
            'expires_at' => now()->addYear(),
        ]);
        $usage = GiftCardUsage::create([
            'gift_card_purchase_id' => $purchase->id,
            'amount_used' => 60,
            'used_for_type' => 'booking',
            'used_for_id' => $booking->id,
            'used_for' => 'booking',
            'used_for_name' => 'Booking',
        ]);

        app(OrderService::class)->refund($order, ['reason' => 'booking_cancelled']);

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertEqualsWithDelta(100.0, (float) $purchase->fresh()->balance, 0.001, 'gift-card balance restored');
        $this->assertNotNull($usage->fresh()->reversed_at);
    }
}
