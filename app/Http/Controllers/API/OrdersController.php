<?php

namespace App\Http\Controllers\API;

use App\Filters\OrderFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderListResource;
use App\Models\Booking;
use App\Models\Order;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrdersController extends Controller
{
    public function index(Request $request, OrderFilter $filter): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 10);
        $page    = (int) $request->input('page', 1);

        $query = Order::query()
            ->where('user_id', auth()->id())
            ->with(['items.product', 'shippingAddress', 'billingAddress', 'orderable']);

        $orders = $filter->apply($query)->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $orders->getCollection()->each(function (Order $order) {
            if ($order->orderable instanceof Booking) {
                $order->loadMissing('orderable.services.bookable');
            }
        });

        return ApiResponse::success([
            'orders' => OrderListResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ],
            'links' => [
                'first' => $orders->url(1),
                'last'  => $orders->url($orders->lastPage()),
                'prev'  => $orders->previousPageUrl(),
                'next'  => $orders->nextPageUrl(),
            ],
        ], __('success.orders.listed'));
    }
}
