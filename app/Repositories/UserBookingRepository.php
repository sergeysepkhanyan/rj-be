<?php

namespace App\Repositories;

use App\Filters\BookingFilter;
use App\Models\UserBooking;
use App\Repositories\Interfaces\UserBookingRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class UserBookingRepository implements UserBookingRepositoryInterface
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

    public function paginateWithFilter(?BookingFilter $filter = null, int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $query = UserBooking::with('master')->orderBy('date')->orderBy('time');

        if ($filter) {
            $query = $filter->apply($query);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
