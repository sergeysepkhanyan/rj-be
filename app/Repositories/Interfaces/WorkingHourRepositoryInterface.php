<?php


namespace App\Repositories\Interfaces;

use App\Models\WorkingHour;

interface WorkingHourRepositoryInterface
{
    public function findByWeekdayId(int $weekdayId): ?WorkingHour;
}

