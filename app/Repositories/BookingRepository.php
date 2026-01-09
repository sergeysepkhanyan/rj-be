<?php

namespace App\Repositories;

use App\Filters\BookingFilter;
use App\Models\Booking;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    public function delete(Booking $booking): ?bool
    {
        return $booking->delete();
    }

    public function paginateWithFilter(?BookingFilter $filter = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $query = Booking::with('master')->orderBy('date')->orderBy('start_time');

        if ($filter) {
            $query = $filter->apply($query);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function getBusyForMasterOnDate(int $masterId, string $date): Collection
    {
        $breaks = Booking::query()
            ->where('master_id', $masterId)
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->where('type', 'break')
            ->get([
                'date',
                'start_time',
                'end_time',
                'type',
                'timezone',
            ]);


        $segments = DB::table('booking_services as bs')
            ->join('bookings as b', 'b.id', '=', 'bs.booking_id')
            ->where('bs.master_id', $masterId)
            ->whereDate('bs.date', $date)
            ->where('b.status', '!=', 'cancelled')
            ->where('b.type', 'booking')
            ->get([
                DB::raw('bs.date as date'),
                DB::raw('bs.start_time as start_time'),
                DB::raw('bs.end_time as end_time'),
                DB::raw("'booking' as type"),
                DB::raw('COALESCE(bs.timezone, b.timezone) as timezone'),
            ]);

        return collect()
            ->merge($breaks)
            ->merge($segments);
    }

    public function hasOverlap(
        int $masterId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null
    ): bool {
        $breakOverlap = Booking::query()
            ->where('master_id', $masterId)
            ->whereDate('date', $date)
            ->where('status', '!=', 'cancelled')
            ->where('type', 'break')
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                    ->where('end_time',   '>', $startTime);
            })
            ->exists();

        if ($breakOverlap) {
            return true;
        }

        return DB::table('booking_services as bs')
            ->join('bookings as b', 'b.id', '=', 'bs.booking_id')
            ->where('bs.master_id', $masterId)
            ->whereDate('bs.date', $date)
            ->where('b.status', '!=', 'cancelled')
            ->where('b.type', 'booking')
            ->when($excludeBookingId, function ($q) use ($excludeBookingId) {
                $q->where('b.id', '!=', $excludeBookingId);
            })
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('bs.start_time', '<', $endTime)
                    ->where('bs.end_time',   '>', $startTime);
            })
            ->exists();
    }
}
