<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\ApiResponse;
use App\Services\ServicePackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServicePackageController extends Controller
{
    public function __construct(protected ServicePackageService $servicePackageService) {}

    /**
     * GET /admin/service-packages — list all packages with purchase counts.
     */
    public function index(): JsonResponse
    {
        $packages = ServicePackage::orderBy('sort_order')
            ->orderBy('price')
            ->withCount('purchases')
            ->with('items.subService')
            ->get();

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
                'status' => $pkg->status,
                'sortOrder' => $pkg->sort_order,
                'purchasesCount' => $pkg->purchases_count,
                'items' => $pkg->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'subServiceId' => $item->sub_service_id,
                        'subServiceName' => $item->subService?->name,
                        'subServiceNameAr' => $item->subService?->name_ar,
                        'totalVisits' => $item->total_visits,
                        'isUnlimited' => $item->isUnlimited(),
                        'dailyLimit' => $item->daily_limit,
                    ];
                }),
                'createdAt' => $pkg->created_at,
            ];
        });

        return ApiResponse::success(['servicePackages' => $items]);
    }

    /**
     * POST /admin/service-packages — create package with items array.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'nameAr' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'descriptionAr' => 'nullable|string',
            'price' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'validityDays' => 'required|integer|min:1',
            'image' => 'nullable|string',
            'status' => 'nullable|in:active,draft',
            'sortOrder' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.subServiceId' => 'required|integer|exists:sub_services,id',
            'items.*.totalVisits' => 'required|integer|min:0',
            'items.*.dailyLimit' => 'nullable|integer|min:1',
        ]);

        $package = DB::transaction(function () use ($request) {
            $image = $request->image;
            if ($image && str_contains($image, '/storage/')) {
                $image = preg_replace('#^https?://[^/]+/storage/#', '', $image);
            }

            $package = ServicePackage::create([
                'name' => $request->name,
                'name_ar' => $request->nameAr,
                'description' => $request->description,
                'description_ar' => $request->descriptionAr,
                'price' => $request->price,
                'currency' => $request->currency ?? 'AED',
                'validity_days' => $request->validityDays,
                'image' => $image,
                'status' => $request->status ?? 'draft',
                'sort_order' => $request->sortOrder ?? 0,
            ]);

            foreach ($request->items as $itemData) {
                $package->items()->create([
                    'sub_service_id' => $itemData['subServiceId'],
                    'total_visits' => $itemData['totalVisits'],
                    'daily_limit' => $itemData['dailyLimit'] ?? 1,
                ]);
            }

            return $package->load('items.subService');
        });

        return ApiResponse::success([
            'servicePackage' => $this->formatPackage($package),
        ], 'Service package created successfully', 201);
    }

    /**
     * PUT /admin/service-packages/{id} — update package and sync items.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $package = ServicePackage::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'nameAr' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'descriptionAr' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'validityDays' => 'sometimes|required|integer|min:1',
            'image' => 'nullable|string',
            'status' => 'nullable|in:active,draft',
            'sortOrder' => 'nullable|integer',
            'items' => 'sometimes|required|array|min:1',
            'items.*.subServiceId' => 'required|integer|exists:sub_services,id',
            'items.*.totalVisits' => 'required|integer|min:0',
            'items.*.dailyLimit' => 'nullable|integer|min:1',
        ]);

        DB::transaction(function () use ($request, $package) {
            // Strip full URL prefix from image path if present (frontend may send full URL on edit)
            $image = $request->image;
            if ($image && str_contains($image, '/storage/')) {
                $image = preg_replace('#^https?://[^/]+/storage/#', '', $image);
            }

            $package->update(array_filter([
                'name' => $request->name,
                'name_ar' => $request->nameAr,
                'description' => $request->description,
                'description_ar' => $request->descriptionAr,
                'price' => $request->price,
                'currency' => $request->currency,
                'validity_days' => $request->validityDays,
                'image' => $image,
                'status' => $request->status,
                'sort_order' => $request->sortOrder,
            ], fn($v) => $v !== null));

            if ($request->has('items')) {
                $existingItems = $package->items()->get()->keyBy('sub_service_id');
                $incomingSubServiceIds = [];

                foreach ($request->items as $itemData) {
                    $subServiceId = $itemData['subServiceId'];
                    $incomingSubServiceIds[] = $subServiceId;

                    if ($existingItems->has($subServiceId)) {
                        // Update existing item
                        $existingItems[$subServiceId]->update([
                            'total_visits' => $itemData['totalVisits'],
                            'daily_limit' => $itemData['dailyLimit'] ?? 1,
                        ]);
                    } else {
                        // Create new item
                        $package->items()->create([
                            'sub_service_id' => $subServiceId,
                            'total_visits' => $itemData['totalVisits'],
                            'daily_limit' => $itemData['dailyLimit'] ?? 1,
                        ]);
                    }
                }

                // Remove items no longer in the package (only if no usages reference them)
                $removedIds = $existingItems->keys()->diff($incomingSubServiceIds);
                if ($removedIds->isNotEmpty()) {
                    $package->items()
                        ->whereIn('sub_service_id', $removedIds)
                        ->whereDoesntHave('usages')
                        ->delete();
                }
            }
        });

        return ApiResponse::success([
            'servicePackage' => $this->formatPackage($package->fresh()->load('items.subService')),
        ], 'Service package updated successfully');
    }

    /**
     * DELETE /admin/service-packages/{id} — soft delete.
     */
    public function destroy($id): JsonResponse
    {
        $package = ServicePackage::findOrFail($id);
        $package->delete();

        return ApiResponse::success([], 'Service package deleted successfully');
    }

    /**
     * GET /admin/clients/{user}/packages — client's purchases with usage.
     */
    public function clientPackages(User $user): JsonResponse
    {
        $purchases = $this->servicePackageService->getClientPackages($user->id);

        $items = $purchases->map(function ($purchase) {
            return [
                'id' => $purchase->id,
                'code' => $purchase->code,
                'status' => $purchase->status,
                'purchasedAt' => $purchase->purchased_at?->toIso8601String(),
                'expiresAt' => $purchase->expires_at?->toIso8601String(),
                'isExpired' => $purchase->isExpired(),
                'package' => $purchase->servicePackage ? [
                    'id' => $purchase->servicePackage->id,
                    'name' => $purchase->servicePackage->name,
                    'nameAr' => $purchase->servicePackage->name_ar,
                    'price' => (float) $purchase->servicePackage->price,
                    'image' => $purchase->servicePackage->image ? asset('storage/' . $purchase->servicePackage->image) : null,
                ] : null,
                'order' => $purchase->order ? [
                    'id' => $purchase->order->id,
                    'reference' => $purchase->order->reference,
                ] : null,
                'items' => $purchase->servicePackage?->items->map(function ($item) use ($purchase) {
                    $usedCount = $purchase->usages
                        ->where('service_package_item_id', $item->id)
                        ->count();

                    return [
                        'id' => $item->id,
                        'subServiceName' => $item->subService?->name,
                        'subServiceNameAr' => $item->subService?->name_ar,
                        'totalVisits' => $item->total_visits,
                        'isUnlimited' => $item->isUnlimited(),
                        'dailyLimit' => $item->daily_limit,
                        'usedCount' => $usedCount,
                        'remainingVisits' => $item->isUnlimited() ? -1 : max(0, $item->total_visits - $usedCount),
                    ];
                }),
                'usages' => $purchase->usages->map(function ($usage) {
                    return [
                        'id' => $usage->id,
                        'itemName' => $usage->item?->subService?->name,
                        'bookingId' => $usage->booking_id,
                        'bookingReference' => $usage->booking?->reference,
                        'usedAt' => $usage->used_at?->toIso8601String(),
                        'notes' => $usage->notes,
                    ];
                }),
            ];
        });

        return ApiResponse::success(['purchases' => $items]);
    }

    private function formatPackage(ServicePackage $pkg): array
    {
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
            'status' => $pkg->status,
            'sortOrder' => $pkg->sort_order,
            'items' => $pkg->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'subServiceId' => $item->sub_service_id,
                    'subServiceName' => $item->subService?->name,
                    'subServiceNameAr' => $item->subService?->name_ar,
                    'totalVisits' => $item->total_visits,
                    'isUnlimited' => $item->isUnlimited(),
                    'dailyLimit' => $item->daily_limit,
                ];
            }),
            'createdAt' => $pkg->created_at,
        ];
    }
}
