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

    public function listForUser(int $userId)
    {
        return $this->addressRepository->allForUser($userId);
    }

    public function getAllAddresses()
    {
        return $this->addressRepository->all();
    }

    public function createAddress(array $data)
    {
        if (!empty($data['is_default'])) {
            Address::where('user_id', $data['user_id'])
                ->where('type', $data['type'])
                ->update(['is_default' => false]);
        }
        return $this->addressRepository->create($data);
    }

    public function updateAddress(Address $address, array $data): Address
    {
        if (!empty($data['is_default'])) {
            Address::where('user_id', $address->user_id)
                ->where('type', $address->type)
                ->update(['is_default' => false]);
        }
        return $this->addressRepository->update($address, $data);
    }

    public function delete(Address $address): bool
    {
        return $this->addressRepository->delete($address);
    }
}

