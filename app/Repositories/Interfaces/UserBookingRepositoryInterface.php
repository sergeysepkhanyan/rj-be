<?php

namespace App\Repositories\Interfaces;

use App\Models\UserBooking;

interface UserBookingRepositoryInterface
{
    public function all();
    public function find($id);
    public function create(array $data);
    public function update(UserBooking $userBooking, array $data): UserBooking;
    public function delete($id);
}
