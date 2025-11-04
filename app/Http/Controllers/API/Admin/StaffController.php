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

    public function store(Request $request): JsonResponse
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

    public function update(Request $request): JsonResponse
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
