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

    public function actionQueueCounts(): JsonResponse
    {
        $pendingDispatch = \App\Models\Order::query()
            ->where('type', 'ecommerce')
            ->where('status', 'paid')
            ->whereNotIn('delivery_status', ['delivered', 'canceled'])
            ->count();

        $pendingRefunds = \App\Models\Order::query()
            ->whereIn('status', ['return_requested'])
            ->count();

        $returnsToReview = \App\Models\OrderReturn::query()
            ->where('status', 'pending')
            ->count();

        $unpaidPastBookings = \App\Models\Booking::query()
            ->where('payment_status', 'unpaid')
            ->where('status', 'confirmed')
            ->whereDate('date', '<', now()->toDateString())
            ->count();

        return ApiResponse::success([
            'counts' => [
                'pendingDispatch' => $pendingDispatch,
                'pendingRefunds' => $pendingRefunds,
                'returnsToReview' => $returnsToReview,
                'unpaidPastBookings' => $unpaidPastBookings,
                'total' => $pendingDispatch + $pendingRefunds + $returnsToReview + $unpaidPastBookings,
            ],
        ]);
    }
}
