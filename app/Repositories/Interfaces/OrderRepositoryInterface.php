<?php

namespace App\Repositories\Interfaces;

use App\Filters\OrderFilter;
use App\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    public function all();
    public function find(int $id): Order;
    public function findByOrderable(string $orderableType, int $orderableId): ?Order;
    public function create(array $data): Order;
    public function update(Order $order, array $data): Order;
    public function paginateWithFilter(?OrderFilter $filter = null, int $perPage = 15, int $page = 1): LengthAwarePaginator;

    public function paginateWithFilterForUser(int $userId, ?OrderFilter $filter = null, int $perPage = 15, int $page = 1): LengthAwarePaginator;
}
