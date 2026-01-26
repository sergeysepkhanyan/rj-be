<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateReferralRequest;
use App\Http\Resources\ReferralResource;
use App\Services\ApiResponse;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralsController extends Controller
{
    public function __construct(protected ReferralService $referralService) {}

    public function index(Request $request): JsonResponse
    {
        $referrals = $this->referralService->getAll();

        return ApiResponse::success(
            ReferralResource::collection($referrals)
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
}
