<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDiscountSettingRequest;
use App\Http\Resources\DiscountSettingResource;
use App\Services\ApiResponse;
use App\Services\DiscountSettingService;
use Illuminate\Http\JsonResponse;

class DiscountSettingController extends Controller
{
    public function __construct(
        protected DiscountSettingService $discountSettingService
    ) {}

    public function show(): JsonResponse
    {
        $setting = $this->discountSettingService->get();

        return ApiResponse::success([
            'data' => new DiscountSettingResource($setting),
        ]);
    }

    public function update(UpdateDiscountSettingRequest $request): JsonResponse
    {
        $data = $request->all();
        $setting = $this->discountSettingService->update($data);

        return ApiResponse::success([
            'data' => new DiscountSettingResource($setting),
        ], __('success.discount_setting.updated'));
    }
}
