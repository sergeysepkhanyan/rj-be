<?php

namespace App\Repositories\Interfaces;

use Illuminate\Support\Collection;

interface ReportsRepositoryInterface
{
    public function getTodaysTurnover(): Collection;
    public function getTopServices(int $limit = 5): Collection;
    public function getTopProducts(int $limit = 5): Collection;
}
