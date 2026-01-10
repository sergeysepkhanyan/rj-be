<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Services\ApiResponse;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class StaffController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function getMasters(): JsonResponse
    {
        return ApiResponse::success([
            'masters' => StaffResource::collection($this->userService->getMasters()),
        ], __('success.staff.masters_listed'));
    }
}

