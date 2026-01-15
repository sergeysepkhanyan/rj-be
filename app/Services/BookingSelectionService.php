<?php

namespace App\Services;

use App\Models\BookingSelection;
use App\Models\SubService;
use App\Models\SubServiceItem;
use App\Repositories\Interfaces\BookingSelectionRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;

class BookingSelectionService
{
    public function __construct(
        protected BookingSelectionRepositoryInterface $selectionRepository
    ) {}

    public function createSelection(array $data): BookingSelection
    {
        $userId = auth()->user()?->id;
        $guestSessionId = $data['guest_session_id'] ?? null;
        if ($userId) {
            $guestSessionId = null;
        }

        if (!$userId && !$guestSessionId) {
            $this->throwValidation(
                ['guestSessionId' => __('validation.booking.guest_session_required')],
                'validation.failed'
            );
        }

        $date = trim((string)($data['date'] ?? ''));
        $tz = $data['timezone'] ?? 'UTC';
        $startInput = (string)($data['start_time'] ?? '');
        $endInput = (string)($data['end_time'] ?? '');

        $serviceType = (string)($data['service_type'] ?? '');
        $serviceId = (int)($data['service_id'] ?? 0);

        $bookableType = $this->mapBookableType($serviceType);

        $start = $this->parseTimeToCarbon($date, $startInput, $tz);
        $end = $this->parseTimeToCarbon($date, $endInput, $tz);

        if ($end->lte($start)) {
            $this->throwValidation(
                ['endTime' => __('validation.booking.end_after_start')],
                'validation.failed'
            );
        }

        $startTime = $start->format('H:i');
        $endTime = $end->format('H:i');
        $durationMinutes = (int) $start->diffInMinutes($end);

        if ($this->selectionRepository->hasOverlapForSession($userId, $guestSessionId, $date, $startTime, $endTime)) {
            $this->throwValidation(
                ['startTime' => __('validation.booking.slot_already_selected')],
                'validation.failed'
            );
        }

        return $this->selectionRepository->create([
            'user_id' => $userId,
            'guest_session_id' => $guestSessionId,
            'master_id' => null,
            'bookable_type' => $bookableType,
            'bookable_id' => $serviceId,
            'duration_minutes' => $durationMinutes,
            'date' => $date,
            'timezone' => $tz,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    public function attachGuestSelectionsToUser(string $guestSessionId, int $userId): void
    {
        $this->selectionRepository->assignGuestSessionToUser($guestSessionId, $userId);
    }

    protected function mapBookableType(string $type): string
    {
        return match ($type) {
            'SubService', 'subservice' => SubService::class,
            'SubServiceItem', 'item'   => SubServiceItem::class,
            default => '',
        };
    }

    protected function getWorkingHours(string $bookingDate, string $timezone): array
    {
        $day = CarbonImmutable::createFromFormat('Y-m-d', $bookingDate, $timezone);
        $isoDay = $day->dayOfWeekIso;

        $weekday = Weekday::query()->where('day', $isoDay)->first();

        if (! $weekday) {
            return ['is_closed' => true];
        }

        $row = $this->workingHourRepository->findByWeekdayId($weekday->id);

        if (! $row || $row->is_closed) {
            return ['is_closed' => true];
        }

        return [
            'is_closed' => false,
            'start' => substr((string) $row->start_time, 0, 5),
            'end'   => substr((string) $row->end_time, 0, 5),
            'break_start' => $row->break_start_time ? substr((string) $row->break_start_time, 0, 5) : null,
            'break_end'   => $row->break_end_time   ? substr((string) $row->break_end_time, 0, 5) : null,
        ];
    }

    protected function parseTimeToCarbon(string $date, string $time, string $tz): Carbon
    {
        $time = trim($time);

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", $tz);
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$time}", $tz);
        }

        $this->throwValidation(
            ['time' => __('validation.booking.invalid_time_format', ['time' => $time])],
            'validation.failed'
        );
    }

    protected function throwValidation(array $errors, string $messageKey, array $replace = [], int $status = 422): void
    {
        throw new HttpResponseException(
            ApiResponse::error($errors, __($messageKey, $replace), $status)
        );
    }
}
