<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Requests\StoreSubServiceRequest;
use App\Http\Requests\UpdateSubServiceRequest;
use App\Http\Resources\AdminServiceResource;
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
        $subService = $this->subServiceManagerService->createSubServiceWithItems(
            $request->only([
                'name', 'description', 'name_ar', 'description_ar', 'service_id', 'image', 'type', 'price','currency', 'duration', 'duration_unit'
            ]),
            $request->input('items')
        );

        $service = $subService->service;
        $service->load('subServices.items');

        return ApiResponse::success([
            'service' => new AdminServiceResource($service),
        ], 'Subservice created successfully.');
    }

    public function update(UpdateSubServiceRequest $request, SubService $subService): JsonResponse
    {
        $subService = $this->subServiceManagerService->updateSubServiceWithItems(
            $subService,
            $request->only([
                'name', 'description', 'name_ar', 'description_ar', 'image', 'type', 'price','currency', 'duration', 'duration_unit'
            ]),
            $request->input('items')
        );

        $subService->load('service.subServices.items');

        return ApiResponse::success([
            'service' => new AdminServiceResource($subService->service),
        ], 'Subservice updated successfully.');
    }

    public function destroy(SubService $subService): JsonResponse
    {
        $this->subServiceManagerService->deleteSubService($subService);
        return ApiResponse::success([
            'deleted' => true,
            'sub_service_id' => $subService->id,
        ], 'Subservice deleted successfully.');
    }
}
