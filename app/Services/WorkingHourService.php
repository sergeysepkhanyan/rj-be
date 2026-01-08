<?php

namespace App\Services;

use App\Models\Weekday;
use App\Repositories\Interfaces\WorkingHourRepositoryInterface;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkingHourService
{
    public function __construct(
        private readonly WorkingHourRepositoryInterface $workingHourRepository
    ) {}

    public function getSchedule(): Collection
    {
        return $this->workingHourRepository->getAllWithWeekday();
    }

    public function bulkUpdate(array $days): void
    {
        DB::transaction(function () use ($days) {
            foreach ($days as $d) {
                $weekday = Weekday::query()->where('day', (int)$d['day'])->first();

                if (! $weekday) {
                    throw new HttpResponseException(
                        ApiResponse::error(['day' => "Invalid weekday day={$d['day']}"], 'Validation failed', 422)
                    );
                }

                $payload = $this->normalizePayload($d);

                $this->workingHourRepository->upsertByWeekdayId($weekday->id, $payload);
            }
        });
    }

    public function updateDay(int $day, array $data): void
    {
        if ($day < 1 || $day > 7) {
            throw new HttpResponseException(
                ApiResponse::error(['day' => 'Day must be between 1 and 7.'], 'Validation failed', 422)
            );
        }

        $weekday = Weekday::query()->where('day', $day)->first();
        if (! $weekday) {
            throw new HttpResponseException(
                ApiResponse::error(['day' => 'Weekday not found.'], 'Validation failed', 422)
            );
        }

        $payload = $this->normalizePayload($data);

        $this->workingHourRepository->upsertByWeekdayId($weekday->id, $payload);
    }

    private function normalizePayload(array $data): array
    {
        $isClosed = (bool)($data['is_closed'] ?? false);

        return [
            'is_closed' => $isClosed,
            'start_time' => $isClosed ? null : ($data['start_time'] ?? null),
            'end_time'   => $isClosed ? null : ($data['end_time'] ?? null),
            'break_start_time' => $data['break_start_time'] ?? null,
            'break_end_time'   => $data['break_end_time'] ?? null,
        ];
    }
}
