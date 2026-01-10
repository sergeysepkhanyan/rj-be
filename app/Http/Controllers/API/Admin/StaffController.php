<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\User;
use App\Services\ApiResponse;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $page    = (int) $request->get('page', 1);

        $staff = $this->userService->getPaginatedStaff($perPage, $page);

        return ApiResponse::success([
            'users' => StaffResource::collection($staff),
            'meta' => [
                'current_page' => $staff->currentPage(),
                'last_page' => $staff->lastPage(),
                'per_page' => $staff->perPage(),
                'total' => $staff->total(),
            ],
            'links' => [
                'first' => $staff->url(1),
                'last' => $staff->url($staff->lastPage()),
                'prev' => $staff->previousPageUrl(),
                'next' => $staff->nextPageUrl(),
            ],
        ], __('success.staff.list'));
    }

    public function store(StoreStaffRequest $request): JsonResponse
    {
        $data = $request->all();

        if (($data['role'] ?? null) === 'admin' && !$this->userService->canAddAdmins(1)) {
            return ApiResponse::error(
                ['role' => [__('errors.staff.admin_limit')]],
                __('validation.failed'),
                422
            );
        }

        $staff = $this->userService->createUser($data);

        return ApiResponse::success([
            'user' => new StaffResource($staff),
        ], __('success.staff.created'), 201);
    }

    public function update(UpdateStaffRequest $request, User $user): JsonResponse
    {
        $data = $request->all();
        $user = $this->userService->updateStaffMember($user, $data);

        return ApiResponse::success([
            'user' => new StaffResource($user),
        ], __('success.staff.updated'));
    }

    public function destroy(User $user): JsonResponse
    {
        if (in_array($user->role->slug, ['superadmin', 'client'], true)) {
            return ApiResponse::error(
                ['role' => [__('errors.staff.cannot_delete_user_type')]],
                __('errors.common.forbidden'),
                403
            );
        }

        $this->userService->deleteUser($user->id);

        return ApiResponse::success([], __('success.staff.deleted'));
    }

    public function restore(int $id): JsonResponse
    {
        $user = $this->userService->restoreUser($id);

        return ApiResponse::success([
            'user' => new StaffResource($user),
        ], __('success.staff.restored'));
    }
}

