<?php

namespace App\Repositories;

use App\Models\Address;
use App\Repositories\Interfaces\AddressRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class AddressRepository implements AddressRepositoryInterface
{
    public function all()
    {
        return Address::all();
    }

    public function find($id)
    {
        return Address::findOrFail($id);
    }

    public function create(array $data)
    {
        return Address::create($data);
    }

    public function update(Address $address, array $data): Address
    {
        $address->update($data);
        return $address;
    }

    public function delete($id)
    {
        $address = Address::findOrFail($id);
        return $address->delete();
    }
}
