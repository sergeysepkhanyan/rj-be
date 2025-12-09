<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\MasterResource;
use App\Models\SubService;
use App\Services\ApiResponse;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubServiceMastersController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(SubService $subservice): JsonResponse
    {
        $masters = $this->userService->getMastersForSubservice($subservice->id);

        return ApiResponse::success([
            'masters' => MasterResource::collection($masters),
        ]);
    }
}
