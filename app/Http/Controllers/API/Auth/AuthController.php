<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleAuthRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SignupRequest;
use App\Http\Resources\UserResource;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ApiResponse;
use App\Services\BookingSelectionService;
use App\Services\CartService;
use App\Services\GoogleAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function signup(SignupRequest $request): JsonResponse
    {
        $data = $request->all();

        $roleId = UserRole::query()
            ->where('slug', 'client')
            ->value('id');

        $user = User::create([
            'user_role_id' => $roleId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        $redirectTo = $data['redirect_to'] ?? null;
        Mail::to($user->email)->queue(new VerifyEmailMail($user, $redirectTo));

        return ApiResponse::success([
            'success' => true,
        ], __('success.auth.register_verify_email'));
    }

    public function login(
        LoginRequest $request,
        BookingSelectionService $bookingSelectionService,
        CartService $cartService
    ): JsonResponse
    {
        $credentials = $request->only(['email', 'password']);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (!$user) {
            return ApiResponse::error(
                ['auth' => [__('errors.auth.invalid_credentials')]],
                __('errors.auth.invalid_credentials'),
                401
            );
        }

        // Allow 'active' users and 'pending' users with temporary password (first login)
        $status = $user->status ?? null;
        $isPendingWithTempPassword = $status === 'pending' && $user->is_temporary_password;
        if ($status !== 'active' && !$isPendingWithTempPassword) {
            return ApiResponse::error(
                ['auth' => [__('errors.auth.account_inactive')]],
                __('errors.auth.account_inactive'),
                401
            );
        }

        // Only require email verification for non-pending users
        if (!$isPendingWithTempPassword && !$user->hasVerifiedEmail()) {
            return ApiResponse::error(
                ['email' => [__('errors.auth.email_not_verified')]],
                __('errors.auth.email_not_verified'),
                403
            );
        }

        if ($user->is_temporary_password && $user->temporary_password_hash) {
            $isUsingTemporaryPassword = Hash::check($credentials['password'], $user->temporary_password_hash);
            
            if ($isUsingTemporaryPassword && $user->temporary_password_used_at) {
                return ApiResponse::error(
                    ['auth' => [__('errors.auth.temporary_password_already_used')]],
                    __('errors.auth.temporary_password_already_used'),
                    401
                );
            }
        }

        if (!$token = auth()->attempt($credentials)) {
            return ApiResponse::error(
                ['auth' => [__('errors.auth.invalid_credentials')]],
                __('errors.auth.invalid_credentials'),
                401
            );
        }

        $user = auth()->user()->load(['role', 'referral'])->loadCount('clientBookings');

        if ($user->is_temporary_password && !$user->temporary_password_used_at && $user->temporary_password_hash) {
            $isUsingTemporaryPassword = Hash::check($credentials['password'], $user->temporary_password_hash);
            if ($isUsingTemporaryPassword) {
                $user->update(['temporary_password_used_at' => now()]);
                $user->refresh();
            }
        }

        $guestSessionId = $request->input('guest_session_id')
            ?? $request->input('guestSessionId')
            ?? $request->header('X-Guest-Session-Id')
            ?? $request->header('X-Guest-Session')
            ?? $request->cookie('guest_session_id');
        if ($guestSessionId) {
            $bookingSelectionService->attachGuestSelectionsToUser($guestSessionId, $user->id);
            $cartService->mergeGuestCartToUser($guestSessionId, $user->id);
        }

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
        ], __('success.auth.logged_in'));
    }

    public function google(
        GoogleAuthRequest $request,
        GoogleAuthService $googleAuthService,
        BookingSelectionService $bookingSelectionService,
        CartService $cartService
    ): JsonResponse
    {
        $credential = $request->input('credential');

        // Supports Google ID token (JWT from Sign-In / One Tap) or OAuth2 access token
        $googleUser = $googleAuthService->verifyGoogleCredential($credential);

        if (!$googleUser) {
            return ApiResponse::error(
                ['auth' => [__('errors.auth.google_auth_failed')]],
                __('errors.auth.google_auth_failed'),
                401
            );
        }

        // Find or create user
        $user = $googleAuthService->findOrCreateUser($googleUser);

        // Check if user is active
        if (($user->status ?? null) !== 'active') {
            return ApiResponse::error(
                ['auth' => [__('errors.auth.account_inactive')]],
                __('errors.auth.account_inactive'),
                401
            );
        }

        // Generate JWT token
        $token = auth()->login($user);

        if (!$token) {
            return ApiResponse::error(
                ['auth' => [__('errors.auth.google_auth_failed')]],
                __('errors.auth.google_auth_failed'),
                401
            );
        }

        $user = auth()->user()->load(['role', 'referral'])->loadCount('clientBookings');

        // Handle guest session merging
        $guestSessionId = $request->input('guest_session_id')
            ?? $request->input('guestSessionId')
            ?? $request->header('X-Guest-Session-Id')
            ?? $request->header('X-Guest-Session')
            ?? $request->cookie('guest_session_id');

        if ($guestSessionId) {
            $bookingSelectionService->attachGuestSelectionsToUser($guestSessionId, $user->id);
            $cartService->mergeGuestCartToUser($guestSessionId, $user->id);
        }

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
        ], __('success.auth.logged_in'));
    }

    public function logout(): JsonResponse
    {
        auth()->logout();

        return ApiResponse::success([
            'success' => true,
        ], __('success.auth.logged_out'));
    }

    public function refresh(): JsonResponse
    {
        return ApiResponse::success([
            'token' => auth()->refresh(),
        ], __('success.auth.token_refreshed'));
    }
}

