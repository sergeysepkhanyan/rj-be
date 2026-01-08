<?php


namespace App\Repositories\Interfaces;

use App\Models\WorkingHour;

interface WorkingHourRepositoryInterface
{
    public function findByWeekdayId(int $weekdayId): ?WorkingHour;
    public function getAllWithWeekday();

    public function upsertByWeekdayId(int $weekdayId, array $data): void;
}

