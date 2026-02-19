<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleAuthService
{
    /**
     * Verify Google access token and get user info
     *
     * @param string $accessToken
     * @return array|null
     */
    public function verifyAccessToken(string $accessToken): ?array
    {
        try {
            // Use Google's userinfo endpoint to get user data from access token
            $response = Http::get('https://www.googleapis.com/oauth2/v3/userinfo', [
                'access_token' => $accessToken,
            ]);

            if (!$response->successful()) {
                return null;
            }

            $userData = $response->json();

            // Validate required fields
            if (empty($userData['sub']) || empty($userData['email'])) {
                return null;
            }

            return [
                'google_id' => $userData['sub'],
                'email' => $userData['email'],
                'email_verified' => $userData['email_verified'] ?? false,
                'first_name' => $userData['given_name'] ?? '',
                'last_name' => $userData['family_name'] ?? '',
                'name' => $userData['name'] ?? '',
                'picture' => $userData['picture'] ?? null,
            ];
        } catch (\Exception $e) {
            \Log::error('Google token verification failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Find or create user from Google data
     *
     * @param array $googleUser
     * @return User
     */
    public function findOrCreateUser(array $googleUser): User
    {
        // First, check if user exists by google_id
        $user = User::where('google_id', $googleUser['google_id'])->first();

        if ($user) {
            return $user;
        }

        // Check if user exists by email
        $user = User::where('email', $googleUser['email'])->first();

        if ($user) {
            // Link Google account to existing user
            $user->update([
                'google_id' => $googleUser['google_id'],
            ]);
            return $user;
        }

        // Create new user
        $roleId = UserRole::query()
            ->where('slug', 'client')
            ->value('id');

        $user = User::create([
            'user_role_id' => $roleId,
            'first_name' => $googleUser['first_name'] ?: $this->extractFirstName($googleUser['name']),
            'last_name' => $googleUser['last_name'] ?: $this->extractLastName($googleUser['name']),
            'email' => $googleUser['email'],
            'google_id' => $googleUser['google_id'],
            'email_verified_at' => $googleUser['email_verified'] ? now() : null,
            'password' => bcrypt(Str::random(32)), // Random password for Google users
            'status' => 'active',
        ]);

        return $user;
    }

    /**
     * Extract first name from full name
     */
    private function extractFirstName(string $name): string
    {
        $parts = explode(' ', trim($name));
        return $parts[0] ?? 'User';
    }

    /**
     * Extract last name from full name
     */
    private function extractLastName(string $name): string
    {
        $parts = explode(' ', trim($name));
        array_shift($parts);
        return implode(' ', $parts) ?: '';
    }
}
