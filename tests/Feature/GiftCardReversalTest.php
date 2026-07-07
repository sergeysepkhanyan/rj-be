<?php

namespace Tests\Feature;

use App\Mail\GiftCardBalanceRestoredMail;
use App\Models\GiftCardPurchase;
use App\Models\GiftCardUsage;
use App\Models\Order;
use App\Services\GiftCardService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * When an order paid (partly) with a gift card is refunded/cancelled, the spent
 * amount must be credited back, the usage marked reversed (so history shows it),
 * and the card HOLDER (recipient, not the buyer) notified — exactly once.
 */
class GiftCardReversalTest extends TestCase
{
    private function makeCardAndUsage(string $code, float $balance, float $used, array $cardOverrides = []): array
    {
        $card = \App\Models\GiftCard::create([
            'name' => 'Gift Card 100', 'price' => 100, 'currency' => 'AED', 'status' => 'active',
        ]);

        $purchase = GiftCardPurchase::create(array_merge([
            'gift_card_id' => $card->id,
            'code' => $code,
            'buyer_name' => 'Buyer', 'buyer_email' => 'buyer@example.com',
            'recipient_name' => 'Holder', 'recipient_email' => 'holder@example.com',
            'amount' => 100, 'balance' => $balance, 'currency' => 'AED', 'status' => 'used',
            'expires_at' => now()->addYear(),
        ], $cardOverrides));

        $order = Order::create([
            'type' => 'ecommerce', 'status' => 'refunded', 'amount' => 0,
            'currency' => 'AED', 'reference' => 'E-' . $code,
        ]);

        $usage = GiftCardUsage::create([
            'gift_card_purchase_id' => $purchase->id,
            'amount_used' => $used,
            'used_for_type' => 'product', 'used_for_id' => $order->id,
            'used_for_name' => 'Serum', 'used_for' => 'Serum',
        ]);

        return [$purchase, $order, $usage];
    }

    public function test_refund_restores_balance_marks_reversed_and_emails_the_holder(): void
    {
        Mail::fake();
        [$purchase, $order, $usage] = $this->makeCardAndUsage('GC-REVERSAL1', 5.50, 94.50);

        app(GiftCardService::class)->reverseForOrder($order);

        $purchase->refresh();
        $usage->refresh();

        $this->assertEqualsWithDelta(100.0, (float) $purchase->balance, 0.001, 'balance credited back');
        $this->assertSame('active', $purchase->status, 'used card reactivated');
        $this->assertNotNull($usage->reversed_at, 'usage marked reversed so history shows it');

        // The HOLDER (recipient), not the buyer, is notified.
        Mail::assertQueued(GiftCardBalanceRestoredMail::class, 1);
        Mail::assertQueued(GiftCardBalanceRestoredMail::class, fn (GiftCardBalanceRestoredMail $m) => $m->hasTo('holder@example.com'));
        Mail::assertNotQueued(GiftCardBalanceRestoredMail::class, fn (GiftCardBalanceRestoredMail $m) => $m->hasTo('buyer@example.com'));
    }

    public function test_reversal_is_idempotent(): void
    {
        Mail::fake();
        [$purchase, $order] = $this->makeCardAndUsage('GC-REVERSAL2', 10, 90);

        $svc = app(GiftCardService::class);
        $svc->reverseForOrder($order);
        $svc->reverseForOrder($order); // second call must not double-credit or re-email

        $this->assertEqualsWithDelta(100.0, (float) $purchase->fresh()->balance, 0.001);
        Mail::assertQueued(GiftCardBalanceRestoredMail::class, 1);
    }
}
