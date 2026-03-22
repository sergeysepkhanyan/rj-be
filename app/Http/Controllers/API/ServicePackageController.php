<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ServicePackage;
use App\Services\ApiResponse;
use App\Services\ServicePackageService;
use Illuminate\Http\JsonResponse;

class ServicePackageController extends Controller
{
    public function __construct(protected ServicePackageService $servicePackageService) {}

    /**
     * GET /service-packages — public listing of active packages with items.
     */
    public function index(): JsonResponse
    {
        $packages = $this->servicePackageService->getPublicPackages();

        $items = $packages->map(function (ServicePackage $pkg) {
            return [
                'id' => $pkg->id,
                'name' => $pkg->name,
                'nameAr' => $pkg->name_ar,
                'description' => $pkg->description,
                'descriptionAr' => $pkg->description_ar,
                'price' => (float) $pkg->price,
                'currency' => $pkg->currency,
                'validityDays' => $pkg->validity_days,
                'image' => $pkg->image ? asset('storage/' . $pkg->image) : null,
                'items' => $pkg->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'subService' => $item->subService ? [
                            'id' => $item->subService->id,
                            'name' => $item->subService->name,
                            'nameAr' => $item->subService->name_ar,
                        ] : null,
                        'totalVisits' => $item->total_visits,
                        'isUnlimited' => $item->isUnlimited(),
                        'dailyLimit' => $item->daily_limit,
                    ];
                }),
            ];
        });

        return ApiResponse::success(['servicePackages' => $items]);
    }

    /**
     * GET /service-packages/my — user's active purchases with progress.
     */
    public function myPackages(): JsonResponse
    {
        $userId = auth()->id();
        $packages = $this->servicePackageService->getUserActivePackages($userId);

        return ApiResponse::success(['purchases' => $packages]);
    }
}
