<?php


namespace App\Repositories;

use App\Models\WorkingHour;
use App\Repositories\Interfaces\WorkingHourRepositoryInterface;

class WorkingHourRepository implements WorkingHourRepositoryInterface
{
    public function findByWeekdayId(int $weekdayId): ?WorkingHour
    {
        return WorkingHour::query()
            ->where('weekday_id', $weekdayId)
            ->first();
    }

    public function getAllWithWeekday(): \Illuminate\Database\Eloquent\Collection
    {
        return WorkingHour::query()
            ->with('weekday')
            ->get();
    }

    public function upsertByWeekdayId(int $weekdayId, array $data): void
    {
        WorkingHour::updateOrCreate(
            ['weekday_id' => $weekdayId],
            $data
        );
    }
}
