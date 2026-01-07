<?php

namespace App\Http\Controllers\API;

use App\Filters\ServiceFilter;
use App\Http\Resources\AdminServiceResource;
use App\Http\Resources\ServiceResource;
use App\Services\ApiResponse;
use App\Services\ServiceManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class ServicesController
{
    protected ServiceManagerService $serviceManagerService;

    public function __construct(ServiceManagerService $serviceManagerService)
    {
        $this->serviceManagerService = $serviceManagerService;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'category_id' => $request->query('category_id'),
            'name'        => $request->query('name'),
        ];

        $services = $this->serviceManagerService->getAllServices($filters);

        return ApiResponse::success([
            'services' => ServiceResource::collection($services),
        ]);
    }

    public function getByIds(Request $request): JsonResponse
    {
        $ids = $request->get('ids', []);
        $services = $this->serviceManagerService->getByIds($ids);

        return ApiResponse::success([
            'services' => ServiceResource::collection($services),
        ]);
    }
}
