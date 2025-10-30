<?php

namespace App\Http\Controllers\API\Admin;

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

    public function index(): JsonResponse
    {
        $services = Service::with('subServices.items.variants')->paginate(10);
        return ApiResponse::success([
            'services' => ServiceResource::collection($services),
            'meta' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:services',
                'description' => 'required|string',
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error($validator->errors(), 'Validation failed', 422);
            }
            $data = $request->only(['name', 'description']);
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('images/services', 'public');
                $data['image'] = $path;
            }

            $service = $this->serviceManagerService->createService($data);
            $service->load('subServices.items.variants');
            return ApiResponse::success([
                'service' => new ServiceResource($service),
            ], 'Service created successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:services,name,' . $service->id,
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

            $service = $this->serviceManagerService->updateService($service->id, $data);
            return ApiResponse::success([
                'service' => new ServiceResource($service),
            ], 'Service updated successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }
}
