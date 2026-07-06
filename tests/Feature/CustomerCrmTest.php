<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\CustomerService;
use Tests\TestCase;

/**
 * Covers the unified customer model: Lead→Client promotion (forward-only),
 * email-as-unique-key dedup, phone-as-match-signal duplicates, staff merge,
 * and opt-in-only marketing consent.
 */
class CustomerCrmTest extends TestCase
{
    private function svc(): CustomerService
    {
        return app(CustomerService::class);
    }

    public function test_capture_promotes_lead_to_client_and_is_forward_only(): void
    {
        $svc = $this->svc();
        $customer = $svc->resolveForTransaction([
            'email' => 'lead@example.com',
            'phone' => '+971500000001',
            'name' => 'Lead Person',
        ]);

        $this->assertSame('lead', $customer->customer_status);
        $this->assertFalse((bool) $customer->has_account, 'a guest transaction must not create an account');

        $svc->markTransacted($customer, now()->subDay());
        $customer->refresh();

        $this->assertSame('client', $customer->customer_status);
        $this->assertNotNull($customer->first_transacted_at);
        $firstAt = $customer->first_transacted_at->timestamp;

        // Forward-only: a later transaction never moves the timestamp or reverts status.
        $svc->markTransacted($customer, now());
        $customer->refresh();

        $this->assertSame('client', $customer->customer_status);
        $this->assertSame($firstAt, $customer->first_transacted_at->timestamp);
    }

    public function test_email_is_the_unique_key_and_dedupes_case_insensitively(): void
    {
        $svc = $this->svc();
        $a = $svc->resolveForTransaction(['email' => 'dup@example.com', 'phone' => '+971500000002']);
        $b = $svc->resolveForTransaction(['email' => 'DUP@Example.com', 'phone' => '+971500000003']);

        $this->assertSame($a->id, $b->id, 'same email (any case) must resolve to one record');
        $this->assertSame(1, User::where('email', 'dup@example.com')->count());
    }

    public function test_shared_phone_creates_two_records_flagged_as_possible_duplicates(): void
    {
        $svc = $this->svc();
        $a = $svc->resolveForTransaction(['email' => 'a@example.com', 'phone' => '+971509999999']);
        $b = $svc->resolveForTransaction(['email' => 'b@example.com', 'phone' => '+971509999999']);

        $this->assertNotSame($a->id, $b->id, 'phone is a match signal, not a unique key');

        $dupes = $svc->possibleDuplicates($a);
        $this->assertTrue($dupes->contains('id', $b->id), 'same phone + different email must be flagged');
    }

    public function test_merge_reassigns_history_promotes_forward_and_soft_deletes_duplicate(): void
    {
        $svc = $this->svc();
        $primary = $svc->resolveForTransaction(['email' => 'primary@example.com', 'phone' => '+971501111111']);
        $duplicate = $svc->resolveForTransaction(['email' => 'dupe@example.com', 'phone' => '+971501111111']);

        // The duplicate has transacted (is a Client) and owns an order.
        $svc->markTransacted($duplicate, now());
        $order = Order::create([
            'user_id' => $duplicate->id,
            'type' => 'ecommerce',
            'status' => 'paid',
            'amount' => 100,
            'currency' => 'AED',
            'reference' => 'TEST-' . uniqid(),
            'paid_at' => now(),
        ]);

        $svc->mergeCustomers($primary, $duplicate);

        $primary->refresh();
        $this->assertSame('client', $primary->customer_status, 'merge promotes the primary forward-only');
        $this->assertSame($primary->id, $order->fresh()->user_id, 'the order moves to the primary');
        $this->assertSoftDeleted('users', ['id' => $duplicate->id]);
    }

    public function test_marketing_consent_is_opt_in_only_and_never_opts_out(): void
    {
        $svc = $this->svc();
        $customer = $svc->resolveForTransaction([
            'email' => 'consent@example.com',
            'phone' => '+971502222222',
            'marketing_opt_in' => false,
        ]);
        $this->assertFalse((bool) $customer->marketing_opt_in);

        $svc->applyMarketingConsent($customer, ['marketing_opt_in' => true]);
        $this->assertTrue((bool) $customer->fresh()->marketing_opt_in);
        $this->assertNotNull($customer->fresh()->marketing_opt_in_at);

        // Passing false must NOT revoke consent (opt-out is only via unsubscribe).
        $svc->applyMarketingConsent($customer, ['marketing_opt_in' => false]);
        $this->assertTrue((bool) $customer->fresh()->marketing_opt_in);
    }
}
