<?php

namespace App\Services;

use App\Filters\BookingFilter;
use App\Models\UserBooking;
use App\Repositories\Interfaces\ServiceRepositoryInterface;
use App\Repositories\Interfaces\UserBookingRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class UserBookingService
{
    protected UserBookingRepositoryInterface $userBookingRepository;

    public function __construct(UserBookingRepositoryInterface $userBookingRepository)
    {
        $this->userBookingRepository = $userBookingRepository;
    }

    public function getAllBooking()
    {
        return $this->userBookingRepository->all();
    }

    public function getBookingById($id)
    {
        return $this->userBookingRepository->find($id);
    }

    /**
     * Create an appointment
     */
    public function createBooking(array $data): ?UserBooking
    {
        $start = Carbon::parse("{$data['date']} {$data['time']}");
        $end = Carbon::parse("{$data['date']} {$data['end_time']}");

        if ($this->hasOverlap($data['master_id'], $data['date'], $start->format('H:i'), $end->format('H:i'))) {
            return null;
        }

        $duration = $start->diffInMinutes($end);

        $bookingData = [
            'client_id' => $data['client_id'] ?? null,
            'master_id' => $data['master_id'],
            'payment_type' => $data['payment_type'],
            'discount_type' => $data['discount_type'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'discount' => $data['discount'] ?? 0,
            'payment_amount' => $data['payment_amount'],
            'payment_currency' => $data['payment_currency'],
            'payment_status' => $data['payment_status'],
            'sub_service_id' => $data['sub_service_id'],
            'date' => $data['date'],
            'time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'mobile' => $data['mobile'],
            'notes' => $data['notes'] ?? null,
            'type' => $data['type'] ?? 'appointment',
            'duration' => $duration,
        ];

        return $this->userBookingRepository->create($bookingData);
    }

    public function updateBooking($id, array $data): UserBooking
    {
        return $this->userBookingRepository->update($id, $data);
    }

    public function deleteService($id)
    {
        return $this->userBookingRepository->delete($id);
    }

    public function getPaginatedBookings(?BookingFilter $filter = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return $this->userBookingRepository->paginateWithFilter($filter, $perPage, $page);
    }

    /**
     * Create a break
     */
    public function createBreak(array $data): UserBooking | null
    {
        $start = Carbon::parse("{$data['date']} {$data['start_time']}");
        $end = Carbon::parse("{$data['date']} {$data['end_time']}");
        $duration = $start->diffInMinutes($end);

        if ($this->hasOverlap($data['master_id'], $data['date'], $start->format('H:i'), $end->format('H:i'))) {
            return null;
        }

        $breakData = [
            'client_id' => null,
            'master_id' => $data['master_id'],
            'discount_type' => null,
            'discount_amount' => null,
            'discount' => null,
            'payment_amount' => null,
            'payment_currency' => null,
            'payment_status' => null,
            'sub_service_id' => null,
            'date' => $data['date'],
            'time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'name' => 'Break',
            'email' => null,
            'mobile' => null,
            'notes' => $data['notes'] ?? null,
            'type' => 'break',
            'duration' => $duration,
        ];

        return $this->userBookingRepository->create($breakData);
    }

    public function hasOverlap(int $masterId, string $date, string $startTime, string $endTime): bool
    {
        return UserBooking::where('master_id', $masterId)
            ->where('date', $date)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereTime('time', '<', $endTime)
                    ->whereTime('end_time', '>', $startTime);
            })
            ->exists();
    }
}
