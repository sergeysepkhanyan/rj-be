<?php

namespace App\Repositories\Interfaces;

use App\Models\Booking;
use App\Models\UserBooking;

interface BookingRepositoryInterface
{
    public function all();
    public function find($id);
    public function create(array $data);
    public function update(Booking $userBooking, array $data): Booking;
    public function delete($id);
}
