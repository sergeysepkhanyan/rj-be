<?php

namespace Tests\Feature;

use App\Models\ComplimentaryReward;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SubService;
use App\Models\User;
use App\Services\CustomerService;
use Tests\TestCase;

/**
 * HTTP-level coverage for the CRM features built this engagement:
 * signup phone-not-unique (G1), POS contact guardrail (G9), possible-duplicate
 * merge (G2), and staff in-store reward redemption (G13).
 */
class CrmEndpointsTest extends TestCase
{
    private function signupPayload(string $email): array
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'mobile' => '+971503333333',
            'date_of_birth' => '1990-01-01',
            'password' => 'secret123',
            'passwordConfirmation' => 'secret123',
        ];
    }

    public function test_two_accounts_can_register_with_the_same_phone(): void
    {
        $this->postJson('/api/auth/signup', $this->signupPayload('one@example.com'))->assertSuccessful();
        $this->postJson('/api/auth/signup', $this->signupPayload('two@example.com'))->assertSuccessful();

        $this->assertSame(2, User::where('mobile', '+971503333333')->count());
    }

    public function test_marketing_opt_in_is_captured_at_signup(): void
    {
        $this->postJson('/api/auth/signup', $this->signupPayload('optin@example.com') + ['marketing_opt_in' => true])
            ->assertSuccessful();

        $user = User::where('email', 'optin@example.com')->first();
        $this->assertTrue((bool) $user->marketing_opt_in);
        $this->assertNotNull($user->marketing_opt_in_at);
    }

    public function test_pos_order_requires_contact_unless_customer_declined(): void
    {
        $this->actingAsAdmin();
        $category = ProductCategory::factory()->create();
        $product = Product::create([
            'name' => 'POS Product',
            'product_category_id' => $category->id,
            'price' => 100,
            'currency' => 'AED',
            'status' => 'active',
            'max_quantity' => 10,
        ]);

        $base = [
            'customerName' => 'Walk In',
            'items' => [[
                'product_id' => $product->id,
                'name' => 'POS Product',
                'price' => 100,
                'quantity' => 1,
            ]],
            'subtotal' => 100,
            'total' => 100,
            'currency' => 'AED',
            'paymentMethod' => 'cash',
        ];

        // Blank email + phone and NOT declined → rejected (no silent blank record).
        $this->postJson('/api/admin/orders/in-store', $base)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customerEmail', 'customerPhone']);

        // Explicit decline → accepted (the decline is logged).
        $this->postJson('/api/admin/orders/in-store', $base + ['contactDeclined' => true])
            ->assertSuccessful();
    }

    public function test_admin_can_list_and_merge_possible_duplicates(): void
    {
        $this->actingAsAdmin();
        $svc = app(CustomerService::class);
        $primary = $svc->resolveForTransaction(['email' => 'pm@example.com', 'phone' => '+971504444444']);
        $duplicate = $svc->resolveForTransaction(['email' => 'dp@example.com', 'phone' => '+971504444444']);

        $this->getJson("/api/admin/clients/{$primary->id}/possible-duplicates")
            ->assertSuccessful()
            ->assertJsonFragment(['id' => $duplicate->id]);

        $this->postJson("/api/admin/clients/{$primary->id}/merge/{$duplicate->id}")
            ->assertSuccessful();

        $this->assertSoftDeleted('users', ['id' => $duplicate->id]);
    }

    public function test_staff_can_redeem_a_client_reward_in_store(): void
    {
        $this->actingAsAdmin();
        $svc = app(CustomerService::class);
        $client = $svc->resolveForTransaction(['email' => 'rw@example.com', 'phone' => '+971505555555']);
        $subService = SubService::create(['name' => 'Complimentary Blow-dry']);
        $reward = ComplimentaryReward::create([
            'user_id' => $client->id,
            'sub_service_id' => $subService->id,
            'status' => 'available',
            'earned_at' => now(),
        ]);

        $this->getJson("/api/admin/clients/{$client->id}/rewards")
            ->assertSuccessful()
            ->assertJsonFragment(['id' => $reward->id, 'status' => 'available']);

        $this->postJson("/api/admin/clients/{$client->id}/rewards/{$reward->id}/redeem")
            ->assertSuccessful();

        $reward->refresh();
        $this->assertSame('redeemed', $reward->status);
        $this->assertNotNull($reward->redeemed_at);
    }
}
