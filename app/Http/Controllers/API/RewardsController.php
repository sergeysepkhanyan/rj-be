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
                return [
                    'id' => $reward->id,
                    'subServiceId' => $reward->sub_service_id,
                    'subService' => $reward->subService ? [
                        'id' => $reward->subService->id,
                        'name' => $reward->subService->name,
                        'image' => $reward->subService->image ? asset('storage/' . $reward->subService->image) : null,
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
        return ApiResponse::success([
            'reward' => [
                'id' => $reward->id,
                'subServiceId' => $reward->sub_service_id,
                'subService' => $reward->subService ? [
                    'id' => $reward->subService->id,
                    'name' => $reward->subService->name,
                ] : null,
                'status' => $reward->status,
                'earnedAt' => $reward->earned_at,
            ],
        ]);
    }
}
