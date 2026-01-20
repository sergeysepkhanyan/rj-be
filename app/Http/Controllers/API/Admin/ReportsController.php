<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApiResponse;
use App\Services\ReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function __construct(
        protected ReportsService $reportsService
    ) {}

    public function todaysTurnover(Request $request): JsonResponse
    {
        $date = $request->input('date');
        $totals = $this->reportsService->todaysTurnover($date);

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
