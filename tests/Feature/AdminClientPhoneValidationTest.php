<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Admin client CRUD used a weaker inline regex than the rest of the API, so a UAE number
 * rejected at booking/checkout could still be stored by an admin.
 */
class AdminClientPhoneValidationTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function invalidUaeNumbers(): array
    {
        return [
            'too short (5 national digits)' => ['+97112345'],
            'eight national digits' => ['+97150123456'],
            'ten national digits' => ['+9715012345678'],
            'implausibly short' => ['+9711'],
        ];
    }

    #[DataProvider('invalidUaeNumbers')]
    public function test_admin_cannot_create_a_client_with_an_invalid_uae_phone(string $mobile): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/clients', [
            'firstName' => 'Test',
            'email' => 'phone.check@example.com',
            'mobile' => $mobile,
        ])->assertStatus(422)->assertJsonValidationErrors('mobile');

        $this->assertDatabaseMissing('users', ['email' => 'phone.check@example.com']);
    }

    public function test_admin_can_create_a_client_with_a_valid_uae_phone(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/clients', [
            'firstName' => 'Valid',
            'email' => 'Valid.Case@Example.COM',
            'mobile' => '+971501234567',
        ])->assertSuccessful();

        // CRM7: the account email is stored normalized.
        $this->assertDatabaseHas('users', ['email' => 'valid.case@example.com']);
    }

    public function test_admin_can_create_a_client_with_a_valid_international_phone(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/clients', [
            'firstName' => 'Intl',
            'email' => 'intl@example.com',
            'mobile' => '+15551234567',
        ])->assertSuccessful();
    }

    public function test_admin_cannot_update_a_client_to_an_invalid_uae_phone(): void
    {
        $this->actingAsAdmin();
        $client = User::factory()->create([
            'user_role_id' => UserRole::where('slug', 'client')->first()->id,
            'mobile' => '+971501234567',
        ]);

        $this->patchJson("/api/admin/clients/{$client->id}", ['mobile' => '+97150123456'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mobile');

        $this->assertSame('+971501234567', $client->refresh()->mobile);
    }
}
