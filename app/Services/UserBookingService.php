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

    public function createBooking(array $data)
    {
        return $this->createBooking()->create($data);
    }

    public function updateBooking($id, array $data): UserBooking
    {
        return $this->userBookingRepository->update($id, $data);
    }

    public function deleteService($id)
    {
        return $this->userBookingRepository->delete($id);
    }

    public function getPaginatedBookings(?BookingFilter $filter = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->userBookingRepository->paginateWithFilter($filter, $perPage);
    }

    public function createBreak(array $data): UserBooking | null
    {
        $start = Carbon::parse("{$data['date']} {$data['start_time']}");
        $end = Carbon::parse("{$data['date']} {$data['end_time']}");
        $duration = $start->diffInMinutes($end);

        $hasOverlap = UserBooking::where('master_id', $data['master_id'])
            ->where('date', $data['date'])
            ->where(function ($query) use ($start, $end) {
                $query->where('time', '<', $end->format('H:i'))
                    ->whereRaw('ADDTIME(time, SEC_TO_TIME(duration * 60)) > ?', [$start->format('H:i')]);
            })
            ->exists();

        if ($hasOverlap) {
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
}
