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

        if (($data['role'] ?? null) === 'marketer' && !$this->userService->canAddMarketers(1)) {
            return ApiResponse::error(
                ['role' => [__('errors.staff.marketer_limit')]],
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

    public function destroy(User $user, Request $request): JsonResponse
    {
        // Cannot deactivate superadmins or clients
        if (in_array($user->role->slug, ['superadmin', 'client'], true)) {
            return ApiResponse::error(
                ['role' => [__('errors.staff.cannot_delete_user_type')]],
                __('errors.common.forbidden'),
                403
            );
        }

        // Cannot deactivate yourself
        if ((int) $user->id === (int) auth()->id()) {
            return ApiResponse::error(
                ['role' => ['You cannot deactivate your own account.']],
                __('errors.common.forbidden'),
                403
            );
        }

        $forceParam = $request->query('force', $request->input('force'));
        $force = filter_var($forceParam, FILTER_VALIDATE_BOOLEAN);

        if (!$force) {
            $now = now();
            $upcomingBookings = \App\Models\Booking::query()
                ->where(function ($q) use ($user) {
                    $q->where('master_id', $user->id)
                      ->orWhereHas('services', function ($qs) use ($user) {
                          $qs->where('master_id', $user->id);
                      });
                })
                ->whereNotIn('status', ['cancelled', 'completed', 'no_show'])
                ->where(function ($q) use ($now) {
                    $q->where('date', '>', $now->toDateString())
                      ->orWhere(function ($qq) use ($now) {
                          $qq->where('date', '=', $now->toDateString())
                             ->where('start_time', '>=', $now->format('H:i:s'));
                      });
                })
                ->orderBy('date')
                ->orderBy('start_time')
                ->limit(50)
                ->get(['id', 'date', 'start_time', 'end_time', 'customer_name', 'status']);

            if ($upcomingBookings->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff member has upcoming bookings.',
                    'errors' => [
                        'upcoming_bookings_count' => $upcomingBookings->count(),
                    ],
                    'data' => [
                        'upcomingBookingsCount' => $upcomingBookings->count(),
                        'upcomingBookings' => $upcomingBookings->map(function ($b) {
                            return [
                                'id' => $b->id,
                                'date' => $b->date instanceof \Carbon\Carbon ? $b->date->toDateString() : $b->date,
                                'startTime' => (string) $b->start_time,
                                'endTime' => (string) $b->end_time,
                                'customerName' => $b->customer_name,
                                'status' => $b->status,
                            ];
                        })->values(),
                    ],
                    'requiresConfirmation' => true,
                ], 409);
            }
        }

        $this->userService->deleteUser($user);

        return ApiResponse::success([], __('success.staff.deleted'));
    }

    public function resetPassword(User $user): JsonResponse
    {
        // Cannot reset password for superadmin
        if ($user->role->slug === 'superadmin') {
            return ApiResponse::error(
                ['role' => [__('errors.staff.cannot_reset_superadmin_password')]],
                __('errors.common.forbidden'),
                403
            );
        }

        $this->userService->resetStaffPassword($user);

        return ApiResponse::success([], __('success.staff.password_reset'));
    }

    public function restore(int $id): JsonResponse
    {
        $user = $this->userService->restoreUser($id);

        return ApiResponse::success([
            'user' => new StaffResource($user),
        ], __('success.staff.restored'));
    }
}

