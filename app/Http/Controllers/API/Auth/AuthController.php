<?php

namespace App\Http\Controllers\API\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SignupRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ApiResponse;
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

        $token = auth()->login($user);
        return ApiResponse::success([[
            'user' => new UserResource($user),
            'token' => $token,
        ]], 'Successfully registered');
    }


    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $credentials = ['email' => $data['email'], 'password' => $data['password']];

        if (! $token = auth()->attempt($credentials)) {
            return ApiResponse::error(
                ['credentials' => ['Invalid email or password']],
                'Authentication failed',
                401
            );
        }

        $user = auth()->user();

        return ApiResponse::success([
            [
                'user' => new UserResource($user),
                'token' => $token,
            ]
        ], 'Successfully logged in');
    }
}
