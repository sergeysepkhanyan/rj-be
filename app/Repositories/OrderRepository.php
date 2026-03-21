<?php

namespace App\Repositories;

use App\Filters\OrderFilter;
use App\Models\Order;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRepository implements OrderRepositoryInterface
{
    public function all()
    {
        return Order::all();
    }

    public function find(int $id): Order
    {
        return Order::findOrFail($id);
    }

    public function findByOrderable(string $orderableType, int $orderableId): ?Order
    {
        return Order::where('orderable_type', $orderableType)
            ->where('orderable_id', $orderableId)
            ->first();
    }

    public function create(array $data): Order
    {
        return Order::create($data);
    }

    public function update(Order $order, array $data): Order
    {
        $order->update($data);
        return $order;
    }

    public function paginateWithFilter(?OrderFilter $filter = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $query = Order::query()
            ->with([
                'user',
                'items.product.files',
                'shippingAddress.country',
                'billingAddress.country',
                'orderable',
                'latestPayment.paymentMethod',
                'orderReturn',
            ]);

        if ($filter) {
            $query = $filter->apply($query);
        }

        return $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function paginateWithFilterForUser(int $userId, ?OrderFilter $filter = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $query = Order::query()
            ->where('user_id', $userId)
            ->with([
                'user',
                'items.product.files',
                'shippingAddress.country',
                'billingAddress.country',
                'orderable',
                'latestPayment',
            ]);

        if ($filter) {
            $query = $filter->apply($query);
        }

        return $query->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
