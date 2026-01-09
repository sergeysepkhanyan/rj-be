<?php

namespace App\Repositories\Interfaces;

use App\Models\Booking;

interface BookingRepositoryInterface
{
    public function all();
    public function find($id);
    public function create(array $data);
    public function update(Booking $booking, array $data): Booking;
    public function delete(Booking $booking);
}
