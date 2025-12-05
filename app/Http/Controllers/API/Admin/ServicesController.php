<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
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

    public function store(StoreServiceRequest $request): JsonResponse
    {
        try {
            $data = $request->all();
            $data = array_intersect_key($data, array_flip((new Service)->getFillable()));
            $service = $this->serviceManagerService->createService($data);
            $service->load('subServices.items');

            return ApiResponse::success([
                'service' => new ServiceResource($service),
            ], 'Service created successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        try {
            $data = $request->validated();

            $service = $this->serviceManagerService->updateService($service->id, $data);

            return ApiResponse::success([
                'service' => new ServiceResource($service),
            ], 'Service updated successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }
}
