<?php

namespace App\Services;

use App\Repositories\Interfaces\ReportsRepositoryInterface;
use Illuminate\Support\Collection;

class ReportsService
{
    public function __construct(
        protected ReportsRepositoryInterface $reportsRepository
    ) {}

    public function todaysTurnover(): Collection
    {
        return $this->reportsRepository->getTodaysTurnover();
    }

    public function topServices(int $limit = 5): Collection
    {
        return $this->reportsRepository->getTopServices($limit);
    }

    public function topProducts(int $limit = 5): Collection
    {
        return $this->reportsRepository->getTopProducts($limit);
    }
}
