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
use App\Models\SubService;
use App\Models\SubServiceItem;

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
        ], __('success.service.listed'));
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $data = array_intersect_key(
            $request->all(),
            array_flip((new Service)->getFillable())
        );

        $service = $this->serviceManagerService->createService($data);

        // Handle additional images
        if ($request->has('images')) {
            $this->serviceManagerService->syncServiceImages($service, $request->input('images'));
        }

        $service->load('subServices.items', 'category', 'files');

        return ApiResponse::success([
            'service' => new AdminServiceResource($service),
        ], __('success.service.created'));
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $data = array_intersect_key(
            $request->all(),
            array_flip((new Service)->getFillable())
        );

        $service = $this->serviceManagerService->updateService($service, $data);

        // Handle additional images
        if ($request->has('images')) {
            $this->serviceManagerService->syncServiceImages($service, $request->input('images'));
        }

        $service->load('subServices.items', 'category', 'files');

        return ApiResponse::success([
            'service' => new AdminServiceResource($service),
        ], __('success.service.updated'));
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->serviceManagerService->deleteService($service);

        return ApiResponse::success([
            'deleted' => true,
            'service_id' => $service->id,
        ], __('success.service.deleted'));
    }

    public function bulkDiscount(Request $request): JsonResponse
    {
        $request->validate([
            'level' => 'required|in:category,service,subservice,item',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'discount' => 'required|boolean',
            'discountType' => 'required_if:discount,true|nullable|in:percentage,fixed',
            'discountAmount' => 'required_if:discount,true|nullable|numeric|min:0',
        ]);

        $level = $request->level;
        $ids = $request->ids;
        $discountData = [
            'discount' => $request->discount,
            'discount_type' => $request->discount ? $request->discountType : null,
            'discount_amount' => $request->discount ? $request->discountAmount : null,
        ];

        $updated = 0;

        if ($level === 'category') {
            $serviceIds = Service::whereIn('category_id', $ids)->pluck('id');
            $subServiceIds = SubService::whereIn('service_id', $serviceIds)->pluck('id');
            $updated += SubService::whereIn('service_id', $serviceIds)->update($discountData);
            $updated += SubServiceItem::whereIn('sub_service_id', $subServiceIds)->update($discountData);
        } elseif ($level === 'service') {
            $subServiceIds = SubService::whereIn('service_id', $ids)->pluck('id');
            $updated += SubService::whereIn('service_id', $ids)->update($discountData);
            $updated += SubServiceItem::whereIn('sub_service_id', $subServiceIds)->update($discountData);
        } elseif ($level === 'subservice') {
            $updated += SubService::whereIn('id', $ids)->update($discountData);
            $updated += SubServiceItem::whereIn('sub_service_id', $ids)->update($discountData);
        } elseif ($level === 'item') {
            $updated += SubServiceItem::whereIn('id', $ids)->update($discountData);
        }

        return ApiResponse::success([
            'updated' => $updated,
        ], "Discount applied to {$updated} items");
    }
}
