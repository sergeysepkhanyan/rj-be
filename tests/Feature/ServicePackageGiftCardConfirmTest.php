<?php

namespace Tests\Feature;

use App\Integrations\Stripe\StripeClient;
use App\Models\GiftCard;
use App\Models\GiftCardPurchase;
use App\Models\ServicePackage;
use App\Models\ServicePackagePurchase;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * A gift card applied at checkout can be spent elsewhere before the customer pays.
 * When that happens the purchase must NOT be created, the captured card payment
 * must be refunded, and the API must report the failure (never a silent success).
 */
class ServicePackageGiftCardConfirmTest extends TestCase
{
    private function actingAsClient(): User
    {
        $role = UserRole::where('slug', 'client')->first();
        $user = User::factory()->create([
            'user_role_id' => $role->id,
            'email_verified_at' => now(),
        ]);
        $this->authToken = JWTAuth::fromUser($user);

        return $user;
    }

    private function makePackage(float $price = 150.0): ServicePackage
    {
        return ServicePackage::create([
            'name' => 'TEST',
            'price' => $price,
            'currency' => 'AED',
            'validity_days' => 1,
            'status' => 'active',
        ]);
    }

    private function makeGiftCard(string $code, float $balance, string $status = 'active'): GiftCardPurchase
    {
        $card = GiftCard::create([
            'name' => 'Gift Card 100', 'price' => 100, 'currency' => 'AED', 'status' => 'active',
        ]);

        return GiftCardPurchase::create([
            'gift_card_id' => $card->id,
            'code' => $code,
            'buyer_name' => 'Buyer', 'buyer_email' => 'buyer@example.com',
            'recipient_name' => 'Holder', 'recipient_email' => 'holder@example.com',
            'amount' => 100, 'balance' => $balance, 'currency' => 'AED', 'status' => $status,
            'expires_at' => now()->addYear(),
        ]);
    }

    /**
     * Stripe intent that captured only the gift-card-discounted amount (57.50 of 157.50).
     *
     * @return array<string,mixed>
     */
    private function capturedIntent(string $id, ServicePackage $package, User $user, string $giftCardCode): array
    {
        return [
            'id' => $id,
            'status' => 'succeeded',
            'amount' => 5750,
            'amount_received' => 5750,
            'metadata' => [
                'service_package_id' => (string) $package->id,
                'user_id' => (string) $user->id,
                'gift_card_code' => $giftCardCode,
            ],
        ];
    }

    public function test_confirm_refunds_and_fails_when_gift_card_was_depleted_after_checkout(): void
    {
        Mail::fake();
        $user = $this->actingAsClient();
        $package = $this->makePackage();
        $this->makeGiftCard('GC-DEPLETED', 0.0, 'used');

        $intent = $this->capturedIntent('pi_depleted', $package, $user, 'GC-DEPLETED');

        $refunds = [];
        $this->mock(StripeClient::class, function ($mock) use ($intent, &$refunds) {
            $mock->shouldReceive('retrievePaymentIntent')->andReturn($intent);
            $mock->shouldReceive('createRefund')
                ->andReturnUsing(function ($payload, $key) use (&$refunds) {
                    $refunds[] = ['payload' => $payload, 'key' => $key];
                    return ['id' => 're_1', 'status' => 'succeeded'];
                });
        });

        $response = $this->postJson('/api/service-packages/confirm', [
            'paymentIntentId' => 'pi_depleted',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('refunded', $response->json('message'));

        $this->assertSame(0, ServicePackagePurchase::count(), 'No purchase may be created.');
        $this->assertDatabaseCount('orders', 0);

        $this->assertCount(1, $refunds, 'The captured card payment must be refunded.');
        $this->assertSame('pi_depleted', $refunds[0]['payload']['payment_intent']);
        $this->assertSame('spo-refund-pi_depleted', $refunds[0]['key'], 'Refund must be idempotent per intent.');
    }

    public function test_confirm_creates_the_purchase_and_debits_the_card_when_the_balance_still_covers_it(): void
    {
        Mail::fake();
        $user = $this->actingAsClient();
        $package = $this->makePackage();
        $giftCard = $this->makeGiftCard('GC-GOOD', 100.0);

        $intent = $this->capturedIntent('pi_good', $package, $user, 'GC-GOOD');

        $this->mock(StripeClient::class, function ($mock) use ($intent) {
            $mock->shouldReceive('retrievePaymentIntent')->andReturn($intent);
            $mock->shouldNotReceive('createRefund');
        });

        $this->postJson('/api/service-packages/confirm', ['paymentIntentId' => 'pi_good'])
            ->assertStatus(200);

        $this->assertSame(1, ServicePackagePurchase::count());

        // 157.50 gross - 57.50 captured = 100.00 taken off the card, leaving it spent.
        $giftCard->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $giftCard->balance, 0.001);
        $this->assertSame('used', $giftCard->status);
    }

    public function test_a_gift_card_cannot_be_spent_twice_across_two_purchases(): void
    {
        Mail::fake();
        $user = $this->actingAsClient();
        $first = $this->makePackage();
        $second = $this->makePackage();
        $giftCard = $this->makeGiftCard('GC-ONCE', 100.0);

        $this->mock(StripeClient::class, function ($mock) use ($first, $second, $user) {
            $mock->shouldReceive('retrievePaymentIntent')->with('pi_one')
                ->andReturn($this->capturedIntent('pi_one', $first, $user, 'GC-ONCE'));
            $mock->shouldReceive('retrievePaymentIntent')->with('pi_two')
                ->andReturn($this->capturedIntent('pi_two', $second, $user, 'GC-ONCE'));
            $mock->shouldReceive('createRefund')->andReturn(['id' => 're_2', 'status' => 'succeeded']);
        });

        $this->postJson('/api/service-packages/confirm', ['paymentIntentId' => 'pi_one'])->assertStatus(200);
        $this->postJson('/api/service-packages/confirm', ['paymentIntentId' => 'pi_two'])->assertStatus(422);

        $this->assertSame(1, ServicePackagePurchase::count(), 'Only the first purchase may exist.');

        $giftCard->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $giftCard->balance, 0.001, 'Balance may never go negative.');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
