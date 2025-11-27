<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Requests\StoreSubServiceRequest;
use App\Http\Requests\UpdateSubServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\SubService;
use App\Services\ApiResponse;
use App\Services\SubServiceManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


class SubServicesController
{
    protected SubServiceManagerService $subServiceManagerService;

    public function __construct(SubServiceManagerService $subServiceManagerService)
    {
        $this->subServiceManagerService = $subServiceManagerService;
    }

    public function store(StoreSubServiceRequest $request): JsonResponse
    {
        try {

            $subService = $this->subServiceManagerService->createSubServiceWithItems(
                $request->only(['name', 'description', 'name_ar', 'description_ar', 'service_id', 'image']),
                $request->input('items')
            );

            $service = $subService->service;
            $service->load('subServices.items.variants');

            return ApiResponse::success([
                'service' => new ServiceResource($service),
            ], 'Subservice created successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }

    public function update(UpdateSubServiceRequest $request, SubService $subService): JsonResponse
    {
        try {

            $subService = $this->subServiceManagerService->updateSubServiceWithItems(
                $subService,
                $request->only(['name', 'description', 'name_ar', 'description_ar', 'service_id', 'image']),
                $request->input('items')
            );

            $subService->load('service.subServices.items.variants');

            return ApiResponse::success([
                'service' => new ServiceResource($subService->service),
            ], 'Subservice updated successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }
}
