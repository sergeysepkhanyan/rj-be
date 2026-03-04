<?php

namespace App\Repositories\Interfaces;

use App\Models\Booking;
use Illuminate\Support\Collection;

interface BookingRepositoryInterface
{
    public function all();
    public function find($id);
    public function create(array $data);
    public function update(Booking $booking, array $data): Booking;
    public function delete(Booking $booking);

    /**
     * Check if a specific service is already booked at overlapping times
     */
    public function hasServiceOverlap(
        string $bookableType,
        int $bookableId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null,
        ?string $timezone = null
    ): bool;

    /**
     * Get busy time slots for a specific service on a date
     */
    public function getBusyForServiceOnDate(
        string $bookableType,
        int $bookableId,
        string $date
    ): Collection;
}
