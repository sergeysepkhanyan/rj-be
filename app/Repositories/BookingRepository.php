<?php

namespace App\Repositories;

use App\Filters\BookingFilter;
use App\Models\Booking;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BookingRepository implements BookingRepositoryInterface
{
    public function all()
    {
        return Booking::all();
    }

    public function find($id)
    {
        return Booking::findOrFail($id);
    }

    public function create(array $data)
    {
        return Booking::create($data);
    }

    public function update(Booking $booking, array $data): Booking
    {
        $booking->update($data);
        return $booking;
    }

    public function delete($id)
    {
        $booking = Booking::findOrFail($id);
        return $booking->delete();
    }

    public function paginateWithFilter(?BookingFilter $filter = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $query = Booking::with('master')->orderBy('date')->orderBy('time');

        if ($filter) {
            $query = $filter->apply($query);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function getBusyForMasterOnDate(int $masterId, string $date): Collection
    {
        return Booking::query()
            ->where('master_id', $masterId)
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->whereIn('type', ['booking', 'break'])
            ->get(['date', 'start_time', 'end_time', 'type']);
    }

    public function hasOverlap(
        int $masterId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null
    ): bool {
        return Booking::query()
            ->where('master_id', $masterId)
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->when($excludeBookingId, function ($q) use ($excludeBookingId) {
                $q->where('id', '!=', $excludeBookingId);
            })
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                    ->where('end_time',   '>', $startTime);
            })
            ->exists();
    }
}
