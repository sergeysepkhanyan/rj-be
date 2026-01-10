<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeUserPasswordRequest;
use App\Http\Requests\UpdateUserDetailsRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ApiResponse;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class UsersController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function updateDetails(UpdateUserDetailsRequest $request): JsonResponse
    {
        $authUser = auth()->user();

        if (! $authUser) {
            return ApiResponse::error(null, __('auth.unauthenticated'), 401);
        }

        $user = $this->userService->getUserById($authUser->id);

        $data = $request->all();
        $data = array_intersect_key($data, array_flip((new User)->getFillable()));

        $user = $this->userService->updateUser($user, $data);

        return ApiResponse::success([
            'user' => new UserResource($user),
        ], __('success.user.updated'));
    }

    public function changePassword(ChangeUserPasswordRequest $request): JsonResponse
    {
        $authUser = auth()->user();

        if (! $authUser) {
            return ApiResponse::error(null, __('auth.unauthenticated'), 401);
        }

        $data = $request->only(['password', 'old_password']);

        try {
            $result = $this->userService->changePassword($authUser->id, $data);

            if (!($result['success'] ?? false)) {
                $msg = $result['message_key']
                    ? __($result['message_key'])
                    : ($result['message'] ?? __('messages.something_went_wrong'));

                return ApiResponse::error(
                    ['password' => [$msg]],
                    __('validation.failed'),
                    422
                );
            }

            return ApiResponse::success([
                'user' => new UserResource($this->userService->getUserById($authUser->id)),
            ], __('success.user.password_changed'));

        } catch (\Throwable $e) {
            return ApiResponse::error(null, __('messages.something_went_wrong'), 500);
        }
    }
}
