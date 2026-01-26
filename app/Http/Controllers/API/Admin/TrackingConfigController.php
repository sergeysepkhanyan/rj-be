<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTrackingConfigRequest;
use App\Http\Resources\TrackingConfigResource;
use App\Services\ApiResponse;
use App\Services\TrackingConfigService;
use Illuminate\Http\JsonResponse;

class TrackingConfigController extends Controller
{
    public function __construct(
        protected TrackingConfigService $trackingConfigService
    ) {}

    public function index(): JsonResponse
    {
        $config = $this->trackingConfigService->get();

        return ApiResponse::success(
            (new TrackingConfigResource($config))->resolve()
        );
    }

    public function update(UpdateTrackingConfigRequest $request): JsonResponse
    {
        $data = $request->all();

        $config = $this->trackingConfigService->update($data);

        return ApiResponse::success(
            (new TrackingConfigResource($config))->resolve(),
            __('success.tracking_config.updated')
        );
    }

    public function public(): JsonResponse
    {
        $data = $this->trackingConfigService->getPublic();

        return ApiResponse::success($data);
    }
}
