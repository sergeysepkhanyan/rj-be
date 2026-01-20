<?php

namespace App\Services;

use App\Filters\BookingFilter;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmedMail;
use App\Models\Booking;
use App\Models\User;
use App\Models\Weekday;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Repositories\Interfaces\BookingSelectionRepositoryInterface;
use App\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Repositories\Interfaces\SubServiceItemRepositoryInterface;
use App\Repositories\Interfaces\SubServiceRepositoryInterface;
use App\Repositories\Interfaces\WorkingHourRepositoryInterface;
use App\Support\VatCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BookingService
{
    public function __construct(
        protected BookingRepositoryInterface $bookingRepository,
        protected BookingSelectionRepositoryInterface $bookingSelectionRepository,
        protected SubServiceRepositoryInterface $subServiceRepository,
        protected SubServiceItemRepositoryInterface $subServiceItemRepository,
        protected WorkingHourRepositoryInterface $workingHourRepository,
        protected MasterAssignmentService $masterAssignmentService,
        protected OrderService $orderService,
        protected PaymentService $paymentService,
        protected PaymentRepositoryInterface $paymentRepository,
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

    public function createBreak(array $data): Booking | null
    {
        $tz = $data['timezone'] ?? 'UTC';

        $date = trim($data['date']);
        $startTime = trim($data['start_time']);
        $endTime   = trim($data['end_time']);

        $start = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$startTime}", $tz);
        $end   = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$endTime}", $tz);

        if ($end->lte($start)) {
            $this->throwValidation(
                ['endTime' => __('validation.break.end_after_start')],
                'validation.failed'
            );
        }

        $masterId = (int)($data['master_id'] ?? 0);
        if ($masterId && $this->isMasterOffOnDate($masterId, $date, $tz)) {
            $this->throwValidation(
                ['masterId' => __('validation.booking.master_day_off')],
                'validation.failed'
            );
        }

        $hasOverlap = $this->bookingRepository->hasOverlap(
            masterId:  $masterId,
            date:      $date,
            startTime: $start->format('H:i'),
            endTime:   $end->format('H:i'),
            timezone:  $tz,
        );

        if ($hasOverlap) {
            $this->throwValidation(
                ['masterId' => __('validation.break.master_unavailable')],
                'validation.failed'
            );
        }

        $breakData = [
            'user_id' => null,
            'master_id' => $masterId,
            'type' => 'break',
            'status' =>  'confirmed',
            'date' => $date,
            'timezone' => $tz,
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'duration' => $start->diffInMinutes($end),
            'duration_unit' => 'minutes',
            'customer_name' => 'Break',
            'customer_email' => null,
            'customer_phone' => null,
            'notes' => $data['notes'] ?? null
        ];

        return $this->bookingRepository->create($breakData);
    }

    public function updateBreak(Booking $booking, array $data): Booking
    {
        if ($booking->type !== 'break') {
            $this->throwValidation([], 'booking.break_only_update', []);
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
            $this->throwValidation(['endTime' => __('validation.break.end_after_start')], 'validation.failed');
        }

        if ($this->isMasterOffOnDate((int)$booking->master_id, $date, $tz)) {
            $this->throwValidation(['masterId' => __('validation.booking.master_day_off')], 'validation.failed');
        }

        $hasOverlap = $this->bookingRepository->hasOverlap(
            masterId: $booking->master_id,
            date: $date,
            startTime: $start->format('H:i'),
            endTime: $end->format('H:i'),
            excludeBookingId: $booking->id,
            timezone: $tz
        );

        if ($hasOverlap) {
            $this->throwValidation(['masterId' => __('validation.break.master_unavailable')], 'validation.failed');
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

    public function getAvailableSlots(array $data): array
    {
        $tz = $data['timezone'] ?? 'UTC';

        $masterId         = (int) ($data['master_id'] ?? 0);
        $date             = trim($data['date'] ?? '');
        $subserviceId     = $data['sub_service_id'] ?? null;
        $subserviceItemId = $data['sub_service_item_id'] ?? null;

        if (!$masterId || !$date) {
            $this->throwValidation(
                [
                    'masterId' => __('validation.available_slots.master_required'),
                    'date' => __('validation.available_slots.date_required'),
                ],
                'validation.failed'
            );
        }

        if ($this->isMasterOffOnDate($masterId, $date, $tz)) {
            return [];
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
            $this->throwValidation(
                ['services' => __('validation.booking.services_required')],
                'validation.failed'
            );
        }

        $rawServices = $this->masterAssignmentService->assignAndValidateMasters(
            date: $date,
            tz: $tz,
            services: $rawServices
        );

        $segments = $this->buildServiceSegmentsFromRequest($date, $rawServices, $tz);

        $this->validateSegmentsBasic($segments, $tz);
        $this->assertNoDuplicateSegments($segments);
        $this->validateRootTimeMatchesSegments($data, $segments);

        $pricing = $this->buildPricingData([
            ...$data,
            'services' => $this->normalizeServicesForPricing($segments),
        ]);

        return DB::transaction(function () use ($data, $user, $tz, $date, $segments, $pricing) {

            $bookingStart = substr($segments[0]['start_time'], 0, 5);
            $bookingEnd   = substr($segments[count($segments) - 1]['end_time'], 0, 5);
            $uniqueMasters = collect($segments)->pluck('master_id')->unique()->values();

            $bookingData = [
                'user_id'        => $user?->id,
                'type'           => 'booking',
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
                'status' => $pricing['payment_mode'] === 'pay_now' ? 'pending_payment' : 'confirmed',
                'expires_at' => $pricing['payment_mode'] === 'pay_now'
                    ? now()->addMinutes((int) config('payment.booking_hold_minutes', 10))
                    : null,
                'customer_name'  => $data['customer_name']  ?? $data['customerName']  ?? ($user->name ?? null),
                'customer_phone' => $data['customer_phone'] ?? $data['customerPhone'] ?? ($user->mobile ?? null),
                'customer_email' => $data['customer_email'] ?? $data['customerEmail'] ?? ($user->email ?? null),
                'notes'          => $data['notes'] ?? null,

                'master_id'      => $uniqueMasters->count() === 1 ? (int)$uniqueMasters->first() : null,
            ];

            $booking = $this->bookingRepository->create($bookingData);

            foreach ($segments as $i => $seg) {
                $this->attachServiceToBookingWithSegment($booking, $seg, $i + 1);
            }

            $this->clearSelectionsAfterBooking($user?->id, $data['guest_session_id'] ?? null);
            $order = $this->orderService->createForBooking($booking, $booking->payment_mode);
            if ($booking->payment_mode === 'pay_later') {
                $this->bookingRepository->update($booking, ['status' => 'confirmed']);
                return $booking->fresh()->load(['services.bookable', 'order.latestPayment']);
            }
            $provider = $data['payment_provider'] ?? $data['paymentProvider'] ?? 'stripe';
            $this->paymentService->startStripePaymentIntent($order, $booking);
            return $booking->load(['services.bookable', 'services.master', 'master', 'order.latestPayment']);
        });
    }

    public function updateBooking(Booking $booking, array $data): Booking
    {
        $tz   = $data['timezone'] ?? $booking->timezone ?? 'UTC';
        $date = trim($data['date'] ?? ($booking->date?->format('Y-m-d') ?? ''));

        $data['payment_mode'] = $booking->payment_mode;
        $data['paymentMode'] = $booking->payment_mode;

        $rawServices = $data['services'] ?? [];

        $rawServices = $this->masterAssignmentService->assignAndValidateMasters(
            date: $date,
            tz: $tz,
            services: $rawServices,
            excludeBookingId: $booking->id
        );

        $segments = $this->buildServiceSegmentsFromRequest($date, $rawServices, $tz);

        $this->validateSegmentsBasic($segments, $tz);
        $this->validateRootTimeMatchesSegments($data, $segments);

        $pricing = $this->buildPricingData([
            ...$data,
            'services' => $this->normalizeServicesForPricing($segments),
        ]);

        return DB::transaction(function () use ($booking, $data, $tz, $date, $segments, $pricing) {
            $bookingStart = substr($segments[0]['start_time'], 0, 5);
            $bookingEnd   = substr($segments[count($segments) - 1]['end_time'], 0, 5);

            $uniqueMasters = collect($segments)->pluck('master_id')->unique()->values();

            $updateData = [
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

                'customer_name'  => $data['customer_name']  ?? $data['customerName']  ?? $booking->customer_name,
                'customer_phone' => $data['customer_phone'] ?? $data['customerPhone'] ?? $booking->customer_phone,
                'customer_email' => $data['customer_email'] ?? $data['customerEmail'] ?? $booking->customer_email,
                'notes'          => $data['notes'] ?? $booking->notes,

                'master_id'      => $uniqueMasters->count() === 1 ? (int)$uniqueMasters->first() : null,
            ];

            if ($booking->payment_status === 'unpaid' || $booking->payment_status === null) {
                $updateData['payment_status'] = $pricing['payment_status'];
            }

            $booking->update($updateData);

            $booking->services()->delete();

            foreach ($segments as $i => $seg) {
                $this->attachServiceToBookingWithSegment($booking, $seg, $i + 1);
            }

            return $booking->load(['services.bookable', 'services.master']);
        });
    }

    protected function buildServiceSegmentsFromRequest(string $date, array $rawServices, string $tz): array
    {
        $segments = [];

        foreach (collect($rawServices)->values() as $index => $s) {
            $serviceType = $s['service_type'] ?? $s['serviceType'] ?? null;
            $serviceId   = $s['service_id']   ?? $s['serviceId']   ?? null;
            $masterId    = $s['master_id']    ?? $s['masterId']    ?? null;

            $startTime   = $s['start_time']   ?? $s['startTime']   ?? null;
            $endTime     = $s['end_time']     ?? $s['endTime']     ?? null;

            if (!$serviceType || !$serviceId) {
                $this->throwValidation(
                    ["services.$index.serviceId" => __('validation.booking.service_type_and_id_required')],
                    'validation.failed'
                );
            }

            if (!$masterId) {
                $this->throwValidation(
                    ["services.$index.masterId" => __('validation.booking.master_required_when_any_false')],
                    'validation.failed'
                );
            }

            if (!$startTime || !$endTime) {
                $this->throwValidation(
                    [
                        "services.$index.startTime" => __('validation.booking.service_start_required'),
                        "services.$index.endTime" => __('validation.booking.service_end_required'),
                    ],
                    'validation.failed'
                );
            }

            $serviceable = $this->resolveServiceable((string)$serviceType, (int) $serviceId);
            $expectedMinutes = (int) ($serviceable->duration ?? 0);

            $start = $this->parseTimeToCarbon($date, (string)$startTime, $tz);
            $end   = $this->parseTimeToCarbon($date, (string)$endTime, $tz);

            if ($end->lte($start)) {
                $this->throwValidation(
                    ["services.$index.endTime" => __('validation.booking.end_after_start')],
                    'validation.failed'
                );
            }

            $actualMinutes = (int) $start->diffInMinutes($end);

            if ($expectedMinutes <= 0) {
                $this->throwValidation(
                    ["services.$index.serviceId" => __('validation.booking.invalid_duration_config')],
                    'validation.failed'
                );
            }

            if ($actualMinutes !== $expectedMinutes) {
                $this->throwValidation(
                    ["services.$index.serviceId" => __('validation.booking.duration_mismatch', [
                        'expected' => $expectedMinutes,
                        'actual' => $actualMinutes,
                    ])],
                    'validation.failed'
                );
            }

            $basePrice  = (float) ($serviceable->price ?? 0);
            $vatEnabled = (bool)  ($serviceable->vat_enabled ?? false);
            $vat = VatCalculator::breakdown($basePrice, $vatEnabled);

            $isAnyMaster = (bool)($s['any_master'] ?? $s['anyMaster'] ?? false);

            $segments[] = [
                'master_id'        => (int) $masterId,
                'is_any_master'    => $isAnyMaster,
                'bookable_type'    => get_class($serviceable),
                'bookable_id'      => $serviceable->id,
                'duration_minutes' => $expectedMinutes,
                'price'            => (float) $vat['final_price'],
                'base_price'       => (float) $vat['base_price'],
                'vat_enabled'      => (bool)  $vat['vat_enabled'],
                'vat_rate'         => (float) $vat['vat_rate'],
                'vat_amount'       => (float) $vat['vat_amount'],
                'final_price'      => (float) $vat['final_price'],
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
            $this->throwValidation(
                ['startTime' => __('validation.booking.root_start_must_match', ['time' => $min])],
                'validation.failed'
            );
        }

        if ($rootEnd && $rootEnd !== $max) {
            $this->throwValidation(
                ['endTime' => __('validation.booking.root_end_must_match', ['time' => $max])],
                'validation.failed'
            );
        }
    }

    protected function resolveServiceable(string $type, int $id): Model
    {
        $serviceable = match ($type) {
            'SubService', 'subservice' => $this->subServiceRepository->find($id),
            'SubServiceItem', 'item'   => $this->subServiceItemRepository->find($id),
            default => null,
        };

        if (!$serviceable) {
            throw new HttpResponseException(
                ApiResponse::error(
                    ['serviceType' => __('validation.booking.unknown_service_type')],
                    __('validation.failed'),
                    422
                )
            );
        }

        return $serviceable;
    }

    protected function attachServiceToBookingWithSegment(Booking $booking, array $seg, int $defaultSort): void
    {
        $booking->services()->create([
            'master_id'        => $seg['master_id'],
            'is_any_master'    => (bool)($seg['is_any_master'] ?? false),
            'bookable_id'      => $seg['bookable_id'],
            'bookable_type'    => $seg['bookable_type'],
            'price'            => $seg['price'],
            'base_price'       => $seg['base_price'] ?? $seg['price'],
            'vat_enabled'      => (bool)($seg['vat_enabled'] ?? false),
            'vat_rate'         => (float)($seg['vat_rate'] ?? (float) config('vat.rate', 0.05)),
            'vat_amount'       => (float)($seg['vat_amount'] ?? 0),
            'final_price'      => $seg['final_price'] ?? $seg['price'],
            'duration_minutes' => $seg['duration_minutes'],
            'sort_order'       => $seg['sort_order'] ?? $defaultSort,
            'date'             => $seg['date'],
            'timezone'         => $seg['timezone'],
            'start_time'       => $seg['start_time'],
            'end_time'         => $seg['end_time'],
        ]);
    }

    protected function normalizeServicesForPricing(array $services): array
    {
        return collect($services)->values()->map(function ($s) {
            return [
                'price' => (float) ($s['price'] ?? 0),
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

        if ($discountType === 'none' || !$discountValue) {
            $autoDiscount = $this->calculateAutomaticDiscount();
            if ($autoDiscount['discount_percent'] > 0) {
                $discountType = 'percent';
                $discountValue = $autoDiscount['discount_percent'];
                $discountLabel = $autoDiscount['discount_label'];
            }
        }

        $discountAmount = $this->calculateDiscountAmount(
            totalPrice: $totalPrice,
            discountType: $discountType,
            discountValue: $discountValue !== null ? (float)$discountValue : null
        );

        $finalPrice = max($totalPrice - $discountAmount, 0);

        $paymentMode = $data['payment_mode'] ?? $data['paymentMode'] ?? 'pay_later';

        $paymentStatus = 'unpaid';

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

    protected function calculateAutomaticDiscount(): array
    {
        $user = auth()->user();
        if (!$user || !$user->id) {
            return [
                'discount_percent' => 0,
                'discount_label' => null,
            ];
        }

        $visitCount = Booking::where('user_id', $user->id)
            ->where('type', 'booking')
            ->where('status', '!=', 'cancelled')
            ->where('payment_status', 'paid')
            ->count();

        $discountPercent = 0;
        $discountLabel = null;

        if ($visitCount >= 50) {
            $discountPercent = 20;
            $discountLabel = 'Loyalty Discount (50+ visits)';
        } elseif ($visitCount >= 25) {
            $discountPercent = 15;
            $discountLabel = 'Loyalty Discount (25+ visits)';
        } elseif ($visitCount >= 11) {
            $discountPercent = 10;
            $discountLabel = 'Loyalty Discount (11+ visits)';
        }

        return [
            'discount_percent' => $discountPercent,
            'discount_label' => $discountLabel,
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
            'break_end'   => $row->break_end_time   ? substr((string) $row->break_end_time  , 0, 5) : null,
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

    public function cancelBooking(Booking $booking, array $data = []): Booking
    {
        $user = auth()->user();

        if (($booking->type ?? 'booking') !== 'booking') {
            $this->throwValidation([], 'messages.booking.only_bookings_can_be_cancelled');
        }

        if ($booking->status === 'cancelled') {
            return $booking;
        }

        if ($booking->status === 'completed') {
            $this->throwValidation([], 'messages.booking.completed_cannot_be_cancelled');
        }

        $isAdmin = $user?->isAdmin() ?? false;
        if (! $isAdmin) {
            if (! $user) {
                $this->throwError('messages.auth.unauthorized', 401);
            }

            if ((int)$booking->user_id !== (int)$user->id) {
                $this->throwError('messages.booking.cancel_only_own', 403);
            }
        }

        $canRefund = false;
        if ($booking->payment_status === 'paid') {
            $booking->loadMissing('services');
            if ($booking->services->isNotEmpty()) {
                $firstService = $booking->services->sortBy('start_time')->first();
                $date = $booking->date instanceof \Carbon\Carbon ? $booking->date->format('Y-m-d') : (string) $booking->date;
                $startTime = $firstService->start_time ?? $booking->start_time;
                $timezone = $firstService->timezone ?? $booking->timezone ?? 'UTC';
                
                $timeStr = (string) $startTime;
                if (strlen($timeStr) === 5) {
                    $timeStr .= ':00';
                }
                
                $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$timeStr}", $timezone);
                $hoursUntilAppointment = now($timezone)->diffInHours($appointmentDateTime, false);
                
                $canRefund = $hoursUntilAppointment >= 24;
            } else {
                $date = $booking->date instanceof \Carbon\Carbon ? $booking->date->format('Y-m-d') : (string) $booking->date;
                $startTime = $booking->start_time;
                $timezone = $booking->timezone ?? 'UTC';
                
                $timeStr = (string) $startTime;
                if (strlen($timeStr) === 5) {
                    $timeStr .= ':00';
                }
                
                $appointmentDateTime = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$timeStr}", $timezone);
                $hoursUntilAppointment = now($timezone)->diffInHours($appointmentDateTime, false);
                
                $canRefund = $hoursUntilAppointment >= 24;
            }
        }

        $order = $booking->order?->load('latestPayment');
        if ($booking->payment_status === 'paid' && $order && $canRefund) {
            $this->paymentService->refundOrderPayment($order, [
                'booking_id' => (string) $booking->id,
                'reason' => 'booking_cancelled',
            ]);
            $this->orderService->refund($order, ['reason' => 'booking_cancelled']);
            $booking->payment_status = 'refunded';
        } elseif ($booking->payment_status === 'paid' && !$canRefund) {
            $booking->payment_status = 'unpaid';
        }

        $booking->update([
            'status' => 'cancelled',
            'payment_status' => $booking->payment_status,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $user?->id,
            'cancel_reason' => $data['reason'] ?? null,
        ]);

        return $booking->load(['services.bookable', 'services.master', 'master', 'cancelledBy'])->refresh();
    }

    public function markBookingPaid(Booking $booking): Booking
    {
        if ($booking->type !== 'booking') {
            $this->throwValidation([], 'messages.booking.only_bookings_can_be_marked_paid');
        }

        if ($booking->status === 'cancelled') {
            $this->throwValidation([], 'messages.booking.cancelled_cannot_be_marked_paid');
        }

        $booking->load('order.latestPayment');

        $booking->update([
            'status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        if ($booking->order) {
            $this->orderService->markPaid($booking->order, ['marked_paid_manually' => true]);

            if ($booking->order->latestPayment) {
                $payment = $booking->order->latestPayment;
                $this->paymentRepository->update($payment, [
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }
        }

        return $booking->fresh()->load(['services.bookable', 'services.master', 'master', 'order.latestPayment']);
    }

    protected function validateSegmentsBasic(array $segments, string $tz): void
    {
        $now = Carbon::now($tz);

        foreach ($segments as $seg) {
            $date = $seg['date'];

            $hours = $this->getWorkingHours($date, $tz);
            if ($hours['is_closed']) {
                $this->throwValidation(
                    ['date' => __('validation.booking.closed_day')],
                    'validation.failed'
                );
            }

            $masterId = (int)($seg['master_id'] ?? 0);
            if ($masterId && $this->isMasterOffOnDate($masterId, $date, $tz)) {
                $this->throwValidation(
                    ['masterId' => __('validation.booking.master_day_off')],
                    'validation.failed'
                );
            }

            $start = $this->parseTimeToCarbon($date, $seg['start_time'], $tz);
            $end   = $this->parseTimeToCarbon($date, $seg['end_time'], $tz);

            if ($start->lte($now)) {
                $this->throwValidation(
                    ['startTime' => __('validation.booking.start_must_be_future')],
                    'validation.failed'
                );
            }

            $dayStart = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$hours['start']}", $tz);
            $dayEnd   = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$hours['end']}", $tz);

            if ($start->lt($dayStart) || $end->gt($dayEnd)) {
                $this->throwValidation(
                    ['workingHours' => __('validation.booking.within_working_hours', [
                        'start' => $hours['start'],
                        'end' => $hours['end'],
                    ])],
                    'validation.failed'
                );
            }

            if (($start->minute % 5) !== 0 || ($end->minute % 5) !== 0) {
                $this->throwValidation(
                    ['grid' => __('validation.booking.time_grid_5min')],
                    'validation.failed'
                );
            }

            if (!empty($hours['break_start']) && !empty($hours['break_end'])) {
                $breakStart = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$hours['break_start']}", $tz);
                $breakEnd   = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$hours['break_end']}", $tz);

                if ($start->lt($breakEnd) && $end->gt($breakStart)) {
                    $this->throwValidation(
                        ['breakTime' => __('validation.booking.overlaps_break', [
                            'start' => $hours['break_start'],
                            'end' => $hours['break_end'],
                        ])],
                        'validation.failed'
                    );
                }
            }
        }
    }

    protected function assertNoDuplicateSegments(array $segments): void
    {
        $count = count($segments);

        for ($i = 0; $i < $count; $i++) {
            $a = $segments[$i];
            $masterA = (int)($a['master_id'] ?? 0);
            if (!$masterA) {
                continue;
            }

            $aStart = $this->parseTimeToCarbon($a['date'], (string)$a['start_time'], (string)$a['timezone']);
            $aEnd   = $this->parseTimeToCarbon($a['date'], (string)$a['end_time'], (string)$a['timezone']);

            for ($j = $i + 1; $j < $count; $j++) {
                $b = $segments[$j];
                $masterB = (int)($b['master_id'] ?? 0);
                if ($masterA !== $masterB) {
                    continue;
                }

                $bStart = $this->parseTimeToCarbon($b['date'], (string)$b['start_time'], (string)$b['timezone']);
                $bEnd   = $this->parseTimeToCarbon($b['date'], (string)$b['end_time'], (string)$b['timezone']);

                if ($aStart < $bEnd && $aEnd > $bStart) {
                    $this->throwValidation(
                        ['services' => __('validation.booking.slot_already_selected')],
                        'validation.failed'
                    );
                }
            }
        }
    }

    private function isMasterOffOnDate(int $masterId, string $date, string $tz): bool
    {
        $day = CarbonImmutable::createFromFormat('Y-m-d', $date, $tz);
        $isoDay = $day->dayOfWeekIso;

        return User::query()
            ->whereKey($masterId)
            ->masters()
            ->whereHas('weekends', fn ($q) => $q->where('day', $isoDay))
            ->exists();
    }

    protected function clearSelectionsAfterBooking(?int $userId, ?string $guestSessionId): void
    {
        if ($userId) {
            $this->bookingSelectionRepository->deleteByUserId($userId);
            return;
        }

        if ($guestSessionId) {
            $this->bookingSelectionRepository->deleteByGuestSession($guestSessionId);
        }
    }

    protected function throwValidation(array $errors, string $messageKey, array $replace = [], int $status = 422): void
    {
        throw new HttpResponseException(
            ApiResponse::error($errors, __($messageKey, $replace), $status)
        );
    }

    protected function throwError(string $messageKey, int $status, array $errors = [], array $replace = []): void
    {
        throw new HttpResponseException(
            ApiResponse::error($errors ?: null, __($messageKey, $replace), $status)
        );
    }

    public function sendBookingConfirmation(Booking $booking): void
    {
        $email = $booking->customer_email;

        if ($email) {
            Mail::to($email)->send(new BookingConfirmedMail($booking));
        }
    }

    public function sendBookingCancellation(Booking $booking): void
    {
        $email = $booking->customer_email;

        if ($email) {
            Mail::to($email)->send(new BookingCancelledMail($booking));
        }
    }
}
