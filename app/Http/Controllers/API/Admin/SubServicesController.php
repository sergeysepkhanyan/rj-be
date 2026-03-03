<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Requests\StoreSubServiceRequest;
use App\Http\Requests\UpdateSubServiceRequest;
use App\Http\Resources\AdminServiceResource;
use App\Models\SubService;
use App\Services\ApiResponse;
use App\Services\SubServiceManagerService;
use Illuminate\Http\JsonResponse;

class SubServicesController
{
    public function __construct(
        protected SubServiceManagerService $subServiceManagerService
    ) {}

    public function store(StoreSubServiceRequest $request): JsonResponse
    {
        $subService = $this->subServiceManagerService->createSubServiceWithItems(
            $request->only([
                'name',
                'description',
                'name_ar',
                'description_ar',
                'service_id',
                'image',
                'type',
                'price',
                'currency',
                'duration',
                'duration_unit',
                'show_duration',
                'vat_enabled',
            ]),
            $request->input('items')
        );

        $service = $subService->service;
        $service->load('subServices.items');

        return ApiResponse::success([
            'service' => new AdminServiceResource($service),
        ], __('success.subservice.created'));
    }

    public function update(UpdateSubServiceRequest $request, SubService $subService): JsonResponse
    {
        $subService = $this->subServiceManagerService->updateSubServiceWithItems(
            $subService,
            $request->only([
                'name',
                'description',
                'name_ar',
                'description_ar',
                'image',
                'type',
                'price',
                'currency',
                'duration',
                'duration_unit',
                'show_duration',
                'vat_enabled',
            ]),
            $request->input('items')
        );

        $subService->load('service.subServices.items');

        return ApiResponse::success([
            'service' => new AdminServiceResource($subService->service),
        ], __('success.subservice.updated'));
    }

    public function destroy(SubService $subService): JsonResponse
    {
        $this->subServiceManagerService->deleteSubService($subService);

        return ApiResponse::success([
            'deleted' => true,
            'sub_service_id' => $subService->id,
        ], __('success.subservice.deleted'));
    }
}
