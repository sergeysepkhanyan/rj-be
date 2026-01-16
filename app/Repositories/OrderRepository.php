<?php

namespace App\Repositories;

use App\Models\Order;
use App\Repositories\Interfaces\OrderRepositoryInterface;

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
}
