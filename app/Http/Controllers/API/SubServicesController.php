<?php

namespace App\Http\Controllers\API;


use App\Http\Resources\ServiceResource;
use App\Http\Resources\SubServiceResource;
use App\Models\Service;
use App\Services\ApiResponse;
use App\Services\SubServiceManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class SubServicesController
{
    protected SubServiceManagerService $subServiceManagerService;

    public function __construct(SubServiceManagerService $subServiceManagerService)
    {
        $this->subServiceManagerService = $subServiceManagerService;
    }

    public function index(int $serviceId): JsonResponse
    {
        $subservices = $this->subServiceManagerService->getByServiceId($serviceId);
        return ApiResponse::success([
            'subservices' => SubServiceResource::collection($subservices),
        ]);
    }
}
