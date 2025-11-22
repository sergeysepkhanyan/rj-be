<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeUserPasswordRequest;
use App\Http\Requests\UpdateUserDetailsRequest;
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

    public function updateDetails(UpdateUserDetailsRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::error(['message' => 'Unauthorized'], 'Unauthorized');
            }

            $data = $request->validated();
            $user = $this->userService->updateUser($user->id, $data);

            return ApiResponse::success([
                'user' => new UserResource($user),
            ], 'User updated successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }

    public function changePassword(ChangeUserPasswordRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return ApiResponse::error(['message' => 'Unauthorized'], 'Unauthorized');
            }

            $data = $request->validated();
            $result = $this->userService->changePassword($user->id, $data);

            if (!$result['success']) {
                return ApiResponse::error(['message' => $result['message']], '', 422);
            }

            return ApiResponse::success([
                'user' => new UserResource($user),
            ], $result['message']);
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }
}
