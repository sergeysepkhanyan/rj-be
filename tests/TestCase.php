<?php

namespace Tests;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected ?string $authToken = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\UserRolesSeeder::class);
    }

    protected function actingAsAdmin(): User
    {
        $role = UserRole::where('slug', 'admin')->first();
        $user = User::factory()->create([
            'user_role_id' => $role->id,
            'email_verified_at' => now(),
        ]);
        $this->authToken = JWTAuth::fromUser($user);
        return $user;
    }

    protected function actingAsMarketer(): User
    {
        $role = UserRole::where('slug', 'marketer')->first();
        $user = User::factory()->create([
            'user_role_id' => $role->id,
            'email_verified_at' => now(),
        ]);
        $this->authToken = JWTAuth::fromUser($user);
        return $user;
    }

    protected function actingAsSuperAdmin(): User
    {
        $role = UserRole::where('slug', 'superadmin')->first();
        $user = User::factory()->create([
            'user_role_id' => $role->id,
            'email_verified_at' => now(),
        ]);
        $this->authToken = JWTAuth::fromUser($user);
        return $user;
    }

    public function getJson($uri, array $headers = [], $options = 0)
    {
        if ($this->authToken) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }
        return parent::getJson($uri, $headers, $options);
    }

    public function postJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        if ($this->authToken) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }
        return parent::postJson($uri, $data, $headers, $options);
    }

    public function putJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        if ($this->authToken) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }
        return parent::putJson($uri, $data, $headers, $options);
    }

    public function patchJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        if ($this->authToken) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }
        return parent::patchJson($uri, $data, $headers, $options);
    }

    public function deleteJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        if ($this->authToken) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }
        return parent::deleteJson($uri, $data, $headers, $options);
    }
}
