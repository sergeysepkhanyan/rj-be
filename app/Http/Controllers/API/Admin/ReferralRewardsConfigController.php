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
            'config' => $this->serializeConfig($config),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'referrals_needed' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'services' => ['sometimes', 'array'],
            'services.*.id' => ['required_with:services', 'integer', 'min:1'],
            'services.*.type' => ['required_with:services', 'in:subservice,item'],
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => ['integer', 'exists:sub_services,id'],
        ]);

        $config = $this->referralRewardService->updateConfig($request->all());

        return ApiResponse::success([
            'config' => $this->serializeConfig($config),
        ], 'Referral rewards configuration updated successfully');
    }

    protected function serializeConfig(\App\Models\ReferralRewardsConfig $config): array
    {
        return [
            'id' => $config->id,
            'referralsNeeded' => $config->referrals_needed,
            'isActive' => $config->is_active,
            'services' => $config->services->map(function ($row) {
                if ($row->sub_service_item_id && $row->subServiceItem) {
                    return [
                        'id' => $row->subServiceItem->id,
                        'type' => 'item',
                        'name' => $row->subServiceItem->name,
                        'rowId' => $row->id,
                    ];
                }
                if ($row->sub_service_id && $row->subService) {
                    return [
                        'id' => $row->subService->id,
                        'type' => 'subservice',
                        'name' => $row->subService->name,
                        'rowId' => $row->id,
                    ];
                }
                return null;
            })->filter()->values(),
            'createdAt' => $config->created_at,
            'updatedAt' => $config->updated_at,
        ];
    }
}
