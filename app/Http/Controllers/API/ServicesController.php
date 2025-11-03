<?php

namespace App\Http\Controllers\API;

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
        $search = $request->query('search');
        $perPage = $request->query('per_page', 10);
        $services = $this->serviceManagerService->getPaginatedServices($search, $perPage);

        return ApiResponse::success([
            'services' => ServiceResource::collection($services),
            'meta' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
            ],
            'links' => [
                'first' => $services->url(1),
                'last' => $services->url($services->lastPage()),
                'prev' => $services->previousPageUrl(),
                'next' => $services->nextPageUrl(),
            ],
        ]);
    }
}
