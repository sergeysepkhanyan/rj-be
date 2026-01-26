<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Country;
use App\Models\User;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AddressCountryTest extends TestCase
{
    public function test_address_requires_country_instead_of_state(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->authToken = JWTAuth::fromUser($user);

        $country = Country::firstOrCreate(
            ['code' => 'AE'],
            ['name' => 'United Arab Emirates', 'enabled' => true]
        );

        $response = $this->postJson('/api/addresses', [
            'type' => 'shipping',
            'name' => 'John Doe',
            'mobile' => '+971501234567',
            'address' => '123 Main St',
            'city' => 'Dubai',
            'countryId' => $country->id,
            'zipCode' => '12345',
        ]);

        $response->assertStatus(200);
        
        $address = Address::with('country')->first();
        $this->assertEquals($country->id, $address->country_id);
        $this->assertEquals('United Arab Emirates', $address->country->name);
        $this->assertNull($address->state ?? null);
    }

    public function test_zip_code_is_optional(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->authToken = JWTAuth::fromUser($user);

        $country = Country::firstOrCreate(
            ['code' => 'AE'],
            ['name' => 'United Arab Emirates', 'enabled' => true]
        );

        $response = $this->postJson('/api/addresses', [
            'type' => 'shipping',
            'name' => 'John Doe',
            'mobile' => '+971501234567',
            'address' => '123 Main St',
            'city' => 'Dubai',
            'countryId' => $country->id,
        ]);

        $response->assertStatus(200);
        
        $address = Address::first();
        $this->assertNull($address->zip_code);
    }
}
