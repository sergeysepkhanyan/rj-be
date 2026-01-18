<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SignupRequest;
use App\Http\Resources\UserResource;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ApiResponse;
use App\Services\BookingSelectionService;
use App\Services\CartService;
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
            'name' => $data['name'] ?? null,
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        Mail::to($user->email)->send(new VerifyEmailMail($user));

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

        if (!$user || ($user->status ?? null) !== 'active') {
            return ApiResponse::error(
                ['auth' => [__('errors.auth.invalid_credentials')]],
                __('errors.auth.invalid_credentials'),
                401
            );
        }

        if (!$user->hasVerifiedEmail()) {
            return ApiResponse::error(
                ['email' => [__('errors.auth.email_not_verified')]],
                __('errors.auth.email_not_verified'),
                403
            );
        }

        if (!$token = auth()->attempt($credentials)) {
            return ApiResponse::error(
                ['auth' => [__('errors.auth.invalid_credentials')]],
                __('errors.auth.invalid_credentials'),
                401
            );
        }

        $user = auth()->user()->load('role');

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

