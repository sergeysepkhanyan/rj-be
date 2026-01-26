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

    public function allForUser(int $userId)
    {
        return Address::where('user_id', $userId)
            ->whereNull('order_id')
            ->get();
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

    public function delete(Address $address): bool
    {
        return $address->delete();
    }

    public function clearDefaultForUserType(int $userId, string $type, ?int $exceptId = null): void
    {
        Address::where('user_id', $userId)
            ->whereNull('order_id')
            ->where('type', $type)
            ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
            ->update(['is_default' => false]);
    }

    public function createCloneForType(Address $source, string $type, bool $isDefault = true): Address
    {
        $data = $source->only([
            'user_id',
            'name','last_name','mobile',
            'address','additional_address',
            'city','country_id','zip_code',
        ]);

        $data['type'] = $type;
        $data['order_id'] = null;
        $data['is_default'] = $isDefault;

        return Address::create($data);
    }
}
