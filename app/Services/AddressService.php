<?php

namespace App\Services;

use App\Models\Address;
use App\Repositories\Interfaces\AddressRepositoryInterface;

class AddressService
{
    protected AddressRepositoryInterface $addressRepository;

    public function __construct(
        AddressRepositoryInterface $addressRepository,
    )
    {
        $this->addressRepository = $addressRepository;
    }

    public function getAllAddresses()
    {
        return $this->addressRepository->all();
    }

    public function createAddress(array $data)
    {
        return $this->addressRepository->create($data);
    }

    public function updateAddress($id, array $data): Address
    {
        return $this->addressRepository->update($id, $data);
    }
}

