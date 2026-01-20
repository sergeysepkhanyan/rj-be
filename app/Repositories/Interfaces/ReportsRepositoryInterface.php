<?php

namespace App\Repositories\Interfaces;

use Illuminate\Support\Collection;

interface ReportsRepositoryInterface
{
    public function getTodaysTurnover(?string $date = null): Collection;
    public function getTopServices(int $limit = 5): Collection;
    public function getTopProducts(int $limit = 5): Collection;
}
