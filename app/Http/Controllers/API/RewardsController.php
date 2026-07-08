<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ComplimentaryReward;
use App\Services\ApiResponse;
use App\Services\ReferralRewardService;
use Illuminate\Http\JsonResponse;

class RewardsController extends Controller
{
    public function __construct(protected ReferralRewardService $referralRewardService) {}

    public function index(): JsonResponse
    {
        $user = auth()->user();
        $rewards = $this->referralRewardService->getUserRewards($user->id);

        return ApiResponse::success([
            'rewards' => $rewards->map(function ($reward) {
                $svc = $reward->subService ?: $reward->subServiceItem;
                return [
                    'id' => $reward->id,
                    'subServiceId' => $reward->sub_service_id,
                    'subServiceItemId' => $reward->sub_service_item_id,
                    'subService' => $svc ? [
                        'id' => $svc->id,
                        'name' => $svc->name,
                        'image' => $svc->image ? asset('storage/' . $svc->image) : null,
                    ] : null,
                    'status' => $reward->status,
                    'earnedAt' => $reward->earned_at,
                    'expiresAt' => $reward->expires_at,
                ];
            }),
        ]);
    }

    public function redeem(ComplimentaryReward $reward): JsonResponse
    {
        $user = auth()->user();

        if ((int) $reward->user_id !== (int) $user->id) {
            return ApiResponse::error(null, 'This reward does not belong to you', 403);
        }

        if ($reward->status !== 'available') {
            return ApiResponse::error(null, 'This reward is not available for redemption', 422);
        }

        // Note: In a full implementation, the reward would be redeemed when linked to a booking.
        // For now, we return the reward details for the client to use during booking.
        $svc = $reward->subService ?: $reward->subServiceItem;

        return ApiResponse::success([
            'reward' => [
                'id' => $reward->id,
                'subServiceId' => $reward->sub_service_id,
                'subServiceItemId' => $reward->sub_service_item_id,
                'subService' => $svc ? [
                    'id' => $svc->id,
                    'name' => $svc->name,
                ] : null,
                'status' => $reward->status,
                'earnedAt' => $reward->earned_at,
            ],
        ]);
    }
}
