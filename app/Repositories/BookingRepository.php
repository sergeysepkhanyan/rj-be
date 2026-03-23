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
        // Auto-set active_slot_key for non-cancelled bookings to enforce uniqueness
        if (($data['status'] ?? '') !== 'cancelled' && !empty($data['master_id'])) {
            $data['active_slot_key'] = $this->buildSlotKey($data);
        }

        return Booking::create($data);
    }

    public function update(Booking $booking, array $data): Booking
    {
        // Update active_slot_key when status changes or time/master fields change
        $merged = array_merge($booking->toArray(), $data);
        $status = $data['status'] ?? $booking->status;

        if ($status === 'cancelled') {
            $data['active_slot_key'] = null;
        } elseif ($booking->master_id || !empty($data['master_id'])) {
            $data['active_slot_key'] = $this->buildSlotKey($merged);
        }

        $booking->update($data);
        return $booking;
    }

    protected function buildSlotKey(array $data): ?string
    {
        $masterId = $data['master_id'] ?? null;
        $date = $data['date'] ?? null;
        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;

        if (!$masterId || !$date || !$startTime || !$endTime) {
            return null;
        }

        // Normalize date to Y-m-d format
        if ($date instanceof \DateTimeInterface) {
            $date = $date->format('Y-m-d');
        } elseif (is_string($date) && strlen($date) > 10) {
            $date = substr($date, 0, 10);
        }

        return "{$masterId}_{$date}_" . substr((string) $startTime, 0, 5) . "_" . substr((string) $endTime, 0, 5);
    }

    public function delete(Booking $booking): ?bool
    {
        return $booking->delete();
    }

    public function paginateWithFilter(?BookingFilter $filter = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $query = Booking::with(['services.bookable', 'services.master', 'master', 'cancelledBy', 'bookingReferral.referrer'])
            ->orderBy('date', 'DESC')
            ->orderBy('start_time');

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
        $startTimeStr = (string) $startTime;
        $endTimeStr = (string) $endTime;
        if (strlen($startTimeStr) === 5) $startTimeStr .= ':00';
        if (strlen($endTimeStr) === 5) $endTimeStr .= ':00';

        $reqStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$startTimeStr}", $tz);
        $reqEnd   = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$endTimeStr}", $tz);

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

    /**
     * Check if a specific service is already booked at overlapping times.
     *
     * @param bool $withLock When true, acquires a pessimistic FOR UPDATE lock on
     *                       relevant booking rows before checking overlap. Must be
     *                       called inside a DB::transaction().
     */
    public function hasServiceOverlap(
        string $bookableType,
        int $bookableId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null,
        ?string $timezone = null,
        bool $withLock = false
    ): bool {
        $tz = $timezone ?: 'UTC';
        $startTimeStr = (string) $startTime;
        $endTimeStr = (string) $endTime;
        if (strlen($startTimeStr) === 5) $startTimeStr .= ':00';
        if (strlen($endTimeStr) === 5) $endTimeStr .= ':00';

        $reqStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$startTimeStr}", $tz);
        $reqEnd   = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$endTimeStr}", $tz);

        $dateObj = Carbon::createFromFormat('Y-m-d', $date);
        $dateRange = [
            $dateObj->copy()->subDay()->toDateString(),
            $dateObj->toDateString(),
            $dateObj->copy()->addDay()->toDateString(),
        ];

        // Acquire a pessimistic lock on the relevant booking rows so concurrent
        // transactions block until this one commits/rolls back.
        if ($withLock) {
            Booking::query()
                ->whereIn('date', $dateRange)
                ->where('status', '!=', 'cancelled')
                ->where('type', 'booking')
                ->lockForUpdate()
                ->get(['id']);
        }

        // Check booking_services for the same service at overlapping times
        $segments = DB::table('booking_services as bs')
            ->join('bookings as b', 'b.id', '=', 'bs.booking_id')
            ->where('bs.bookable_type', $bookableType)
            ->where('bs.bookable_id', $bookableId)
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

    /**
     * Get busy time slots for a specific service on a date
     */
    public function getBusyForServiceOnDate(
        string $bookableType,
        int $bookableId,
        string $date
    ): Collection {
        return DB::table('booking_services as bs')
            ->join('bookings as b', 'b.id', '=', 'bs.booking_id')
            ->where('bs.bookable_type', $bookableType)
            ->where('bs.bookable_id', $bookableId)
            ->whereDate('bs.date', $date)
            ->where('b.status', '!=', 'cancelled')
            ->where('b.type', 'booking')
            ->get([
                DB::raw('bs.date as date'),
                DB::raw('bs.start_time as start_time'),
                DB::raw('bs.end_time as end_time'),
                DB::raw("'service_booking' as type"),
                DB::raw('COALESCE(bs.timezone, b.timezone) as timezone'),
            ]);
    }
}
