<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReferralRequest;
use App\Http\Requests\UpdateReferralRequest;
use App\Http\Resources\ReferralResource;
use App\Services\ApiResponse;
use App\Services\LoyaltyService;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralsController extends Controller
{
    public function __construct(
        protected ReferralService $referralService,
        protected LoyaltyService $loyaltyService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $referrals = $this->referralService->getAll();

        return ApiResponse::success(
            ReferralResource::collection($referrals)
        );
    }

    public function store(StoreReferralRequest $request): JsonResponse
    {
        $data = $request->all();

        // Default type to 'percentage' if not provided
        if (! isset($data['type'])) {
            $data['type'] = 'percentage';
        }

        $referral = $this->referralService->create($data);

        return ApiResponse::success(
            (new ReferralResource($referral))->resolve(),
            __('success.referral.created')
        );
    }

    public function update(UpdateReferralRequest $request, int $id): JsonResponse
    {
        $data = $request->all();
        $referral = $this->referralService->update($id, $data);

        return ApiResponse::success(
            (new ReferralResource($referral))->resolve(),
            __('success.referral.updated')
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->referralService->delete($id);

        return ApiResponse::success(
            null,
            __('success.referral.deleted')
        );
    }

    /**
     * Public endpoint: returns active loyalty tiers for client dashboard display.
     */
    public function publicTiers(): JsonResponse
    {
        $tiers = $this->loyaltyService->getActiveTiersForClient();

        return ApiResponse::success(
            ReferralResource::collection($tiers)
        );
    }
}
