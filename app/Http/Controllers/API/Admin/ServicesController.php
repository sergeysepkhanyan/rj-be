<?php

namespace App\Http\Controllers\API\Admin;

use App\Filters\ServiceFilter;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\AdminServiceResource;
use App\Models\Service;
use App\Services\ApiResponse;
use App\Services\ServiceManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class ServicesController
{
    protected ServiceManagerService $serviceManagerService;

    public function __construct(ServiceManagerService $serviceManagerService)
    {
        $this->serviceManagerService = $serviceManagerService;
    }

    public function index(Request $request, ServiceFilter $filter): JsonResponse
    {
        $perPage = $request->query('per_page', 10);

        $services = $this->serviceManagerService->getPaginatedServices($filter, $perPage);

        return ApiResponse::success([
            'services' => AdminServiceResource::collection($services),
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
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $data = $request->all();
        $data = array_intersect_key($data, array_flip((new Service)->getFillable()));
        $service = $this->serviceManagerService->createService($data);
        $service->load('subServices.items');

        return ApiResponse::success([
            'service' => new AdminServiceResource($service),
        ], 'Service created successfully.');
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $data = $request->all();
        $data = array_intersect_key($data, array_flip((new Service)->getFillable()));
        $service = $this->serviceManagerService->updateService($service, $data);
        return ApiResponse::success([
            'service' => new AdminServiceResource($service),
        ], 'Service updated successfully.');
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->serviceManagerService->deleteService($service);

        return ApiResponse::success([
            'deleted' => true,
            'service_id' => $service->id,
        ], 'Service deleted successfully.');
    }
}
