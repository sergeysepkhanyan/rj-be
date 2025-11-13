<?php

namespace App\Repositories;

use App\Models\UserBooking;
use App\Repositories\Interfaces\UserBookingRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class UserUserBookingRepository implements UserBookingRepositoryInterface
{
    public function all()
    {
        return UserBooking::all();
    }

    public function find($id)
    {
        return UserBooking::findOrFail($id);
    }

    public function create(array $data)
    {
        return UserBooking::create($data);
    }

    public function update(UserBooking $userBooking, array $data): UserBooking
    {
        $userBooking->update($data);
        return $userBooking;
    }

    public function delete($id)
    {
        $userBooking = UserBooking::findOrFail($id);
        return $userBooking->delete();
    }

    public function paginateWithSearch(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = UserBooking::query();
        return $query->paginate($perPage);
    }
}
