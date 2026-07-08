<?php

namespace Tests\Feature;

use App\Mail\ComplimentaryRewardRedeemedMail;
use App\Models\ComplimentaryReward;
use App\Models\SubService;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * An in-store (admin) redemption has no booking confirmation to signal it, so the
 * client must be emailed that their complimentary reward was used.
 */
class RewardRedeemNotificationTest extends TestCase
{
    public function test_in_store_redeem_marks_redeemed_and_emails_the_client(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $client = User::factory()->create();
        $subService = SubService::create([
            'name' => 'Trim', 'price' => 100, 'currency' => 'AED',
            'duration' => 30, 'duration_unit' => 'minutes',
        ]);
        $reward = ComplimentaryReward::create([
            'user_id' => $client->id, 'sub_service_id' => $subService->id,
            'status' => 'available', 'earned_at' => now(),
        ]);

        $this->postJson("/api/admin/clients/{$client->id}/rewards/{$reward->id}/redeem")
            ->assertSuccessful();

        $this->assertSame('redeemed', $reward->fresh()->status);
        Mail::assertQueued(
            ComplimentaryRewardRedeemedMail::class,
            fn (ComplimentaryRewardRedeemedMail $m) => $m->hasTo($client->email)
        );
    }
}
