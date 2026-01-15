<?php

namespace App\Repositories;

use App\Filters\BookingFilter;
use App\Models\Booking;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Carbon\Carbon;
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
        $query = Booking::with(['services.bookable', 'services.master', 'master', 'cancelledBy'])->orderBy('date', 'DESC')->orderBy('start_time');

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
        ?int $excludeBookingId = null,
        ?string $timezone = null
    ): bool {
        $tz = $timezone ?: 'UTC';
        $reqStart = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$startTime}", $tz);
        $reqEnd   = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$endTime}", $tz);

        $dateObj = Carbon::createFromFormat('Y-m-d', $date);
        $dateRange = [
            $dateObj->copy()->subDay()->toDateString(),
            $dateObj->toDateString(),
            $dateObj->copy()->addDay()->toDateString(),
        ];

        $breaks = Booking::query()
            ->where('master_id', $masterId)
            ->whereIn('date', $dateRange)
            ->where('status', '!=', 'cancelled')
            ->where('type', 'break')
            ->get(['date', 'start_time', 'end_time', 'timezone']);

        foreach ($breaks as $row) {
            $rowTz = $row->timezone ?: 'UTC';
            $rowDate = is_string($row->date) ? $row->date : $row->date->toDateString();
            $startStr = (string) $row->start_time;
            $endStr   = (string) $row->end_time;
            if (strlen($startStr) === 5) $startStr .= ':00';
            if (strlen($endStr) === 5)   $endStr   .= ':00';

            $start = Carbon::createFromFormat('Y-m-d H:i:s', "{$rowDate} {$startStr}", $rowTz)->setTimezone($tz);
            $end   = Carbon::createFromFormat('Y-m-d H:i:s', "{$rowDate} {$endStr}", $rowTz)->setTimezone($tz);

            if ($reqStart < $end && $reqEnd > $start) {
                return true;
            }
        }

        $bookings = Booking::query()
            ->where('master_id', $masterId)
            ->whereIn('date', $dateRange)
            ->where('status', '!=', 'cancelled')
            ->where('type', 'booking')
            ->when($excludeBookingId, function ($q) use ($excludeBookingId) {
                $q->where('id', '!=', $excludeBookingId);
            })
            ->get(['date', 'start_time', 'end_time', 'timezone']);

        foreach ($bookings as $row) {
            $rowTz = $row->timezone ?: 'UTC';
            $rowDate = is_string($row->date) ? $row->date : $row->date->toDateString();
            $startStr = (string) $row->start_time;
            $endStr   = (string) $row->end_time;
            if (strlen($startStr) === 5) $startStr .= ':00';
            if (strlen($endStr) === 5)   $endStr   .= ':00';

            $start = Carbon::createFromFormat('Y-m-d H:i:s', "{$rowDate} {$startStr}", $rowTz)->setTimezone($tz);
            $end   = Carbon::createFromFormat('Y-m-d H:i:s', "{$rowDate} {$endStr}", $rowTz)->setTimezone($tz);

            if ($reqStart < $end && $reqEnd > $start) {
                return true;
            }
        }

        $segments = DB::table('booking_services as bs')
            ->join('bookings as b', 'b.id', '=', 'bs.booking_id')
            ->where('bs.master_id', $masterId)
            ->whereIn('bs.date', $dateRange)
            ->where('b.status', '!=', 'cancelled')
            ->where('b.type', 'booking')
            ->when($excludeBookingId, function ($q) use ($excludeBookingId) {
                $q->where('b.id', '!=', $excludeBookingId);
            })
            ->get([
                'bs.date',
                'bs.start_time',
                'bs.end_time',
                'bs.timezone',
                'b.timezone as booking_timezone',
            ]);

        foreach ($segments as $row) {
            $rowTz = $row->timezone ?: ($row->booking_timezone ?: 'UTC');
            $rowDate = is_string($row->date) ? $row->date : (string) $row->date;
            $startStr = (string) $row->start_time;
            $endStr   = (string) $row->end_time;
            if (strlen($startStr) === 5) $startStr .= ':00';
            if (strlen($endStr) === 5)   $endStr   .= ':00';

            $start = Carbon::createFromFormat('Y-m-d H:i:s', "{$rowDate} {$startStr}", $rowTz)->setTimezone($tz);
            $end   = Carbon::createFromFormat('Y-m-d H:i:s', "{$rowDate} {$endStr}", $rowTz)->setTimezone($tz);

            if ($reqStart < $end && $reqEnd > $start) {
                return true;
            }
        }

        return false;
    }
}
