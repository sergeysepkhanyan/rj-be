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
use Illuminate\Validation\Rule;


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
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('sub_services')->where(function ($query) use ($request) {
                        return $query->where('service_id', $request->input('service_id'));
                    }),
                ],
                'description' => 'required|string',
                'image' => 'required|string',
                'items' => 'required|array',
                'items.*.name' => 'required|string|max:255',
                'items.*.type' => 'required|string|in:Simple,Variant Based',
                'items.*.price' => 'required_if:items.*.type,Simple|nullable|numeric',
                'items.*.duration' => 'required_if:items.*.type,Simple|nullable|numeric',
                'items.*.currency' => 'required_if:items.*.type,Simple|nullable|string',
                'items.*.duration_unit' => 'required_if:items.*.type,Simple|nullable|string',
                'items.*.variants' => 'required_if:items.*.type,Variant Based|array',
                'items.*.variants.*.name' => 'required_if:items.*.type,Variant Based|string|max:255',
                'items.*.variants.*.price' => 'required_if:items.*.type,Variant Based|numeric',
                'items.*.variants.*.duration' => 'required_if:items.*.type,Variant Based|numeric',
                'items.*.variants.*.currency' => 'required_if:items.*.type,Variant Based|string',
                'items.*.variants.*.duration_unit' => 'required_if:items.*.type,Variant Based|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error($validator->errors(), 'Validation failed', 422);
            }
            $subService = $this->subServiceManagerService->createSubServiceWithItems(
                $request->only(['name', 'description', 'service_id', 'image']),
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

    public function update(Request $request, SubService $subService): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('sub_services')->ignore($subService->id)->where(function ($query) use ($request) {
                        return $query->where('service_id', $request->input('service_id'));
                    }),
                ],
                'description' => 'required|string',
                'image' => 'nullable|string',
                'items' => 'required|array',
                'items.*.id' => 'sometimes|nullable|integer|exists:sub_service_items,id',
                'items.*.name' => 'required|string|max:255',
                'items.*.type' => 'required|string|in:Simple,Variant Based',
                'items.*.price' => 'required_if:items.*.type,Simple|nullable|numeric',
                'items.*.duration' => 'required_if:items.*.type,Simple|nullable|numeric',
                'items.*.currency' => 'required_if:items.*.type,Simple|nullable|string',
                'items.*.duration_unit' => 'required_if:items.*.type,Simple|nullable|string',
                'items.*.variants' => 'required_if:items.*.type,Variant Based|array',
                'items.*.variants.*.name' => 'required_if:items.*.type,Variant Based|string|max:255',
                'items.*.variants.*.price' => 'required_if:items.*.type,Variant Based|numeric',
                'items.*.variants.*.duration' => 'required_if:items.*.type,Variant Based|numeric',
                'items.*.variants.*.currency' => 'required_if:items.*.type,Variant Based|string',
                'items.*.variants.*.duration_unit' => 'required_if:items.*.type,Variant Based|string',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error($validator->errors(), 'Validation failed', 422);
            }

            $subService = $this->subServiceManagerService->updateSubServiceWithItems(
                $subService,
                $request->only(['name', 'description', 'service_id', 'image']),
                $request->input('items')
            );

            $subService->load('service.subServices.items.variants');

            return ApiResponse::success([
                'service' => new ServiceResource($subService->service),
            ], 'Subservice updated successfully.');

        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }}
