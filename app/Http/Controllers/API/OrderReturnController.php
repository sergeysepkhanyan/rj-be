<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ApiResponse;
use App\Services\OrderReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderReturnController extends Controller
{
    public function __construct(
        protected OrderReturnService $orderReturnService
    ) {}

    /**
     * Request a return for an order.
     * POST /orders/{order}/return
     */
    public function store(Request $request, Order $order): JsonResponse
    {
        // Ensure the authenticated user owns this order
        if ($order->user_id !== auth()->id()) {
            return ApiResponse::error(
                ['order' => ['Order not found.']],
                'Not found',
                404
            );
        }

        $request->validate([
            'reason' => 'required|string|max:2000',
            'pickupAddressId' => 'required|integer|exists:addresses,id',
        ]);

        $check = $this->orderReturnService->canRequestReturn($order);
        if (!$check['can']) {
            return ApiResponse::error(
                ['return' => [$check['reason']]],
                $check['reason'],
                422
            );
        }

        $orderReturn = $this->orderReturnService->requestReturn(
            $order,
            $request->input('reason'),
            $request->input('pickupAddressId')
        );

        return ApiResponse::success([
            'returnRequest' => [
                'id' => $orderReturn->id,
                'status' => $orderReturn->status,
                'reason' => $orderReturn->reason,
                'createdAt' => $orderReturn->created_at,
            ],
        ], 'Return request submitted successfully.');
    }

    /**
     * Get return status for an order.
     * GET /orders/{order}/return
     */
    public function show(Order $order): JsonResponse
    {
        // Ensure the authenticated user owns this order
        if ($order->user_id !== auth()->id()) {
            return ApiResponse::error(
                ['order' => ['Order not found.']],
                'Not found',
                404
            );
        }

        $orderReturn = $order->orderReturn;

        if (!$orderReturn) {
            return ApiResponse::success([
                'returnRequest' => null,
                'canRequestReturn' => $this->orderReturnService->canRequestReturn($order),
            ]);
        }

        return ApiResponse::success([
            'returnRequest' => [
                'id' => $orderReturn->id,
                'status' => $orderReturn->status,
                'reason' => $orderReturn->reason,
                'createdAt' => $orderReturn->created_at,
                'adminNotes' => $orderReturn->admin_notes,
                'approvedAt' => $orderReturn->approved_at,
                'rejectedAt' => $orderReturn->rejected_at,
            ],
        ]);
    }

    /**
     * Get return policy settings (public).
     * GET /return-policy
     */
    public function policy(): JsonResponse
    {
        $policy = $this->orderReturnService->getReturnPolicy();

        return ApiResponse::success([
            'returnPolicy' => [
                'returnWindowDays' => $policy->return_window_days,
                'isActive' => $policy->is_active,
            ],
        ]);
    }
}
