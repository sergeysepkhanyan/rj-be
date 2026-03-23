<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ApiResponse;
use App\Services\GoogleReviewsService;
use Illuminate\Http\JsonResponse;

class GoogleReviewsController extends Controller
{
    public function __construct(private readonly GoogleReviewsService $service) {}

    /**
     * GET /google-reviews — public, cached Google reviews for the salon.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success($this->service->getReviews());
    }

    /**
     * POST /admin/google-reviews/refresh — admin-only, clears cache and returns fresh data.
     */
    public function refresh(): JsonResponse
    {
        $this->service->clearCache();

        return ApiResponse::success(
            $this->service->getReviews(),
            'Reviews cache refreshed'
        );
    }
}
