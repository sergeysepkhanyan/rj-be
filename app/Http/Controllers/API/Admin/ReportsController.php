<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApiResponse;
use App\Services\ReportsService;
use Illuminate\Http\JsonResponse;

class ReportsController extends Controller
{
    public function __construct(
        protected ReportsService $reportsService
    ) {}

    public function todaysTurnover(): JsonResponse
    {
        $totals = $this->reportsService->todaysTurnover();

        return ApiResponse::success([
            'totals' => $totals,
        ]);
    }

    public function topServices(): JsonResponse
    {
        $grouped = $this->reportsService->topServices();

        return ApiResponse::success([
            'services' => $grouped,
        ]);
    }

    public function topProducts(): JsonResponse
    {
        $grouped = $this->reportsService->topProducts();

        return ApiResponse::success([
            'products' => $grouped,
        ]);
    }
}
