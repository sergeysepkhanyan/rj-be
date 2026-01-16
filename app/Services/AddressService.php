<?php

namespace App\Services;

use App\Models\Address;
use App\Repositories\Interfaces\AddressRepositoryInterface;
use Illuminate\Support\Facades\DB;

class AddressService
{
    public function __construct(
      protected AddressRepositoryInterface $addressRepository,
    )
    {
    }

    public function listForUser(int $userId)
    {
        return $this->addressRepository->allForUser($userId);
    }

    public function getAllAddresses()
    {
        return $this->addressRepository->all();
    }

    public function createAddress(array $data): Address
    {
        return DB::transaction(function () use ($data) {
            $userId = (int) $data['user_id'];
            $type   = $data['type'] ?? 'shipping';
            [$setDefaultShipping, $setDefaultBilling] = $this->resolveDefaultFlags($data, $type);
            $payload = $this->stripMetaForCreate($data);
            $payload['is_default'] = false;
            $address = $this->addressRepository->create($payload);

            $this->applyDefaults(
                userId: $userId,
                address: $address,
                addressType: $type,
                setDefaultShipping: $setDefaultShipping,
                setDefaultBilling: $setDefaultBilling,
            );

            return $address->fresh();
        });
    }

    public function updateAddress(Address $address, array $data): Address
    {
        return DB::transaction(function () use ($address, $data) {
            $userId = (int) $address->user_id;
            $type   = $address->type;
            [$setDefaultShipping, $setDefaultBilling] = $this->resolveDefaultFlags($data, $type);
            $payload = $this->stripMetaForUpdate($data);
            $address = $this->addressRepository->update($address, $payload);

            $this->applyDefaults(
                userId: $userId,
                address: $address,
                addressType: $type,
                setDefaultShipping: $setDefaultShipping,
                setDefaultBilling: $setDefaultBilling,
            );

            return $address->fresh();
        });
    }

    private function resolveDefaultFlags(array $data, string $type): array
    {
        $setDefaultShipping = (bool)($data['set_default_shipping'] ?? false);
        $setDefaultBilling  = (bool)($data['set_default_billing'] ?? false);
        if (!empty($data['is_default']) && !$setDefaultShipping && !$setDefaultBilling) {
            $setDefaultShipping = $type === 'shipping';
            $setDefaultBilling  = $type === 'billing';
        }

        return [$setDefaultShipping, $setDefaultBilling];
    }

    private function applyDefaults(
        int $userId,
        Address $address,
        string $addressType,
        bool $setDefaultShipping,
        bool $setDefaultBilling
    ): void {
        $wantsOwnTypeDefault =
            ($addressType === 'shipping' && $setDefaultShipping) ||
            ($addressType === 'billing'  && $setDefaultBilling);

        if ($wantsOwnTypeDefault) {
            $this->addressRepository->clearDefaultForUserType($userId, $addressType, $address->id);
            $this->addressRepository->update($address, ['is_default' => true]);
        }

        if ($addressType === 'shipping' && $setDefaultBilling) {
            $this->addressRepository->clearDefaultForUserType($userId, 'billing');
            $this->addressRepository->createCloneForType($address->fresh(), 'billing', true);
        }

        if ($addressType === 'billing' && $setDefaultShipping) {
            $this->addressRepository->clearDefaultForUserType($userId, 'shipping');
            $this->addressRepository->createCloneForType($address->fresh(), 'shipping', true);
        }
    }

    private function stripMetaForCreate(array $data): array
    {
        unset($data['set_default_shipping'], $data['set_default_billing']);
        return $data;
    }

    private function stripMetaForUpdate(array $data): array
    {
        unset(
            $data['user_id'],
            $data['order_id'],
            $data['type'],
            $data['is_default'],
            $data['set_default_shipping'],
            $data['set_default_billing'],
        );

        return $data;
    }


    public function delete(Address $address): bool
    {
        return $this->addressRepository->delete($address);
    }
}

