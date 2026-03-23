<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductDiscountTierRequest;
use App\Http\Requests\UpdateProductDiscountTierRequest;
use App\Http\Resources\ProductDiscountTierResource;
use App\Services\ApiResponse;
use App\Services\ProductDiscountTierService;
use Illuminate\Http\JsonResponse;

class ProductDiscountTiersController extends Controller
{
    public function __construct(
        protected ProductDiscountTierService $service,
    ) {}

    public function index(): JsonResponse
    {
        $tiers = $this->service->getAll();

        return ApiResponse::success(
            ProductDiscountTierResource::collection($tiers)
        );
    }

    public function store(StoreProductDiscountTierRequest $request): JsonResponse
    {
        $tier = $this->service->create($request->all());

        return ApiResponse::success(
            (new ProductDiscountTierResource($tier))->resolve(),
            'Product discount tier created'
        );
    }

    public function update(UpdateProductDiscountTierRequest $request, int $id): JsonResponse
    {
        $tier = $this->service->update($id, $request->all());

        return ApiResponse::success(
            (new ProductDiscountTierResource($tier))->resolve(),
            'Product discount tier updated'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return ApiResponse::success(null, 'Product discount tier deleted');
    }

    public function publicTiers(): JsonResponse
    {
        $tiers = $this->service->getActiveTiers();

        return ApiResponse::success(
            ProductDiscountTierResource::collection($tiers)
        );
    }

    public function userDiscountInfo(): JsonResponse
    {
        $user = auth()->user();
        $user->load('productDiscountTier');

        $totalSpend = $this->service->getUserTotalProductSpend($user);
        $discountPercentage = $this->service->getDiscountForUser($user);
        $tiers = $this->service->getActiveTiers();

        return ApiResponse::success([
            'currentTier' => $user->productDiscountTier
                ? (new ProductDiscountTierResource($user->productDiscountTier))->resolve()
                : null,
            'totalSpend' => round($totalSpend, 2),
            'discountPercentage' => $discountPercentage,
            'tiers' => ProductDiscountTierResource::collection($tiers),
        ]);
    }
}
