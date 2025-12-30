<?php

namespace App\Repositories\Interfaces;

use App\Models\Order;

interface OrderRepositoryInterface
{
    public function all();
    public function find(int $id): Order;
    public function findByOrderable(string $orderableType, int $orderableId): ?Order;
    public function create(array $data): Order;
    public function update(Order $order, array $data): Order;
}
