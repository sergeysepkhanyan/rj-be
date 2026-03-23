<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderReturn;
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
     * List all return requests (paginated, filterable by status).
     * GET /admin/order-returns
     */
    public function index(Request $request): JsonResponse
    {
        $query = OrderReturn::with(['order.user', 'order.items.product', 'approvedByUser', 'rejectedByUser'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status') && $request->input('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $returns = $query->paginate($perPage);

        $data = $returns->getCollection()->map(function (OrderReturn $return) {
            return [
                'id' => $return->id,
                'orderId' => $return->order_id,
                'orderReference' => $return->order?->reference,
                'customerName' => $this->resolveCustomerName($return),
                'customerEmail' => $this->resolveCustomerEmail($return),
                'reason' => $return->reason,
                'status' => $return->status,
                'adminNotes' => $return->admin_notes,
                'pickupAddressSnapshot' => $return->pickup_address_snapshot,
                'approvedBy' => $return->approvedByUser ? $return->approvedByUser->name : null,
                'approvedAt' => $return->approved_at,
                'rejectedBy' => $return->rejectedByUser ? $return->rejectedByUser->name : null,
                'rejectedAt' => $return->rejected_at,
                'createdAt' => $return->created_at,
                'orderAmount' => $return->order ? (string) $return->order->amount : null,
                'orderCurrency' => $return->order?->currency ?? 'AED',
            ];
        });

        return ApiResponse::success([
            'returns' => $data,
            'meta' => [
                'current_page' => $returns->currentPage(),
                'last_page' => $returns->lastPage(),
                'per_page' => $returns->perPage(),
                'total' => $returns->total(),
            ],
        ], 'Return requests listed.');
    }

    /**
     * Approve a return request.
     * PATCH /admin/order-returns/{orderReturn}/approve
     */
    public function approve(Request $request, OrderReturn $orderReturn): JsonResponse
    {
        if ($orderReturn->status !== 'pending') {
            return ApiResponse::error(
                ['return' => ['This return request has already been processed.']],
                'Return already processed.',
                422
            );
        }

        $request->validate([
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        $orderReturn = $this->orderReturnService->approveReturn(
            $orderReturn,
            $request->input('admin_notes')
        );

        return ApiResponse::success([
            'return' => [
                'id' => $orderReturn->id,
                'status' => $orderReturn->status,
                'adminNotes' => $orderReturn->admin_notes,
                'approvedAt' => $orderReturn->approved_at,
            ],
        ], 'Return request approved and refund initiated.');
    }

    /**
     * Reject a return request.
     * PATCH /admin/order-returns/{orderReturn}/reject
     */
    public function reject(Request $request, OrderReturn $orderReturn): JsonResponse
    {
        if ($orderReturn->status !== 'pending') {
            return ApiResponse::error(
                ['return' => ['This return request has already been processed.']],
                'Return already processed.',
                422
            );
        }

        $request->validate([
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        $orderReturn = $this->orderReturnService->rejectReturn(
            $orderReturn,
            $request->input('admin_notes')
        );

        return ApiResponse::success([
            'return' => [
                'id' => $orderReturn->id,
                'status' => $orderReturn->status,
                'adminNotes' => $orderReturn->admin_notes,
                'rejectedAt' => $orderReturn->rejected_at,
            ],
        ], 'Return request rejected.');
    }

    /**
     * Get count of pending return requests.
     * GET /admin/order-returns/pending-count
     */
    public function pendingCount(): JsonResponse
    {
        $count = OrderReturn::where('status', 'pending')->count();

        return ApiResponse::success([
            'count' => $count,
        ]);
    }

    /**
     * Get return policy.
     * GET /admin/return-policy
     */
    public function policy(): JsonResponse
    {
        $policy = $this->orderReturnService->getReturnPolicy();

        return ApiResponse::success([
            'returnPolicy' => [
                'id' => $policy->id ?? null,
                'returnWindowDays' => $policy->return_window_days,
                'isActive' => $policy->is_active,
            ],
        ]);
    }

    /**
     * Update return policy.
     * PUT /admin/return-policy
     */
    public function updatePolicy(Request $request): JsonResponse
    {
        $request->validate([
            'return_window_days' => 'required|integer|min:1|max:365',
            'is_active' => 'required|boolean',
        ]);

        $policy = $this->orderReturnService->updateReturnPolicy($request->only(['return_window_days', 'is_active']));

        return ApiResponse::success([
            'returnPolicy' => [
                'id' => $policy->id,
                'returnWindowDays' => $policy->return_window_days,
                'isActive' => $policy->is_active,
            ],
        ], 'Return policy updated.');
    }

    protected function resolveCustomerName(OrderReturn $return): ?string
    {
        $order = $return->order;
        if (!$order) {
            return null;
        }

        if ($order->user) {
            return trim(($order->user->name ?? '') . ' ' . ($order->user->last_name ?? ''));
        }

        return ($order->meta ?? [])['customer_name'] ?? 'Guest';
    }

    protected function resolveCustomerEmail(OrderReturn $return): ?string
    {
        $order = $return->order;
        if (!$order) {
            return null;
        }

        if ($order->user && $order->user->email) {
            return $order->user->email;
        }

        return ($order->meta ?? [])['customer_email'] ?? null;
    }
}
