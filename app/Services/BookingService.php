<?php

namespace App\Services;

use App\Filters\BookingFilter;
use App\Models\Booking;
use App\Models\SubService;
use App\Models\SubServiceItem;
use App\Models\Weekday;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Repositories\Interfaces\SubServiceItemRepositoryInterface;
use App\Repositories\Interfaces\SubServiceRepositoryInterface;
use App\Repositories\Interfaces\WorkingHourRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        protected BookingRepositoryInterface $bookingRepository,
        protected SubServiceRepositoryInterface $subServiceRepository,
        protected SubServiceItemRepositoryInterface $subServiceItemRepository,
        protected WorkingHourRepositoryInterface $workingHourRepository
    ) {}

    public function getAllBooking()
    {
        return $this->bookingRepository->all();
    }

    public function getBookingById($id)
    {
        return $this->bookingRepository->find($id);
    }

    public function deleteBreak(Booking $booking)
    {
        return $this->bookingRepository->delete($booking);
    }

    public function getPaginatedBookings(?BookingFilter $filter = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return $this->bookingRepository->paginateWithFilter($filter, $perPage, $page);
    }

    /**
     * Create a break (single master range).
     */
    public function createBreak(array $data): Booking | null
    {
        $tz = $data['timezone'] ?? 'UTC';

        $date = trim($data['date']);
        $startTime = trim($data['start_time']);
        $endTime   = trim($data['end_time']);

        $start = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$startTime}", $tz);
        $end   = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$endTime}", $tz);

        if ($end->lte($start)) {
            throw new HttpResponseException(
                ApiResponse::error(['endTime' => 'End time must be after start time.'], 'Validation failed', 422)
            );
        }

        $hasOverlap = $this->bookingRepository->hasOverlap(
            masterId:  (int) $data['master_id'],
            date:      $date,
            startTime: $start->format('H:i'),
            endTime:   $end->format('H:i'),
        );

        if ($hasOverlap) {
            throw new HttpResponseException(
                ApiResponse::error([], 'Master is not available in selected time range.', 422)
            );
        }

        $breakData = [
            'user_id' => null,
            'master_id' => (int) $data['master_id'],
            'type' => 'break',
            'status' => $data['status'] ?? 'active',
            'date' => $date,
            'timezone' => $tz,
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'duration' => $start->diffInMinutes($end),
            'duration_unit' => 'minutes',
            'customer_name' => 'Break',
            'customer_email' => null,
            'customer_phone' => null,
            'notes' => $data['notes'] ?? null,
            'price' => null,
            'discount_type' => null,
            'discount_value' => null,
            'discount_label' => null,
            'final_price' => null,
            'payment_mode' => null,
            'payment_status' => null,
        ];

        return $this->bookingRepository->create($breakData);
    }

    public function updateBreak(Booking $booking, array $data): Booking
    {
        if ($booking->type !== 'break') {
            throw new HttpResponseException(
                ApiResponse::error([], 'Only breaks can be updated with this endpoint.', 422)
            );
        }

        $tz = $data['timezone'] ?? $booking->timezone ?? 'UTC';

        $date = isset($data['date']) ? trim($data['date']) : $booking->date;

        $startTime = isset($data['start_time'])
            ? trim($data['start_time'])
            : $booking->start_time;

        $endTime = isset($data['end_time'])
            ? trim($data['end_time'])
            : $booking->end_time;

        $start = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$startTime}", $tz);
        $end   = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$endTime}", $tz);

        if ($end->lte($start)) {
            throw new HttpResponseException(
                ApiResponse::error(['endTime' => 'End time must be after start time.'], 'Validation failed', 422)
            );
        }

        $hasOverlap = $this->bookingRepository->hasOverlap(
            masterId: $booking->master_id,
            date: $date,
            startTime: $start->format('H:i'),
            endTime: $end->format('H:i'),
            excludeBookingId: $booking->id
        );

        if ($hasOverlap) {
            throw new HttpResponseException(
                ApiResponse::error([], 'Master is not available in selected time range.', 422)
            );
        }

        $updateData = [
            'date' => $date,
            'timezone' => $tz,
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'duration' => $start->diffInMinutes($end),
            'duration_unit' => 'minutes',
        ];

        if (array_key_exists('notes', $data)) {
            $updateData['notes'] = $data['notes'];
        }

        return $this->bookingRepository->update($booking, $updateData);
    }

    /**
     * Available slots for ONE master and ONE chosen service duration.
     * Uses same timezone + same business hours + same overlap base (repo busy).
     */
    public function getAvailableSlots(array $data): array
    {
        $tz = $data['timezone'] ?? 'UTC';

        $masterId         = (int) ($data['master_id'] ?? 0);
        $date             = trim($data['date'] ?? '');
        $subserviceId     = $data['sub_service_id'] ?? null;
        $subserviceItemId = $data['sub_service_item_id'] ?? null;

        if (!$masterId || !$date) {
            throw new HttpResponseException(
                ApiResponse::error(['masterId' => 'master_id and date are required.'], 'Validation failed', 422)
            );
        }

        $durationMinutes = $this->resolveDurationMinutes($subserviceId, $subserviceItemId);

        $hours = $this->getWorkingHours($date, $tz);

        if ($hours['is_closed']) {
            return [];
        }

        $workStart = $hours['start'];
        $workEnd   = $hours['end'];

        $busy = $this->bookingRepository->getBusyForMasterOnDate($masterId, $date);

        return $this->buildSlots($date, $workStart, $workEnd, $busy, $durationMinutes, $tz);
    }

    protected function resolveDurationMinutes(?int $subserviceId, ?int $subserviceItemId): int
    {
        if ($subserviceItemId) {
            $item = $this->subServiceItemRepository->find($subserviceItemId);
            return (int) ($item->duration ?? 0);
        }

        if ($subserviceId) {
            $sub = $this->subServiceRepository->find($subserviceId);
            return (int) ($sub->duration ?? 0);
        }

        return 30;
    }

    /**
     * Build free slots between workStart/workEnd skipping busy intervals.
     *
     * @return array<array{start:string,end:string}>
     */
    protected function buildSlots(
        string $date,
        string $workStart,
        string $workEnd,
        Collection $busy,
        int $durationMinutes,
        string $tz
    ): array {
        $slots = [];

        $dayStart = Carbon::createFromFormat('Y-m-d H:i', "$date $workStart", $tz);
        $dayEnd   = Carbon::createFromFormat('Y-m-d H:i', "$date $workEnd", $tz);
        $now      = Carbon::now($tz);

        if ($dayEnd->lt($now->copy()->startOfDay())) {
            return [];
        }

        $busyIntervals = $busy->map(function ($row) use ($tz) {
            $rowTz = $row->timezone ?? 'UTC';

            $rowDate = is_string($row->date)
                ? trim(substr($row->date, 0, 10))
                : Carbon::parse($row->date, $rowTz)->toDateString();

            $startStr = trim((string) $row->start_time);
            $endStr   = trim((string) $row->end_time);

            if (strlen($startStr) === 5) $startStr .= ':00';
            if (strlen($endStr) === 5)   $endStr   .= ':00';

            $startLocal = Carbon::createFromFormat('Y-m-d H:i:s', $rowDate.' '.$startStr, $rowTz);
            $endLocal   = Carbon::createFromFormat('Y-m-d H:i:s', $rowDate.' '.$endStr, $rowTz);

            return [
                'start' => $startLocal->copy()->setTimezone($tz),
                'end'   => $endLocal->copy()->setTimezone($tz),
            ];
        });

        $cursor = $dayStart->copy();

        if ($dayStart->isSameDay($now)) {
            $roundedNow = $now->copy()->setSecond(0);

            $mod = $roundedNow->minute % 5;
            if ($mod !== 0) {
                $roundedNow->addMinutes(5 - $mod);
            }

            if ($roundedNow->gt($cursor)) {
                $cursor = $roundedNow;
            }
        }

        while ($cursor->copy()->addMinutes($durationMinutes) <= $dayEnd) {
            $slotStart = $cursor->copy();
            $slotEnd   = $cursor->copy()->addMinutes($durationMinutes);

            if ($dayStart->isSameDay($now) && $slotStart->lte($now)) {
                $cursor->addMinutes(5);
                continue;
            }

            $overlaps = $busyIntervals->contains(function ($interval) use ($slotStart, $slotEnd) {
                return $slotStart < $interval['end'] && $slotEnd > $interval['start'];
            });

            if (! $overlaps) {
                $slots[] = [
                    'start' => $slotStart->format('H:i'),
                    'end'   => $slotEnd->format('H:i'),
                ];
            }

            $cursor->addMinutes(5);
        }

        return $slots;
    }

    public function createBooking(array $data): Booking
    {
        $user = auth()->user();

        $tz   = $data['timezone'] ?? 'UTC';
        $date = trim($data['date'] ?? '');

        $rawServices = $data['services'] ?? [];
        if (!is_array($rawServices) || count($rawServices) === 0) {
            throw new HttpResponseException(
                ApiResponse::error(['services' => 'At least one service is required.'], 'Validation failed', 422)
            );
        }

        $segments = $this->buildServiceSegmentsFromRequest($date, $rawServices, $tz);

        $this->validateSegments($segments, $tz);
        $this->validateRootTimeMatchesSegments($data, $segments);

        $pricing = $this->buildPricingData([
            ...$data,
            'services' => $this->normalizeServicesForPricing($rawServices),
        ]);

        return DB::transaction(function () use ($data, $user, $tz, $date, $segments, $pricing) {

            $bookingStart = substr($segments[0]['start_time'], 0, 5);
            $bookingEnd   = substr($segments[count($segments) - 1]['end_time'], 0, 5);

            $bookingData = [
                'user_id'        => $user?->id,
                'type'           => 'booking',
                'status'         => 'pending',
                'date'           => $date,
                'timezone'       => $tz,
                'start_time'     => $bookingStart,
                'end_time'       => $bookingEnd,
                'duration'       => $this->calculateDuration($bookingStart, $bookingEnd),
                'duration_unit'  => 'minutes',

                'price'          => $pricing['total_price'],
                'discount_type'  => $pricing['discount_type'],
                'discount_value' => $pricing['discount_value'],
                'discount_label' => $pricing['discount_label'],
                'final_price'    => $pricing['final_price'],
                'payment_mode'   => $pricing['payment_mode'],
                'payment_status' => $pricing['payment_status'],

                'customer_name'  => $data['customer_name']  ?? $data['customerName']  ?? ($user->name ?? null),
                'customer_phone' => $data['customer_phone'] ?? $data['customerPhone'] ?? ($user->mobile ?? null),
                'customer_email' => $data['customer_email'] ?? $data['customerEmail'] ?? ($user->email ?? null),
                'notes'          => $data['notes'] ?? null,
                'master_id'      => $segments[0]['master_id'] ?? null,
            ];

            /** @var Booking $booking */
            $booking = $this->bookingRepository->create($bookingData);

            foreach ($segments as $i => $seg) {
                $this->attachServiceToBookingWithSegment($booking, $seg, $i + 1);
            }

            return $booking->load(['services.bookable', 'services.master']);
        });
    }

    /**
     * UPDATE BOOKING (same as create, but excludes current booking id from overlap).
     */
    public function updateBooking(Booking $booking, array $data): Booking
    {
        $tz   = $data['timezone'] ?? $booking->timezone ?? 'UTC';
        $date = trim($data['date'] ?? ($booking->date?->format('Y-m-d') ?? ''));

        $rawServices = $data['services'] ?? [];
        if (!is_array($rawServices) || count($rawServices) === 0) {
            throw new HttpResponseException(
                ApiResponse::error(['services' => 'At least one service is required.'], 'Validation failed', 422)
            );
        }

        $segments = $this->buildServiceSegmentsFromRequest($date, $rawServices, $tz);

        $this->validateSegments($segments, $tz, excludeBookingId: $booking->id);
        $this->validateRootTimeMatchesSegments($data, $segments);

        $pricing = $this->buildPricingData([
            ...$data,
            'services' => $this->normalizeServicesForPricing($rawServices),
        ]);

        return DB::transaction(function () use ($booking, $data, $tz, $date, $segments, $pricing) {

            $bookingStart = substr($segments[0]['start_time'], 0, 5);
            $bookingEnd   = substr($segments[count($segments) - 1]['end_time'], 0, 5);

            $booking->update([
                'date'           => $date,
                'timezone'       => $tz,
                'start_time'     => $bookingStart,
                'end_time'       => $bookingEnd,
                'duration'       => $this->calculateDuration($bookingStart, $bookingEnd),
                'duration_unit'  => 'minutes',

                'price'          => $pricing['total_price'],
                'discount_type'  => $pricing['discount_type'],
                'discount_value' => $pricing['discount_value'],
                'discount_label' => $pricing['discount_label'],
                'final_price'    => $pricing['final_price'],
                'payment_mode'   => $pricing['payment_mode'],
                'payment_status' => $pricing['payment_status'],

                'customer_name'  => $data['customer_name']  ?? $data['customerName']  ?? $booking->customer_name,
                'customer_phone' => $data['customer_phone'] ?? $data['customerPhone'] ?? $booking->customer_phone,
                'customer_email' => $data['customer_email'] ?? $data['customerEmail'] ?? $booking->customer_email,
                'notes'          => $data['notes'] ?? $booking->notes,

                'master_id'      => $segments[0]['master_id'] ?? null,
            ]);

            $booking->services()->delete();

            foreach ($segments as $i => $seg) {
                $this->attachServiceToBookingWithSegment($booking, $seg, $i + 1);
            }

            return $booking->load(['services.bookable', 'services.master']);
        });
    }

    /**
     * Build segments from REQUEST times (services[].startTime/endTime) and validate duration matches DB.
     */
    protected function buildServiceSegmentsFromRequest(string $date, array $rawServices, string $tz): array
    {
        $segments = [];

        foreach (collect($rawServices)->values() as $index => $s) {
            $serviceType = $s['service_type'] ?? $s['serviceType'] ?? null;
            $serviceId   = $s['service_id']   ?? $s['serviceId']   ?? null;
            $masterId    = $s['master_id']    ?? $s['masterId']    ?? null;

            $startTime   = $s['start_time']   ?? $s['startTime']   ?? null;
            $endTime     = $s['end_time']     ?? $s['endTime']     ?? null;

            if (!$serviceType || !$serviceId || !$masterId) {
                throw new HttpResponseException(
                    ApiResponse::error(['services' => "services[$index] must include serviceType/serviceId/masterId."], 'Validation failed', 422)
                );
            }

            if (!$startTime || !$endTime) {
                throw new HttpResponseException(
                    ApiResponse::error(['services' => "services[$index] must include startTime/endTime."], 'Validation failed', 422)
                );
            }

            $serviceable = $this->resolveServiceable($serviceType, (int) $serviceId);
            $expectedMinutes = (int) ($serviceable->duration ?? 0);

            $start = $this->parseTimeToCarbon($date, (string)$startTime, $tz);
            $end   = $this->parseTimeToCarbon($date, (string)$endTime, $tz);

            if ($end->lte($start)) {
                throw new HttpResponseException(
                    ApiResponse::error(['endTime' => "services[$index].endTime must be after startTime."], 'Validation failed', 422)
                );
            }

            $expectedMinutes = (int) $serviceable->duration;

            $start = $this->parseTimeToCarbon($date, (string) $startTime, $tz);
            $end   = $this->parseTimeToCarbon($date, (string) $endTime, $tz);

            $actualMinutes = (int) $start->diffInMinutes($end);

            if ($expectedMinutes <= 0) {
                throw new HttpResponseException(
                    ApiResponse::error([
                        'serviceDuration' => "services[$index] has invalid configured duration."
                    ], 'Validation failed', 422)
                );
            }

            if ($actualMinutes !== $expectedMinutes) {
                throw new HttpResponseException(
                    ApiResponse::error([
                        'serviceDuration' => "services[$index] requires {$expectedMinutes} minutes, but provided range is {$actualMinutes} minutes."
                    ], 'Validation failed', 422)
                );
            }

            $segments[] = [
                'master_id'        => (int) $masterId,
                'bookable_type'    => get_class($serviceable),
                'bookable_id'      => $serviceable->id,
                'duration_minutes' => $expectedMinutes,
                'price'            => (float) ($s['price'] ?? 0),
                'sort_order'       => $s['sort_order'] ?? $s['sortOrder'] ?? null,
                'date'             => $date,
                'timezone'         => $tz,
                'start_time'       => $start->format('H:i:s'),
                'end_time'         => $end->format('H:i:s'),
            ];
        }

        usort($segments, fn ($a, $b) => strcmp($a['start_time'], $b['start_time']));

        return $segments;
    }

    protected function validateSegments(array $segments, string $tz, ?int $excludeBookingId = null): void
    {
        $now = Carbon::now($tz);

        foreach ($segments as $seg) {
            $date = $seg['date'];

            $hours = $this->getWorkingHours($date, $tz);
            if ($hours['is_closed']) {
                throw new HttpResponseException(
                    ApiResponse::error(['workingHours' => 'Business is closed on selected day.'], 'Validation failed', 422)
                );
            }

            $workStart = $hours['start'];
            $workEnd   = $hours['end'];

            $start = $this->parseTimeToCarbon($date, $seg['start_time'], $tz);
            $end   = $this->parseTimeToCarbon($date, $seg['end_time'], $tz);

            if ($start->lte($now)) {
                throw new HttpResponseException(
                    ApiResponse::error(['startTime' => 'Start time must be in the future.'], 'Validation failed', 422)
                );
            }

            $dayStart = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$workStart}", $tz);
            $dayEnd   = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$workEnd}", $tz);

            if ($start->lt($dayStart) || $end->gt($dayEnd)) {
                throw new HttpResponseException(
                    ApiResponse::error([
                        'workingHours' => "Booking must be within working hours ({$workStart}–{$workEnd})."
                    ], 'Validation failed', 422)
                );
            }

            if ($hours['break_start'] && $hours['break_end']) {
                $breakStart = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$hours['break_start']}", $tz);
                $breakEnd   = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$hours['break_end']}", $tz);

                if ($start->lt($breakEnd) && $end->gt($breakStart)) {
                    throw new HttpResponseException(
                        ApiResponse::error([
                            'breakTime' => "Booking overlaps break time ({$hours['break_start']}–{$hours['break_end']})."
                        ], 'Validation failed', 422)
                    );
                }
            }

            if (($start->minute % 5) !== 0 || ($end->minute % 5) !== 0) {
                throw new HttpResponseException(
                    ApiResponse::error(['grid' => 'Time must be in 5-minute increments.'], 'Validation failed', 422)
                );
            }

            $hasOverlap = $this->bookingRepository->hasOverlap(
                masterId: (int) $seg['master_id'],
                date: $date,
                startTime: substr($seg['start_time'], 0, 5),
                endTime: substr($seg['end_time'], 0, 5),
                excludeBookingId: $excludeBookingId
            );

            if ($hasOverlap) {
                throw new HttpResponseException(
                    ApiResponse::error(['masterId' => 'Master is not available in selected time range.'], 'Master is not available in selected time range.', 422)
                );
            }
        }
    }


    /**
     * Optional: if root startTime/endTime exist, ensure they match segments min/max.
     */
    protected function validateRootTimeMatchesSegments(array $data, array $segments): void
    {
        $rootStart = $data['start_time'] ?? $data['startTime'] ?? null;
        $rootEnd   = $data['end_time']   ?? $data['endTime']   ?? null;

        if (!$rootStart && !$rootEnd) {
            return;
        }

        $min = substr($segments[0]['start_time'], 0, 5);
        $max = substr($segments[count($segments) - 1]['end_time'], 0, 5);

        if ($rootStart && $rootStart !== $min) {
            throw new HttpResponseException(
                ApiResponse::error(['startTime' => "startTime must match first service start ({$min})."], 'Validation failed', 422)
            );
        }

        if ($rootEnd && $rootEnd !== $max) {
            throw new HttpResponseException(
                ApiResponse::error(['endTime' => "endTime must match last service end ({$max})."], 'Validation failed', 422)
            );
        }
    }

    protected function resolveServiceable(string $type, int $id): Model
    {
        $class = match ($type) {
            'subservice' => SubService::class,
            'item'       => SubServiceItem::class,
            default      => null,
        };

        if (! $class) {
            throw new HttpResponseException(
                ApiResponse::error(['serviceType' => 'Unknown service type.'], 'Validation failed', 422)
            );
        }

        return $class::findOrFail($id);
    }

    protected function attachServiceToBookingWithSegment(Booking $booking, array $seg, int $defaultSort): void
    {
        $booking->services()->create([
            'master_id'        => $seg['master_id'],
            'bookable_id'      => $seg['bookable_id'],
            'bookable_type'    => $seg['bookable_type'],
            'price'            => $seg['price'],
            'duration_minutes' => $seg['duration_minutes'],
            'sort_order'       => $seg['sort_order'] ?? $defaultSort,
            'date'             => $seg['date'],
            'timezone'         => $seg['timezone'],
            'start_time'       => $seg['start_time'],
            'end_time'         => $seg['end_time'],
        ]);
    }

    protected function normalizeServicesForPricing(array $rawServices): array
    {
        return collect($rawServices)->values()->map(function ($s) {
            return [
                'price' => $s['price'] ?? 0,
            ];
        })->all();
    }

    protected function buildPricingData(array $data): array
    {
        $services = collect($data['services'] ?? []);
        $totalPrice = (float) $services->sum(fn ($s) => $s['price'] ?? 0);

        $discountType  = $data['discount_type']  ?? $data['discountType']  ?? 'none';
        $discountValue = $data['discount_value'] ?? $data['discountValue'] ?? null;
        $discountLabel = $data['discount_label'] ?? $data['discountLabel'] ?? null;

        $discountAmount = $this->calculateDiscountAmount(
            totalPrice: $totalPrice,
            discountType: $discountType,
            discountValue: $discountValue !== null ? (float)$discountValue : null
        );

        $finalPrice = max($totalPrice - $discountAmount, 0);

        $paymentMode = $data['payment_mode'] ?? $data['paymentMode'] ?? 'pay_later';

        $paymentStatus = match ($paymentMode) {
            'pay_now'   => 'paid',
            default     => 'unpaid',
        };

        return [
            'total_price'     => $totalPrice,
            'discount_type'   => $discountType,
            'discount_value'  => $discountValue,
            'discount_label'  => $discountLabel,
            'discount_amount' => $discountAmount,
            'final_price'     => $finalPrice,
            'payment_mode'    => $paymentMode,
            'payment_status'  => $paymentStatus,
        ];
    }

    protected function calculateDiscountAmount(float $totalPrice, ?string $discountType, ?float $discountValue): float
    {
        if (! $discountType || $discountType === 'none' || ! $discountValue) {
            return 0.0;
        }

        return match ($discountType) {
            'percent' => round($totalPrice * ($discountValue / 100), 2),
            'fixed'   => min($discountValue, $totalPrice),
            default   => 0.0,
        };
    }

    protected function calculateDuration(string $startTime, string $endTime): int
    {
        $start = Carbon::parse($startTime);
        $end   = Carbon::parse($endTime);

        return $start->diffInMinutes($end);
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

        throw new HttpResponseException(
            ApiResponse::error(['time' => "Invalid time format: {$time}"], 'Validation failed', 422)
        );
    }

}
