<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\SubServiceItem;
use App\Services\ApiResponse;
use App\Services\SubServiceItemManagerService;
use Illuminate\Http\JsonResponse;

class SubServiceItemsController
{
    public function __construct(protected SubServiceItemManagerService $subServiceItemManagerService) {}

    public function destroy(SubServiceItem $subServiceItem): JsonResponse
    {
        $this->subServiceItemManagerService->deleteSubServiceItem($subServiceItem->id);

        return ApiResponse::success([], __('success.subservice_item.deleted'));
    }
}
