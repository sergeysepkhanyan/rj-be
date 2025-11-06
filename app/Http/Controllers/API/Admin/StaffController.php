<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\UserRole;
use App\Services\ApiResponse;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);

            $staff = $this->userService->getPaginatedStaff($perPage, $page);

            return ApiResponse::success([
                'users' => UserResource::collection($staff),
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
            ], 'Staff members retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }



    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->all();

            $validator = Validator::make($data, [
                'role' => 'required|in:admin,master',
                'name' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'mobile' => 'required|string|unique:users,mobile',
                'subservices' => 'array',
                'subservices.*' => 'exists:sub_services,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error($validator->errors(), 'Validation failed', 422);
            }
            if ($data['role'] === 'admin' && !$this->userService->canAddAdmins(1)) {
                return ApiResponse::error(null, 'You can only have up to 2 admin users', 422);
            }

            $staff = $this->userService-> createUser($data);

            return ApiResponse::success(new UserResource($staff), 'Staff member added successfully', 201);
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $staff = $this->userService->getUserById($id);

            if (!$staff) {
                return ApiResponse::error([], 'Staff member not found', 404);
            }
            $data = $request->all();

            $validator = Validator::make($data, [
                'role' => 'required|in:admin,master',
                'name' => 'required|string',
                'email' => "required_if:role,admin|email|unique:users,email,{$id}",
                'mobile' => "required_if:role,admin|string|unique:users,mobile,{$id}",
                'subservices' => 'array',
                'subservices.*' => 'exists:sub_services,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error($validator->errors(), 'Validation failed', 422);
            }

            $staff = $this->userService->updateStaffMember($id, $data);

            return ApiResponse::success(new UserResource($staff), 'Staff member updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $staff = $this->userService->getUserById($id);

            if (!$staff) {
                return ApiResponse::error([], 'Staff member not found', 404);
            }

            if (in_array($staff->role->slug, ['superadmin', 'client'])) {
                return ApiResponse::error([], 'You cannot delete this user type', 403);
            }

            $this->userService->deleteUser($id);

            return ApiResponse::success([], 'Staff member deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }


    public function createMany(Request $request): JsonResponse
    {
        try {

            $data = $request->all();

            if (!is_array($data)) {
                return ApiResponse::error(null, 'Invalid input format — expected an array of users', 422);
            }

            $validator = Validator::make($data, [
                '*.role' => 'required|in:admin,master',
                '*.name' => 'required|string',
                '*.email' => 'required|email|distinct|unique:users,email',
                '*.mobile' => 'required|string|distinct|unique:users,mobile',
                '*.subservices' => 'array',
                '*.subservices.*' => 'exists:sub_services,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error($validator->errors(), 'Validation failed', 422);
            }

            $newAdminsCount = collect($data)->where('role', 'admin')->count();

            if (!$this->userService->canAddAdmins($newAdminsCount)) {
                return ApiResponse::error(null, 'You can only have up to 2 admin users', 422);
            }

            $users = $this->userService->createStaffMembers($data);

            return ApiResponse::success(UserResource::collection($users), 'Staff members added successfully');
        } catch (\Exception $e) {
            ApiResponse::error();
        }
    }

    public function updateMany(Request $request): JsonResponse
    {
        try {
            $data = $request->all();
            $validator = Validator::make($data, [
                '*.role' => 'required|in:admin,master',
                '*.name' => 'required|string',
                '*.email' => [
                    'required_if:*.role,admin',
                    'email',
                    function ($attribute, $value, $fail) use ($request) {
                        $index = explode('.', $attribute)[0];
                        $id = $request->input($index . '.id');
                        $exists = \App\Models\User::where('email', $value)
                            ->when($id, fn($q) => $q->where('id', '!=', $id))
                            ->exists();
                        if ($exists) {
                            $fail("The email {$value} is already taken.");
                        }
                    }
                ],
                '*.mobile' => [
                    'required_if:*.role,admin',
                    'string',
                    function ($attribute, $value, $fail) use ($request) {
                        $index = explode('.', $attribute)[0];
                        $id = $request->input($index . '.id');
                        $exists = \App\Models\User::where('mobile', $value)
                            ->when($id, fn($q) => $q->where('id', '!=', $id))
                            ->exists();
                        if ($exists) {
                            $fail("The mobile {$value} is already taken.");
                        }
                    }
                ],
                '*.subservices' => 'array',
                '*.subservices.*' => 'exists:sub_services,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error($validator->errors(), 'Validation failed', 422);
            }
            $users = $this->userService->updateStaff($data);
            return ApiResponse::success(UserResource::collection($users), 'Staff updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }
}
