<?php

namespace Tests\Feature;

use App\Integrations\Stripe\StripeClient;
use App\Models\Order;
use App\Models\ServicePackage;
use App\Models\User;
use App\Models\UserRole;
use App\Services\CustomerService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Marketing consent must be captured wherever a customer transacts — and consent is
 * only ever granted here. Revocation happens exclusively through the unsubscribe link.
 */
class MarketingConsentCaptureTest extends TestCase
{
    private function client(array $overrides = []): User
    {
        $role = UserRole::where('slug', 'client')->first();

        return User::factory()->create(array_merge([
            'user_role_id' => $role->id,
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function authenticate(User $user): void
    {
        $this->authToken = JWTAuth::fromUser($user);
    }

    public function test_an_order_for_a_logged_in_customer_records_consent_from_order_meta(): void
    {
        // The regression: resolveOrderCustomer returned early on orders that already had a
        // user_id, so a logged-in buyer's consent was written to meta but never to the record.
        $user = $this->client(['marketing_opt_in' => false]);

        $order = Order::create([
            'user_id' => $user->id,
            'type' => 'ecommerce', 'status' => 'paid', 'amount' => 100,
            'currency' => 'AED', 'reference' => 'EPO-CONSENT',
            'meta' => ['marketing_opt_in' => true],
        ]);

        app(OrderService::class)->promoteOrderCustomer($order);

        $user->refresh();
        $this->assertTrue((bool) $user->marketing_opt_in);
        $this->assertNotNull($user->marketing_opt_in_at);
    }

    public function test_an_order_without_consent_does_not_grant_it(): void
    {
        $user = $this->client(['marketing_opt_in' => false]);

        $order = Order::create([
            'user_id' => $user->id,
            'type' => 'ecommerce', 'status' => 'paid', 'amount' => 100,
            'currency' => 'AED', 'reference' => 'EPO-NOCONSENT',
            'meta' => ['marketing_opt_in' => false],
        ]);

        app(OrderService::class)->promoteOrderCustomer($order);

        $this->assertFalse((bool) $user->refresh()->marketing_opt_in);
    }

    public function test_an_order_without_consent_never_revokes_existing_consent(): void
    {
        // Unsubscribe is the only revocation path; an untick at checkout must not opt someone out.
        $user = $this->client(['marketing_opt_in' => true, 'marketing_opt_in_at' => now()]);

        $order = Order::create([
            'user_id' => $user->id,
            'type' => 'ecommerce', 'status' => 'paid', 'amount' => 100,
            'currency' => 'AED', 'reference' => 'EPO-KEEP',
            'meta' => ['marketing_opt_in' => false],
        ]);

        app(OrderService::class)->promoteOrderCustomer($order);

        $this->assertTrue((bool) $user->refresh()->marketing_opt_in, 'Consent must survive an order with no opt-in.');
    }

    public function test_service_package_purchase_records_consent_for_the_buyer(): void
    {
        Mail::fake();
        $user = $this->client(['marketing_opt_in' => false]);
        $this->authenticate($user);

        $package = ServicePackage::create([
            'name' => 'Consent package', 'price' => 100, 'currency' => 'AED',
            'validity_days' => 30, 'status' => 'active',
        ]);

        $intent = [
            'id' => 'pi_consent', 'status' => 'succeeded',
            'amount' => 10500, 'amount_received' => 10500,
            'metadata' => [
                'service_package_id' => (string) $package->id,
                'user_id' => (string) $user->id,
                'marketing_opt_in' => '1',
            ],
        ];

        $this->mock(StripeClient::class, function ($mock) use ($intent) {
            $mock->shouldReceive('retrievePaymentIntent')->andReturn($intent);
        });

        $this->postJson('/api/service-packages/confirm', ['paymentIntentId' => 'pi_consent'])
            ->assertStatus(200);

        $this->assertTrue((bool) $user->refresh()->marketing_opt_in);

        $order = Order::where('type', 'service_package')->first();
        $this->assertTrue((bool) ($order->meta['marketing_opt_in'] ?? false), 'Consent must persist on the order.');
    }

    public function test_a_guest_booking_records_consent_on_the_created_lead(): void
    {
        $customerService = app(CustomerService::class);

        $customer = $customerService->resolveForTransaction([
            'name' => 'Walk In', 'email' => 'Walk.In@Example.com', 'phone' => '+971501234567',
            'source' => 'booking', 'marketing_opt_in' => true,
        ]);

        $this->assertSame('walk.in@example.com', $customer->email, 'Email is normalized.');
        $this->assertSame('lead', $customer->customer_status, 'A booking alone does not create a client.');
        $this->assertTrue((bool) $customer->marketing_opt_in);
    }

    public function test_consent_is_granted_only_once_and_keeps_its_original_timestamp(): void
    {
        $user = $this->client(['marketing_opt_in' => false]);
        $service = app(CustomerService::class);

        $service->applyMarketingConsent($user, ['marketing_opt_in' => true]);
        $firstAt = $user->refresh()->marketing_opt_in_at;

        $service->applyMarketingConsent($user, ['marketing_opt_in' => true]);

        $this->assertEquals($firstAt, $user->refresh()->marketing_opt_in_at, 'Re-consent must not reset the timestamp.');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
