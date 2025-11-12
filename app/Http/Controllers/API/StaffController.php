<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Services\ApiResponse;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class StaffController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function getMasters(): JsonResponse
    {
        try {
            return ApiResponse::success([
                'masters' => StaffResource::collection($this->userService->getMasters()),
            ], 'Staff members added successfully');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }
}
