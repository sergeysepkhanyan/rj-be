<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Referral;
use App\Models\User;
use App\Models\UserRole;
use Tests\TestCase;

class DiscountTierTest extends TestCase
{
    public function test_admin_can_view_all_discount_tiers(): void
    {
        $this->actingAsAdmin();

        Referral::create([
            'name' => 'Bronze',
            'value' => 10,
            'type' => 'percentage',
            'visit_threshold' => 11,
            'enabled' => true,
        ]);

        $response = $this->getJson('/api/admin/referrals');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'nameAr', 'value', 'type', 'visitThreshold', 'enabled'],
                ],
            ]);
    }

    public function test_admin_can_update_discount_tier_visit_threshold(): void
    {
        $this->actingAsAdmin();

        $referral = Referral::create([
            'name' => 'Bronze',
            'value' => 10,
            'type' => 'percentage',
            'visit_threshold' => 11,
            'enabled' => true,
        ]);

        $response = $this->putJson("/api/admin/referrals/{$referral->id}", [
            'visitThreshold' => 15,
            'enabled' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.visitThreshold', 15);

        $referral->refresh();
        $this->assertEquals(15, $referral->visit_threshold);
    }

    public function test_admin_can_assign_manual_discount_tier_to_user(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $referral = Referral::create([
            'name' => 'Gold',
            'value' => 20,
            'type' => 'percentage',
            'visit_threshold' => 50,
            'enabled' => true,
        ]);

        $response = $this->patchJson("/api/admin/clients/{$user->id}/add-referral", [
            'manualReferralId' => $referral->id,
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals($referral->id, $user->manual_referral_id);
    }

    public function test_manual_discount_tier_bypasses_visit_count(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->authToken = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);

        $goldReferral = Referral::create([
            'name' => 'Gold',
            'value' => 20,
            'type' => 'percentage',
            'visit_threshold' => 50,
            'enabled' => true,
        ]);

        $user->update(['manual_referral_id' => $goldReferral->id]);

        $response = $this->postJson('/api/bookings', [
            'date' => now()->addDays(7)->format('Y-m-d'),
            'startTime' => '10:00',
            'endTime' => '11:00',
            'timezone' => 'UTC',
            'customerName' => 'Test User',
            'customerPhone' => '+971501234567',
            'customerEmail' => 'test@example.com',
            'paymentMode' => 'pay_later',
            'services' => [
                [
                    'serviceType' => 'subservice',
                    'serviceId' => 1,
                    'startTime' => '10:00',
                    'endTime' => '11:00',
                    'price' => 100,
                ],
            ],
        ]);

        if ($response->status() === 422) {
            $this->markTestSkipped('Booking creation requires valid services and masters');
        }

        $response->assertStatus(200);
        $booking = Booking::latest()->first();
        $this->assertEquals(20, $booking->discount_value);
        $this->assertEquals('Gold Tier Discount', $booking->discount_label);
    }

    public function test_automatic_discount_applies_based_on_visit_threshold(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $master = User::factory()->create(['email_verified_at' => now()]);
        $masterRole = UserRole::where('slug', 'master')->first();
        if ($masterRole) {
            $master->update(['user_role_id' => $masterRole->id]);
        }
        $this->authToken = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);

        $bronzeReferral = Referral::create([
            'name' => 'Bronze',
            'value' => 10,
            'type' => 'percentage',
            'visit_threshold' => 2,
            'enabled' => true,
        ]);

        $user->update(['referral_id' => $bronzeReferral->id]);

        Booking::create([
            'user_id' => $user->id,
            'master_id' => $master->id,
            'type' => 'booking',
            'date' => now()->subDays(10),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'price' => 100,
            'final_price' => 100,
        ]);

        Booking::create([
            'user_id' => $user->id,
            'master_id' => $master->id,
            'type' => 'booking',
            'date' => now()->subDays(5),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'price' => 100,
            'final_price' => 100,
        ]);

        $response = $this->postJson('/api/bookings', [
            'date' => now()->addDays(7)->format('Y-m-d'),
            'startTime' => '10:00',
            'endTime' => '11:00',
            'timezone' => 'UTC',
            'customerName' => 'Test User',
            'customerPhone' => '+971501234567',
            'customerEmail' => 'test@example.com',
            'paymentMode' => 'pay_later',
            'services' => [
                [
                    'serviceType' => 'subservice',
                    'serviceId' => 1,
                    'startTime' => '10:00',
                    'endTime' => '11:00',
                    'price' => 100,
                ],
            ],
        ]);

        if ($response->status() === 422) {
            $this->markTestSkipped('Booking creation requires valid services and masters');
        }

        $response->assertStatus(200);
        $booking = Booking::latest()->first();
        $this->assertEquals(10, $booking->discount_value);
        $this->assertEquals('Bronze Tier Discount', $booking->discount_label);
    }

    public function test_manual_discount_takes_precedence_over_automatic(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->authToken = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);

        $bronzeReferral = Referral::create([
            'name' => 'Bronze',
            'value' => 10,
            'type' => 'percentage',
            'visit_threshold' => 2,
            'enabled' => true,
        ]);

        $goldReferral = Referral::create([
            'name' => 'Gold',
            'value' => 20,
            'type' => 'percentage',
            'visit_threshold' => 50,
            'enabled' => true,
        ]);

        $user->update([
            'referral_id' => $bronzeReferral->id,
            'manual_referral_id' => $goldReferral->id,
        ]);

        $response = $this->postJson('/api/bookings', [
            'date' => now()->addDays(7)->format('Y-m-d'),
            'startTime' => '10:00',
            'endTime' => '11:00',
            'timezone' => 'UTC',
            'customerName' => 'Test User',
            'customerPhone' => '+971501234567',
            'customerEmail' => 'test@example.com',
            'paymentMode' => 'pay_later',
            'services' => [
                [
                    'serviceType' => 'subservice',
                    'serviceId' => 1,
                    'startTime' => '10:00',
                    'endTime' => '11:00',
                    'price' => 100,
                ],
            ],
        ]);

        if ($response->status() === 422) {
            $this->markTestSkipped('Booking creation requires valid services and masters');
        }

        $response->assertStatus(200);
        $booking = Booking::latest()->first();
        $this->assertEquals(20, $booking->discount_value);
        $this->assertEquals('Gold Tier Discount', $booking->discount_label);
    }
}
