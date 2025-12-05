<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Requests\StoreSubServiceRequest;
use App\Http\Requests\UpdateSubServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\SubService;
use App\Models\SubServiceItem;
use App\Services\ApiResponse;
use App\Services\SubServiceItemManagerService;
use App\Services\SubServiceManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


class SubServiceItemsController
{
    protected SubServiceItemManagerService $subServiceItemManagerService;

    public function __construct(SubServiceItemManagerService $subServiceItemManagerService)
    {
        $this->subServiceItemManagerService = $subServiceItemManagerService;
    }

    public function destroy(SubServiceItem $subServiceItem): JsonResponse
    {
        try {

            $this->subServiceItemManagerService->deleteSubServiceItem($subServiceItem->id);

            return ApiResponse::success([], 'Subservice item deleted successfully');

        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }
}
