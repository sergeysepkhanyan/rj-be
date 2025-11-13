<?php

namespace App\Services;

use App\Models\UserBooking;
use App\Repositories\Interfaces\ServiceRepositoryInterface;
use App\Repositories\Interfaces\UserBookingRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

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

    public function getPaginatedBookings(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->userBookingRepository->paginateWithSearch($search, $perPage);
    }

    public function createBreak(array $data): UserBooking
    {

        // Convert to Carbon for overlap check
        $start = Carbon::parse("{$data['date']} {$data['time']}");
        $end = $start->copy()->addMinutes($data['duration']);

        // Check overlap with existing bookings
        $hasOverlap = UserBooking::where('master_id', $data['master_id'])
            ->where('date', $data['date'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('time', [$start->format('H:i'), $end->format('H:i')])
                    ->orWhereRaw('? BETWEEN time AND ADDTIME(time, SEC_TO_TIME(duration * 60))', [$start->format('H:i')]);
            })
            ->exists();



        $data['type'] = 'break';
        $data['client_id'] = null;
        $data['payment_status'] = null;

        return $this->userBookingRepository->create($data);
    }
}
