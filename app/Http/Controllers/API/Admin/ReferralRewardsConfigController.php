<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApiResponse;
use App\Services\ReferralRewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralRewardsConfigController extends Controller
{
    public function __construct(protected ReferralRewardService $referralRewardService) {}

    public function show(): JsonResponse
    {
        $config = $this->referralRewardService->getConfig();

        if (!$config) {
            return ApiResponse::success([
                'config' => null,
            ]);
        }

        return ApiResponse::success([
            'config' => [
                'id' => $config->id,
                'referralsNeeded' => $config->referrals_needed,
                'isActive' => $config->is_active,
                'services' => $config->services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'subServiceId' => $service->sub_service_id,
                        'subService' => $service->subService ? [
                            'id' => $service->subService->id,
                            'name' => $service->subService->name,
                        ] : null,
                    ];
                }),
                'createdAt' => $config->created_at,
                'updatedAt' => $config->updated_at,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'referrals_needed' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => ['integer', 'exists:sub_services,id'],
        ]);

        $config = $this->referralRewardService->updateConfig($request->all());

        return ApiResponse::success([
            'config' => [
                'id' => $config->id,
                'referralsNeeded' => $config->referrals_needed,
                'isActive' => $config->is_active,
                'services' => $config->services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'subServiceId' => $service->sub_service_id,
                        'subService' => $service->subService ? [
                            'id' => $service->subService->id,
                            'name' => $service->subService->name,
                        ] : null,
                    ];
                }),
                'createdAt' => $config->created_at,
                'updatedAt' => $config->updated_at,
            ],
        ], 'Referral rewards configuration updated successfully');
    }
}
