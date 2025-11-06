<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Resources\ReferralResource;
use App\Services\ApiResponse;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class ReferralsController
{
    protected ReferralService $referralService;

    public function __construct(ReferralService $referralService)
    {
        $this->referralService = $referralService;
    }

    public function index(Request $request): JsonResponse
    {
        $services = $this->referralService->getAll();

        return ApiResponse::success([
            'referrals' => ReferralResource::collection($services)
        ]);
    }
}
