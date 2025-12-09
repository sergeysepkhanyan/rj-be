<?php

namespace App\Services;

use App\Filters\BookingFilter;
use App\Models\Booking;
use App\Models\SubService;
use App\Models\SubServiceItem;
use App\Models\UserBooking;
use App\Repositories\Interfaces\ServiceRepositoryInterface;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use App\Repositories\Interfaces\SubServiceItemRepositoryInterface;
use App\Repositories\Interfaces\SubServiceRepositoryInterface;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookingService
{
    protected BookingRepositoryInterface $bookingRepository;
    protected SubServiceRepositoryInterface $subServiceRepository;
    protected SubServiceItemRepositoryInterface $subServiceItemRepository;

    public function __construct(
        BookingRepositoryInterface $bookingRepository,
        SubServiceRepositoryInterface $subServiceRepository,
        SubServiceItemRepositoryInterface $subServiceItemRepository
    )
    {
        $this->bookingRepository = $bookingRepository;
        $this->subServiceRepository = $subServiceRepository;
        $this->subServiceItemRepository = $subServiceItemRepository;
    }

    public function getAllBooking()
    {
        return $this->bookingRepository->all();
    }

    public function getBookingById($id)
    {
        return $this->bookingRepository->find($id);
    }

    public function updateBooking($id, array $data): Booking
    {
        return $this->bookingRepository->update($id, $data);
    }

    public function deleteService($id)
    {
        return $this->bookingRepository->delete($id);
    }

    public function getPaginatedBookings(?BookingFilter $filter = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return $this->bookingRepository->paginateWithFilter($filter, $perPage, $page);
    }

    /**
     * Create a break
     */
    public function createBreak(array $data): Booking | null
    {
        $start = Carbon::parse("{$data['date']} {$data['start_time']}");
        $end = Carbon::parse("{$data['date']} {$data['end_time']}");
        $duration = $start->diffInMinutes($end);

        $hasOverlap = $this->bookingRepository->hasOverlap(
            masterId:  $data['master_id'],
            date:      $data['date'],
            startTime: $data['start_time'],
            endTime:   $data['end_time'],
        );

        if ($hasOverlap) {
            throw new HttpResponseException(
                ApiResponse::error([], 'Master is not available in selected time range.', 422)
            );
        }

        $breakData = [
            'client_id' => null,
            'master_id' => $data['master_id'],
            'discount' => null,
            'payment_amount' => null,
            'payment_currency' => null,
            'sub_service_id' => null,
            'date' => $data['date'],
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'name' => 'Break',
            'email' => null,
            'mobile' => null,
            'notes' => $data['notes'] ?? null,
            'type' => 'break',
            'duration' => $duration,
        ];

        return $this->bookingRepository->create($breakData);
    }

    public function hasOverlap(int $masterId, string $date, string $startTime, string $endTime): bool
    {
        return Booking::where('master_id', $masterId)
            ->where('date', $date)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereTime('time', '<', $endTime)
                    ->whereTime('end_time', '>', $startTime);
            })
            ->exists();
    }

    public function getAvailableSlots(array $data): array
    {
        $masterId         = $data['master_id'];
        $date             = $data['date'];
        $subserviceId     = $data['sub_service_id'] ?? null;
        $subserviceItemId = $data['sub_service_item_id'] ?? null;
        $durationMinutes = $this->resolveDurationMinutes($subserviceId, $subserviceItemId);

//        $workStart = $data['work_start'] ?? '09:00';
//        $workEnd   = $data['work_end'] ?? '18:00';
        $workStart = '10:00';
        $workEnd   = '19:00';

        $busy = $this->bookingRepository->getBusyForMasterOnDate($masterId, $date);

        return $this->buildSlots($date, $workStart, $workEnd, $busy, $durationMinutes);
    }

    protected function resolveDurationMinutes(?int $subserviceId, ?int $subserviceItemId): int
    {
        if ($subserviceItemId) {
            $item = $this->subServiceItemRepository->find($subserviceItemId);
            return (int) $item->duration;
        }

        if ($subserviceId) {
            $sub = $this->subServiceRepository->find($subserviceId);
            return (int) $sub->duration;
        }

        return 30;
    }

    /**
     * Build free slots between workStart/workEnd skipping busy intervals.
     *
     * @param  string     $date           Y-m-d
     * @param  string     $workStart      "H:i"
     * @param  string     $workEnd        "H:i"
     * @param  Collection $busy           collection of bookings/breaks with date, start_time, end_time
     * @param  int        $durationMinutes
     * @return array<array{start:string,end:string}>
     */
    protected function buildSlots(
        string $date,
        string $workStart,
        string $workEnd,
        Collection $busy,
        int $durationMinutes
    ): array
    {
        $slots = [];

        $dayStart = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$workStart}");
        $dayEnd   = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$workEnd}");

        $busyIntervals = $busy->map(function ($row) use ($date) {
            return [
                'start' => Carbon::createFromFormat('Y-m-d H:i', $row->date . ' ' . $row->start_time),
                'end'   => Carbon::createFromFormat('Y-m-d H:i', $row->date . ' ' . $row->end_time),
            ];
        });

        $cursor = $dayStart->copy();

        while ($cursor->copy()->addMinutes($durationMinutes) <= $dayEnd) {
            $slotStart = $cursor->copy();
            $slotEnd   = $cursor->copy()->addMinutes($durationMinutes);

            $overlaps = $busyIntervals->contains(function ($interval) use ($slotStart, $slotEnd) {
                return $slotStart < $interval['end'] && $slotEnd > $interval['start'];
            });

            if (! $overlaps) {
                $slots[] = [
                    'start' => $slotStart->format('H:i'),
                    'end'   => $slotEnd->format('H:i'),
                ];
            }

            $cursor->addMinutes($durationMinutes);
        }

        return $slots;
    }

    public function createBooking(array $data): Booking
    {
        $hasOverlap = $this->bookingRepository->hasOverlap(
            masterId:  $data['master_id'],
            date:      $data['date'],
            startTime: $data['start_time'],
            endTime:   $data['end_time'],
        );

        if ($hasOverlap) {
            throw new HttpResponseException(
                ApiResponse::error([], 'Master is not available in selected time range.', 422)
            );
        }

        $services = collect($data['services'] ?? []);
        $totalPrice = $services->sum(fn ($s) => $s['price'] ?? 0);

        $discountType  = $data['discount_type']  ?? null;
        $discountValue = $data['discount_value'] ?? null;
        $discountLabel = $data['discount_label'] ?? null;

        $discountAmount = $this->calculateDiscountAmount(
            totalPrice:    $totalPrice,
            discountType:  $discountType,
            discountValue: $discountValue
        );

        $finalPrice = max($totalPrice - $discountAmount, 0);

        $paymentMode = $data['payment_mode'] ?? 'pay_later';

        $paymentStatus = match ($paymentMode) {
            'pay_now'   => 'paid',
            default     => 'unpaid',
        };

        return DB::transaction(function () use ($data, $totalPrice, $finalPrice, $discountType, $discountValue, $discountLabel, $paymentMode, $paymentStatus) {
            $user = auth()->user();

            $bookingData = [
                'user_id'        => $user->id,
                'master_id'      => $data['master_id'],
                'type'           => 'booking',
                'date'           => $data['date'],
                'start_time'     => $data['start_time'],
                'end_time'       => $data['end_time'],
                'duration'       => $this->calculateDuration($data['start_time'], $data['end_time']),
                'duration_unit'  => 'minutes',
                'price'          => $totalPrice,
                'discount_type'  => $discountType,
                'discount_value' => $discountValue,
                'discount_label' => $discountLabel,
                'final_price'    => $finalPrice,
                'payment_mode'   => $paymentMode,
                'payment_status' => $paymentStatus,
                'status'         => 'pending',
                'customer_name'  => $data['customer_name']  ?? ($user->name   ?? null),
                'customer_phone' => $data['customer_phone'] ?? ($user->mobile ?? null),
                'customer_email' => $data['customer_email'] ?? ($user->email  ?? null),
                'notes'          => $data['notes'] ?? null,
            ];

            /** @var Booking $booking */
            $booking = $this->bookingRepository->create($bookingData);

            foreach ($data['services'] as $serviceData) {
                $this->attachServiceToBooking($booking, $serviceData);
            }

            return $booking->load('services.bookable');
        });
    }

    /**
     * @param float      $totalPrice
     * @param string|null $discountType  'percent' or 'fixed'
     * @param float|int|null $discountValue
     * @return float
     */
    protected function calculateDiscountAmount(
        float $totalPrice,
        ?string $discountType,
        float|int|null $discountValue
    ): float {
        if (!$discountType || $discountValue === null) {
            return 0.0;
        }

        $discountValue = (float) $discountValue;
        $discountAmount = 0.0;

        if ($discountType === 'percent') {
            $discountAmount = $totalPrice * ($discountValue / 100);
        } elseif ($discountType === 'fixed') {
            $discountAmount = $discountValue;
        }

        if ($discountAmount > $totalPrice) {
            $discountAmount = $totalPrice;
        }

        return round($discountAmount, 2);
    }

    protected function attachServiceToBooking(Booking $booking, array $serviceData): void
    {
        $type = $serviceData['service_type'];
        $id   = $serviceData['service_id'];

        $serviceableClass = match ($type) {
            'subservice' => SubService::class,
            'item'       => SubServiceItem::class,
            default      => null,
        };

        if (! $serviceableClass) {
            throw new \RuntimeException("Unknown service_type: $type");
        }

        $serviceable = $serviceableClass::findOrFail($serviceData['service_id']);

        $booking->services()->create([
            'bookable_id'   => $serviceable->id,
            'bookable_type' => $serviceableClass,
            'price'         => $serviceData['price'],
            'duration_minutes' => $serviceable->duration ?? 0,
        ]);

    }

    protected function calculateDuration(string $startTime, string $endTime): int
    {
        [$sh, $sm] = explode(':', $startTime);
        [$eh, $em] = explode(':', $endTime);

        $startMinutes = ((int) $sh) * 60 + (int) $sm;
        $endMinutes   = ((int) $eh) * 60 + (int) $em;

        return max(0, $endMinutes - $startMinutes);
    }
}
