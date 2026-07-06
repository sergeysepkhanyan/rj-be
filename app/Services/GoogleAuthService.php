<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleAuthService
{
    /**
     * Verify Google credential from the client.
     *
     * Google Identity Services (One Tap / Sign-In button) sends an **ID token** (JWT) in `credential`.
     * Some clients may still send an OAuth2 **access token**; both are supported.
     */
    public function verifyGoogleCredential(string $credential): ?array
    {
        $credential = trim($credential);
        if ($credential === '') {
            return null;
        }

        if ($this->looksLikeJwt($credential)) {
            $parsed = $this->verifyIdToken($credential);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return $this->verifyOAuth2AccessToken($credential);
    }

    /**
     * @deprecated Use verifyGoogleCredential(); kept for clarity in stack traces.
     */
    public function verifyAccessToken(string $token): ?array
    {
        return $this->verifyGoogleCredential($token);
    }

    private function looksLikeJwt(string $value): bool
    {
        return substr_count($value, '.') === 2;
    }

    /**
     * Validate ID token via Google's tokeninfo (signature checked by Google).
     *
     * @see https://developers.google.com/identity/sign-in/web/backend-auth
     */
    private function verifyIdToken(string $jwt): ?array
    {
        try {
            $response = Http::timeout(15)->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $jwt,
            ]);

            if (!$response->successful()) {
                return null;
            }

            $payload = $response->json();
            if (!is_array($payload) || !empty($payload['error'])) {
                return null;
            }

            if (empty($payload['sub']) || empty($payload['email'])) {
                return null;
            }

            $exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;
            if ($exp > 0 && $exp < time()) {
                return null;
            }

            $iss = (string) ($payload['iss'] ?? '');
            if (!in_array($iss, ['https://accounts.google.com', 'accounts.google.com'], true)) {
                return null;
            }

            $aud = (string) ($payload['aud'] ?? '');
            $allowedAudiences = $this->allowedGoogleClientIds();
            if ($allowedAudiences !== [] && $aud !== '' && !in_array($aud, $allowedAudiences, true)) {
                \Log::warning('Google ID token aud does not match any configured GOOGLE_CLIENT_ID', [
                    'aud' => $aud,
                ]);
                return null;
            }

            $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

            return [
                'google_id' => $payload['sub'],
                'email' => $payload['email'],
                'email_verified' => $emailVerified,
                'first_name' => $payload['given_name'] ?? '',
                'last_name' => $payload['family_name'] ?? '',
                'name' => $payload['name'] ?? '',
                'picture' => $payload['picture'] ?? null,
            ];
        } catch (\Throwable $e) {
            \Log::error('Google ID token verification failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Legacy: OAuth2 access token → userinfo.
     */
    private function verifyOAuth2AccessToken(string $accessToken): ?array
    {
        try {
            $response = Http::timeout(15)->get('https://www.googleapis.com/oauth2/v3/userinfo', [
                'access_token' => $accessToken,
            ]);

            if (!$response->successful()) {
                return null;
            }

            $userData = $response->json();

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
            \Log::error('Google OAuth2 userinfo failed: ' . $e->getMessage());

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
        $googleId = $googleUser['google_id'];
        $email = $googleUser['email'];

        $user = User::where('google_id', $googleId)->first();
        if ($user) {
            return $user;
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->first();

        if ($user) {
            if ($user->google_id !== null && $user->google_id !== '' && $user->google_id !== $googleId) {
                $this->denyGoogleLink('errors.auth.google_account_mismatch');
            }

            $wasGuest = ! $user->has_account;
            $user->update([
                'google_id' => $googleId,
                'has_account' => true,
                'email_verified_at' => $user->email_verified_at ?? ($googleUser['email_verified'] ? now() : null),
            ]);
            $user->refresh();

            if ($wasGuest) {
                app(CustomerService::class)->linkGuestTransactions($user);
            }

            return $user;
        }

        return app(CustomerService::class)->registerOrUpgrade($email, [
            'first_name' => $googleUser['first_name'] ?: $this->extractFirstName($googleUser['name']),
            'last_name' => $googleUser['last_name'] ?: $this->extractLastName($googleUser['name']),
            'google_id' => $googleId,
            'email_verified_at' => $googleUser['email_verified'] ? now() : null,
            'password' => bcrypt(Str::random(32)),
            'status' => 'active',
            'registration_source' => 'online',
        ]);
    }

    private function denyGoogleLink(string $messageKey): never
    {
        throw new HttpResponseException(
            ApiResponse::error(
                ['auth' => [__($messageKey)]],
                __($messageKey),
                409
            )
        );
    }

    /**
     * @return list<string>
     */
    private function allowedGoogleClientIds(): array
    {
        $raw = (string) (config('services.google.client_id') ?? '');
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
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
