<?php

namespace App\Http\Controllers\API\Auth;
use App\Http\Controllers\Controller;
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
    public function signup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
//            'mobile' => 'nullable|string|unique:users,mobile',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error($validator->errors(), 'Validation failed', 422);
        }

        $roleId = UserRole::where('slug', 'client')->first()->id;
        $user = User::create([
            'user_role_id' => $roleId,
            'name' => $request->name ?? null,
            'email' => $request->email,
            'mobile' => $request->mobile ?? null,
            'password' => Hash::make($request->password),
        ]);

        $token = auth()->login($user);
        return ApiResponse::success([[
            'user' => new UserResource($user),
            'token' => $token,
        ]], 'Successfully registered');
    }


    public function login(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error($validator->errors(), 'Validation failed', 422);
            }
            $password = $request->input('password');
            $credentials = ['email' => $request->email, 'password' => $password];

            if (! $token = auth()->attempt($credentials)) {
                return ApiResponse::error(
                    ['credentials' => ['Invalid email or password']],
                    'Authentication failed',
                    401
                );
            }

            $user = auth()->user();

            return ApiResponse::success([[
                'user' => new UserResource($user),
                'token' => $token,
            ]], 'Successfully logged in');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }

    }


}
