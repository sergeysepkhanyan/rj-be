<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\SubService;
use App\Services\ApiResponse;
use App\Services\SubServiceManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class SubServicesController
{
    protected SubServiceManagerService $subServiceManagerService;

    public function __construct(SubServiceManagerService $subServiceManagerService)
    {
        $this->subServiceManagerService = $subServiceManagerService;
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'service_id' => 'required|integer|exists:services,id',
                'name' => 'required|string|max:255|unique:services',
                'description' => 'required|string',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error($validator->errors(), 'Validation failed', 422);
            }
            $data = $request->only(['name', 'description', 'service_id']);
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('images/sub-services', 'public');
                $data['image'] = $path;
            }

            $subService = $this->subServiceManagerService->createSubService($data);
            $service = $subService->service;
            $service->load('subServices.items.variants');
            return ApiResponse::success([
                'service' => new ServiceResource($service),
            ], 'Subservice created successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }

    }

    public function update(Request $request, SubService $subService): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:services,name,' . $subService->id,
                'description' => 'required|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error($validator->errors(), 'Validation failed', 422);
            }
            $data = $request->only(['name', 'description']);
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('images/services', 'public');
                $data['image'] = $path;
            }

            $subService = $this->subServiceManagerService->updateSubService($subService->id,$data);
            $service = $subService->service;
            $service->load('subServices.items.variants');
            return ApiResponse::success([
                'service' => new ServiceResource($service),
            ], 'Subservice updated successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }
}
