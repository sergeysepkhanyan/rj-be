<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\ApiResponse;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function updateDetails(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::error(['message' => 'Unauthorized'], 'Unauthorized');
            }
            $data = $request->validate([
                'name'  => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
                'mobile' => 'sometimes|required|string|unique:users,mobile,' . $user->id,
                'date_of_birth' => 'sometimes|required|date|date_format:Y-m-d|before_or_equal:' . now()->subYears(18)->toDateString(),
            ]);

            $user = $this->userService->updateUser($user->id, $data);
            return ApiResponse::success([
                'user' => new UserResource($user),
            ], 'User updated successfully.');
        } catch (\Exception $e){
            return ApiResponse::error();
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::error(['message' => 'Unauthorized'], 'Unauthorized');
            }

            $data = $request->validate([
                'old_password' => 'required|string',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $result = $this->userService->changePassword($user->id, $data);

            if (!$result['success']) {
                return ApiResponse::error(['message' => $result['message']], '' , 422);
            }

            return ApiResponse::success([
                'user' => new UserResource($user),
            ], $result['message']);
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }


}
