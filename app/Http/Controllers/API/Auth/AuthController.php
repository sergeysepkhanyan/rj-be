<?php

namespace App\Http\Controllers\API\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SignupRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserRole;
use App\Repositories\UserRepository;
use App\Services\ApiResponse;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function signup(SignupRequest $request): JsonResponse
    {
        $data = $request->all();
        $roleId = UserRole::where('slug', 'client')->first()->id;
        $user = User::create([
            'user_role_id' => $roleId,
            'name' => $data['name'] ?? null,
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        $user->sendEmailVerificationNotification();
        return ApiResponse::success([
            'success' => true,
        ], 'Registration successful. Please verify your email.');
    }


    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password']);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (!$user || $user->status !== 'active') {
            return ApiResponse::error([
                'success' => false,
            ], 'Invalid credentials', 401);
        }

        if (!$user->hasVerifiedEmail()) {
            return ApiResponse::error([
                'success' => false,
            ], 'Please verify your email first.', 403);
        }

        if (!$token = auth()->attempt($credentials)) {
            return ApiResponse::error([
                'success' => false,
            ], 'Invalid credentials', 401);
        }

        $user = auth()->user()->load('role');
        return ApiResponse::success([
            [
                'user' => new UserResource($user),
                'token' => $token,
            ]
        ], 'Successfully logged in');
    }

    public function logout(): JsonResponse
    {
        auth()->logout();
        return ApiResponse::success([
            'success' => true,
        ], 'Logged out successfully.');
    }

    public function refresh(): JsonResponse
    {
        return ApiResponse::success(['token' => auth()->refresh()], 'Successfully logged in.');
    }
}
